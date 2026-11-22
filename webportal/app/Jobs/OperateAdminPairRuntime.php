<?php
namespace App\Jobs;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class OperateAdminPairRuntime implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $pairId, public readonly string $operation) {}

    public function handle(): void
    {
        Cache::lock('imnehnet:admin-pair:lifecycle:'.$this->pairId, 360)
            ->block(30, function (): void {
                $pair = AdminEnvironmentPair::query()->find($this->pairId);
                if (!$pair || $pair->status !== 'OPERATING') return;
                $a = LabEnvironment::query()->find($pair->attacker_environment_id);
                $v = LabEnvironment::query()->find($pair->victim_environment_id);
                if (!$a || !$v) {
                    $pair->forceFill(['status' => 'ERROR', 'last_error' => 'Pair member missing.'])->save();
                    return;
                }
                $members = [[$a, 'lab-atk'], [$v, 'lab-env']];
                try {
                    if (!in_array($this->operation, ['start', 'stop'], true)) throw new RuntimeException('Invalid operation.');
                    foreach ($members as [$env, $remote]) {
                        if ($env->isProtected() || (int) $env->user_id !== (int) $pair->user_id
                            || $env->container_name !== ($remote === 'lab-atk' ? 'attacker' : 'victim').'-user-'.$pair->user_id.'-p'.$pair->id
                            || rtrim((string) $env->incus_remote, ':') !== $remote
                            || ($env->incus_project ?: 'default') !== 'default') {
                            throw new RuntimeException('Pair identity mismatch; no operation authorized.');
                        }
                    }
                    // Inspect BOTH before changing EITHER. Missing instances fail closed.
                    $before = [];
                    foreach ($members as [$env, $remote]) {
                        $before[] = $this->inspect($remote.':'.$env->container_name);
                    }
                    $desired = $this->operation === 'start' ? 'RUNNING' : 'STOPPED';
                    foreach ($members as $i => [$env, $remote]) {
                        if ($before[$i] === $desired) continue;
                        $target = $remote.':'.$env->container_name;
                        $p = new Process(['incus', $this->operation, $target]);
                        $p->setTimeout(100);
                        $p->run();
                        if (!$p->isSuccessful()) throw new RuntimeException('Incus '.$this->operation.' failed for '.$remote.' plane.');
                        if ($this->inspect($target) !== $desired) throw new RuntimeException('Runtime verification failed for '.$remote.' plane.');
                    }
                    foreach ($members as [$env, $remote]) {
                        if ($this->inspect($remote.':'.$env->container_name) !== $desired) {
                            throw new RuntimeException('Final pair runtime verification failed.');
                        }
                    }
                    foreach ($members as [$env]) {
                        $env->forceFill([
                            'status' => $desired === 'RUNNING' ? EnvironmentStatus::Ready : EnvironmentStatus::Stopped,
                            'runtime_status' => $desired === 'RUNNING' ? RuntimeStatus::Running : RuntimeStatus::Stopped,
                            'last_error' => null,
                        ])->save();
                    }
                    $pair->forceFill(['status' => 'READY', 'last_error' => null])->save();
                } catch (Throwable $error) {
                    report($error);
                    foreach ($members as [$env, $remote]) {
                        try {
                            $actual = $this->inspect($remote.':'.$env->container_name);
                            $env->forceFill([
                                'status' => $actual === 'RUNNING' ? EnvironmentStatus::Ready : EnvironmentStatus::Stopped,
                                'runtime_status' => $actual === 'RUNNING' ? RuntimeStatus::Running : RuntimeStatus::Stopped,
                                'last_error' => null,
                            ])->save();
                        } catch (Throwable $inspectionError) {
                            $env->forceFill(['status' => EnvironmentStatus::Error,
                                'runtime_status' => RuntimeStatus::Unknown,
                                'last_error' => 'Pair operation failed; Incus inspection required.'])->save();
                        }
                    }
                    $pair->forceFill(['status' => 'ERROR',
                        'last_error' => 'Pair runtime operation failed or is partial; inspect Incus.'])->save();
                }
            });
    }

    public function failed(?Throwable $error): void
    {
        $pair = AdminEnvironmentPair::query()->find($this->pairId);
        if ($pair && $pair->status === 'OPERATING') {
            $pair->forceFill(['status' => 'ERROR',
                'last_error' => 'Runtime worker terminated; inspect both containers.'])->save();
        }
    }

    private function inspect(string $target): string
    {
        $p = new Process(['incus', 'info', $target]);
        $p->setTimeout(25);
        $p->run();
        if (!$p->isSuccessful()) throw new RuntimeException('Cannot inspect '.$target);
        if (!preg_match('/^Status:\s*(RUNNING|STOPPED)\s*$/mi', $p->getOutput(), $match)) {
            throw new RuntimeException('Unrecognized runtime state for '.$target);
        }
        return strtoupper($match[1]);
    }
}
