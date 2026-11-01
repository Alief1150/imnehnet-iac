<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Services\GuidedCtf\AttackerTools;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LifecycleFailure;
use App\Services\TerminalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ChangePairedLabPower implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $sessionId, public readonly bool $start) {}

    public function handle(LabFirewall $firewall, TerminalService $terminals): void
    {
        Cache::lock('imnehnet:ctf:session-lifecycle:'.$this->sessionId, 1200)->block(10, function () use ($firewall, $terminals): void {
            try {
                $s = LabSession::query()->find($this->sessionId);
                if (! $s || ! in_array($s->status, [LabSessionStatus::Ready, LabSessionStatus::Running, LabSessionStatus::Completed, LabSessionStatus::Stopped], true)) {
                    return;
                }
                if ($this->start && $s->status !== LabSessionStatus::Stopped) {
                    return;
                }
                $s->load(['victimEnvironment', 'attackerEnvironment']);
                $environments = [$s->victimEnvironment, $s->attackerEnvironment];
                foreach (['victim', 'attacker'] as $index => $role) {
                    $e = $environments[$index];
                    $remote = rtrim((string) config($role === 'victim' ? 'infrastructure.incus_remote' : 'infrastructure.incus_attacker_remote'), ':');
                    if (! $e || $e->isProtected() || (int) $e->user_id !== (int) $s->user_id
                        || $e->container_name !== $role.'-user-'.$s->user_id
                        || $e->container_name !== $s->{$role.'_container'}
                        || rtrim((string) $e->incus_remote, ':') !== $remote
                        || rtrim((string) $s->{$role.'_remote'}, ':') !== $remote
                        || ($e->incus_project ?: 'default') !== 'default') {
                        throw new RuntimeException('Pair ownership verification failed.');
                    }
                }
                try {
                    $s->forceFill(['last_error' => null])->save();
                    // Keep the pair isolated throughout a partial power transition.
                    $firewall->revoke($s);
                    foreach ($environments as $e) {
                        foreach (TerminalSession::query()->where('lab_environment_id', $e->id)->usable()->get() as $terminal) {
                            $terminals->close($terminal);
                        }
                        $target = rtrim($e->incus_remote, ':').':'.$e->container_name;
                        $desired = $this->start ? 'Running' : 'Stopped';
                        if ($this->state($target) !== $desired) {
                            $args = $this->start ? ['start', $target] : ['stop', $target, '--timeout=25'];
                            $this->run($args, 40);
                        }
                        if ($this->state($target) !== $desired) {
                            throw new RuntimeException('Power transition not confirmed by Incus.');
                        }
                        $e->forceFill(['runtime_status' => $this->start ? RuntimeStatus::Running : RuntimeStatus::Stopped])->save();
                    }
                    if ($this->start) {
                        app(AttackerTools::class)->ensure($s);
                        $firewall->permit($s);
                    }
                    $s->forceFill(['status' => $this->start ? LabSessionStatus::Ready : LabSessionStatus::Stopped, 'last_error' => null])->save();
                } catch (Throwable $error) {
                    // Record observed runtime independently; partial work is never called success.
                    foreach ($environments as $e) {
                        try {
                            $state = $this->state(rtrim($e->incus_remote, ':').':'.$e->container_name);
                            $e->forceFill(['runtime_status' => RuntimeStatus::tryFrom($state) ?? RuntimeStatus::Unknown])->save();
                        } catch (Throwable) {
                            $e->forceFill(['runtime_status' => RuntimeStatus::Unknown])->save();
                        }
                    }
                    throw $error;
                }
            } finally {
                Cache::forget('imnehnet:terminal-hold:'.$this->sessionId);
            }
        });
    }

    private function state(string $target): string
    {
        $data = json_decode($this->run(['query', $this->apiTarget($target)], 15), true, 512, JSON_THROW_ON_ERROR);

        return (string) ($data['status'] ?? 'Unknown');
    }

    private function apiTarget(string $target): string
    {
        [$remote, $name] = explode(':', $target, 2);

        return $remote.':/1.0/instances/'.rawurlencode($name).'/state';
    }

    private function run(array $args, int $timeout): string
    {
        $p = new Process([(string) config('infrastructure.incus_binary', 'incus'), ...$args], env: ['INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus')]);
        $p->setTimeout($timeout);
        $p->mustRun();

        return $p->getOutput();
    }

    public function failed(?Throwable $error): void
    {
        Cache::forget('imnehnet:terminal-hold:'.$this->sessionId);
        LifecycleFailure::record($this->sessionId, $this->start ? 'Resume Lab' : 'Stop Lab', $error ?? new RuntimeException('Worker timed out.'));
    }
}
