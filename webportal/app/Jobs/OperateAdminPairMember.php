<?php

namespace App\Jobs;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class OperateAdminPairMember implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 180;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $pairId,
        public readonly int $environmentId,
        public readonly string $operation
    ) {}

    public static function assertOwnership(
        AdminEnvironmentPair $pair,
        LabEnvironment $attacker,
        LabEnvironment $victim
    ): void {
        if (
            $attacker->isProtected()
            || $victim->isProtected()
            || (int) $attacker->user_id !== (int) $pair->user_id
            || (int) $victim->user_id !== (int) $pair->user_id
            || $attacker->container_name
                !== "attacker-user-{$pair->user_id}-p{$pair->id}"
            || $victim->container_name
                !== "victim-user-{$pair->user_id}-p{$pair->id}"
            || $attacker->tofu_workspace
                !== "admin-attacker-p{$pair->id}"
            || $victim->tofu_workspace
                !== "admin-victim-p{$pair->id}"
            || rtrim((string) $attacker->incus_remote, ':')
                !== 'lab-atk'
            || rtrim((string) $victim->incus_remote, ':')
                !== 'lab-env'
            || ! str_starts_with(
                (string) $attacker->internal_ip,
                '10.30.0.'
            )
            || ! str_starts_with(
                (string) $victim->internal_ip,
                '10.20.0.'
            )
            || ! in_array(
                $attacker->incus_project,
                [null, 'default'],
                true
            )
            || ! in_array(
                $victim->incus_project,
                [null, 'default'],
                true
            )
        ) {
            throw new RuntimeException(
                'Unsafe Admin Pair identity.'
            );
        }

        if (
            LabSession::query()
                ->where(function ($query) use ($attacker, $victim) {
                    $ids = [$attacker->id, $victim->id];

                    $query
                        ->whereIn('attacker_environment_id', $ids)
                        ->orWhereIn('victim_environment_id', $ids);
                })
                ->exists()
        ) {
            throw new RuntimeException(
                'Student LabSession references this pair.'
            );
        }
    }

    public function handle(): void
    {
        Cache::lock(
            'imnehnet:admin-pair:lifecycle:'.$this->pairId,
            240
        )->block(60, function (): void {
            $pair = AdminEnvironmentPair::query()
                ->find($this->pairId);

            if (! $pair || $pair->status !== 'OPERATING') {
                return;
            }

            try {
                if (! in_array(
                    $this->operation,
                    ['start', 'stop'],
                    true
                )) {
                    throw new RuntimeException(
                        'Invalid runtime operation.'
                    );
                }

                $attacker = LabEnvironment::query()
                    ->findOrFail($pair->attacker_environment_id);

                $victim = LabEnvironment::query()
                    ->findOrFail($pair->victim_environment_id);

                self::assertOwnership(
                    $pair,
                    $attacker,
                    $victim
                );

                $target = match ($this->environmentId) {
                    (int) $attacker->id => $attacker,
                    (int) $victim->id => $victim,
                    default => throw new RuntimeException(
                        'Environment does not belong to pair.'
                    ),
                };

                $remote = rtrim(
                    (string) $target->incus_remote,
                    ':'
                );

                $incusTarget = $remote.':'.$target->container_name;

                $desired = $this->operation === 'start'
                    ? 'RUNNING'
                    : 'STOPPED';

                $current = $this->readStatus($incusTarget);

                if ($current !== $desired) {
                    if (
                        ($this->operation === 'start'
                            && $current !== 'STOPPED')
                        || ($this->operation === 'stop'
                            && $current !== 'RUNNING')
                    ) {
                        throw new RuntimeException(
                            'Unexpected current Incus state.'
                        );
                    }

                    $process = new Process([
                        'incus',
                        $this->operation,
                        $incusTarget,
                    ]);

                    $process->setTimeout(120);
                    $process->run();

                    if (! $process->isSuccessful()) {
                        throw new RuntimeException(
                            'Incus runtime operation failed: '.
                            substr(
                                $process->getErrorOutput(),
                                0,
                                180
                            )
                        );
                    }
                }

                if ($this->readStatus($incusTarget) !== $desired) {
                    throw new RuntimeException(
                        'Incus did not reach desired state.'
                    );
                }

                $target->forceFill([
                    'status' => EnvironmentStatus::Ready,
                    'runtime_status' => $desired === 'RUNNING'
                        ? RuntimeStatus::Running
                        : RuntimeStatus::Stopped,
                    'last_error' => null,
                ])->save();

                $pair->forceFill([
                    'status' => 'READY',
                    'last_error' => null,
                ])->save();

            } catch (Throwable $error) {
                report($error);
                $this->markError($error);
            }
        });
    }

    public function failed(?Throwable $error): void
    {
        $this->markError($error);
    }

    private function markError(?Throwable $error): void
    {
        $pair = AdminEnvironmentPair::query()
            ->find($this->pairId);

        if (! $pair || $pair->status !== 'OPERATING') {
            return;
        }

        $pair->forceFill([
            'status' => 'ERROR',
            'last_error' => 'Individual runtime operation failed. '
                .'Inspect actual Incus state before recovery.',
        ])->save();

        LabEnvironment::query()
            ->find($this->environmentId)
            ?->forceFill([
                'status' => EnvironmentStatus::Error,
                'runtime_status' => RuntimeStatus::Unknown,
                'last_error' => 'Individual runtime operation incomplete.',
            ])->save();
    }

    private function readStatus(string $target): string
    {
        $process = new Process(['incus', 'info', $target]);
        $process->setTimeout(30);
        $process->run();

        if (
            ! $process->isSuccessful()
            || ! preg_match(
                '/^Status:\s*(RUNNING|STOPPED)\s*$/mi',
                $process->getOutput(),
                $match
            )
        ) {
            throw new RuntimeException(
                'Cannot verify Incus container state: '.$target
            );
        }

        return strtoupper($match[1]);
    }
}
