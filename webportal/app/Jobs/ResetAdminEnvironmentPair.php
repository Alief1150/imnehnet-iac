<?php

namespace App\Jobs;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ResetAdminEnvironmentPair implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 2400;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $pairId) {}

    public function handle(OpenTofuService $tofu): void
    {
        Cache::lock('imnehnet:admin-pair:lifecycle:'.$this->pairId, 2700)
            ->block(60, function () use ($tofu): void {
                $pair = AdminEnvironmentPair::query()->find($this->pairId);
                if (! $pair || $pair->status !== 'RESETTING') {
                    return;
                }
                try {
                    $a = LabEnvironment::query()->findOrFail($pair->attacker_environment_id);
                    $v = LabEnvironment::query()->findOrFail($pair->victim_environment_id);
                    $this->assertPair($pair, $a, $v);
                    $folder = storage_path('app/private/admin-pair-reset/pair-'.$this->pairId.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)));
                    if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) {
                        throw new RuntimeException('Cannot create private reset backup directory.');
                    }
                    chmod($folder, 0700);
                    // Both plans are prepared and ownership-checked before any replacement.
                    $this->prepare($tofu, $v, 'victim', $folder);
                    $this->prepare($tofu, $a, 'attacker', $folder);
                    $this->apply($tofu, $v, 'victim', $folder);
                    $this->apply($tofu, $a, 'attacker', $folder);
                    foreach ([$a, $v] as $env) {
                        $env->forceFill([
                            'status' => EnvironmentStatus::Ready,
                            'runtime_status' => RuntimeStatus::Running,
                            'provisioned_at' => now(),
                            'last_error' => null,
                        ])->save();
                    }
                    $pair->forceFill(['status' => 'READY', 'last_error' => null])->save();
                } catch (Throwable $e) {
                    report($e);
                    $this->markError();
                }
            });
    }

    public function failed(?Throwable $e): void
    {
        $this->markError();
        if ($e) report($e);
    }

    private function markError(): void
    {
        $pair = AdminEnvironmentPair::query()->find($this->pairId);
        if (! $pair || $pair->status === 'READY') return;
        $pair->forceFill([
            'status' => 'ERROR',
            'last_error' => 'Reset failed or partial. Do not retry automatically; inspect Incus, state and Laravel logs.',
        ])->save();
        foreach ([$pair->attacker_environment_id, $pair->victim_environment_id] as $id) {
            $env = LabEnvironment::query()->find($id);
            $env?->forceFill(['status' => EnvironmentStatus::Error,
                'runtime_status' => RuntimeStatus::Unknown,
                'last_error' => 'Admin Pair reset incomplete.'])->save();
        }
    }

    private function assertPair(AdminEnvironmentPair $p, LabEnvironment $a, LabEnvironment $v): void
    {
        foreach ([['attacker', $a, 'lab-atk', '10.30.0.'], ['victim', $v, 'lab-env', '10.20.0.']] as [$role, $env, $remote, $prefix]) {
            if ($env->isProtected()
                || (int) $env->user_id !== (int) $p->user_id
                || $env->container_name !== "{$role}-user-{$p->user_id}-p{$p->id}"
                || $env->tofu_workspace !== "admin-{$role}-p{$p->id}"
                || rtrim((string) $env->incus_remote, ':') !== $remote
                || ! str_starts_with((string) $env->internal_ip, $prefix)
                || ! in_array($env->incus_project, [null, 'default'], true)) {
                throw new RuntimeException('Reset pair ownership mismatch.');
            }
            $this->mustRunning($remote.':'.$env->container_name);
        }
        if (LabSession::query()->whereIn('attacker_environment_id', [$a->id, $v->id])
            ->orWhereIn('victim_environment_id', [$a->id, $v->id])->exists()) {
            throw new RuntimeException('Student LabSession owns this resource.');
        }
        if (TerminalSession::query()->where('purpose', 'admin_lab_pair')
            ->whereIn('lab_environment_id', [$a->id, $v->id])
            ->whereNull('closed_at')->exists()) {
            throw new RuntimeException('Active Admin Pair terminal sessions.');
        }
    }

    private function mustRunning(string $target): void
    {
        $p = new Process(['incus', 'info', $target]);
        $p->setTimeout(30);
        $p->run();
        if (! $p->isSuccessful() || ! preg_match('/^Status:\s*RUNNING\s*$/mi', $p->getOutput())) {
            throw new RuntimeException('Instance missing or not RUNNING: '.$target);
        }
    }

    private function variables(LabEnvironment $env, string $role): array
    {
        return [
            'container_name' => (string) $env->container_name,
            'container_image' => (string) $env->image,
            'incus_remote' => rtrim((string) $env->incus_remote, ':'),
            'static_ipv4' => (string) $env->internal_ip,
            'incus_network' => (string) config('infrastructure.incus_network', 'incusbr0'),
            'cpu_limit' => (int) $env->cpu_limit,
            'memory_limit' => (int) $env->memory_limit_mb.'MiB',
            'desired_running' => 'true',
            'security_acls' => $role === 'attacker'
                ? (string) config('infrastructure.attacker_acl', 'ctf-attacker') : '',
        ];
    }

    private function prepare(OpenTofuService $tofu, LabEnvironment $env, string $role, string $folder): void
    {
        $tofu->withWorkspace($env, function (OpenTofuService $service) use ($env, $role, $folder): void {
            $state = $service->run(['state', 'pull']);
            if (! $state->successful()) throw new RuntimeException('State pull failed: '.$role);
            $j = json_decode($state->stdout, true, 512, JSON_THROW_ON_ERROR);
            $r = $j['resources'] ?? [];
            if (count($r) !== 1 || ($r[0]['mode'] ?? '') !== 'managed'
                || ($r[0]['type'] ?? '') !== 'incus_instance'
                || ($r[0]['name'] ?? '') !== 'student_lab'
                || count($r[0]['instances'] ?? []) !== 1) {
                throw new RuntimeException('Unexpected state resource inventory: '.$role);
            }
            $attrs = $r[0]['instances'][0]['attributes'] ?? [];
            if (($attrs['name'] ?? '') !== $env->container_name
                || rtrim((string) ($attrs['remote'] ?? ''), ':') !== rtrim((string) $env->incus_remote, ':')
                || ($attrs['project'] ?? 'default') !== 'default') {
                throw new RuntimeException('State ownership mismatch: '.$role);
            }
            $backup = "{$folder}/{$role}-state.json";
            if (file_put_contents($backup, $state->stdout, LOCK_EX) === false) {
                throw new RuntimeException('State backup failed: '.$role);
            }
            chmod($backup, 0600);
            $args = ['plan', '-replace=incus_instance.student_lab', '-input=false', '-no-color', '-out='.$folder.'/'.$role.'.tfplan'];
            foreach ($this->variables($env, $role) as $key => $value) {
                $args[] = '-var='.$key.'='.$value;
            }
            $plan = $service->run($args);
            if (! $plan->successful()) {
                throw new RuntimeException('Reset plan failed: '.$role.' '.substr($plan->stderr, 0, 200));
            }
            $show = $service->run(['show', '-json', $folder.'/'.$role.'.tfplan']);
            if (! $show->successful()) throw new RuntimeException('Cannot inspect reset plan: '.$role);
            $decoded = json_decode($show->stdout, true, 512, JSON_THROW_ON_ERROR);
            $changes = $decoded['resource_changes'] ?? [];
            if (count($changes) !== 1) throw new RuntimeException('Reset plan touches multiple resources: '.$role);
            $c = $changes[0];
            $actions = $c['change']['actions'] ?? [];
            if (($c['address'] ?? '') !== 'incus_instance.student_lab'
                || ($c['mode'] ?? '') !== 'managed'
                || ($c['type'] ?? '') !== 'incus_instance'
                || ! in_array($actions, [['delete', 'create'], ['create', 'delete']], true)
                || ($c['change']['before']['name'] ?? '') !== $env->container_name
                || rtrim((string) ($c['change']['before']['remote'] ?? ''), ':') !== rtrim((string) $env->incus_remote, ':')
                || ($c['change']['before']['project'] ?? 'default') !== 'default'
                || ($c['change']['after']['name'] ?? '') !== $env->container_name
                || rtrim((string) ($c['change']['after']['remote'] ?? ''), ':') !== rtrim((string) $env->incus_remote, ':')) {
                throw new RuntimeException('Unsafe replacement plan: '.$role);
            }
        });
    }

    private function apply(OpenTofuService $tofu, LabEnvironment $env, string $role, string $folder): void
    {
        $tofu->withWorkspace($env, function (OpenTofuService $service) use ($role, $folder): void {
            $result = $service->run(['apply', '-input=false', '-no-color', $folder.'/'.$role.'.tfplan']);
            if (! $result->successful()) {
                throw new RuntimeException('Reset apply failed: '.$role.' '.substr($result->stderr, 0, 200));
            }
        });
        $this->mustRunning(rtrim((string) $env->incus_remote, ':').':'.$env->container_name);
    }
}
