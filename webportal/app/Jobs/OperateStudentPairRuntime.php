<?php

namespace App\Jobs;

// IMNEHNET_STUDENT_PAIR_RUNTIME_V2

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\StudentPairRuntimeManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class OperateStudentPairRuntime implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $operation
    ) {}

    public function handle(
        StudentPairRuntimeManager $manager
    ): void {
        Cache::lock(
            'imnehnet:student-pair:runtime:'.$this->sessionId,
            360
        )->block(30, function () use ($manager): void {
            $session = LabSession::find($this->sessionId);

            if (!$session) {
                throw new RuntimeException(
                    'Student LabSession missing.'
                );
            }

            [$a, $v] = $manager->members($session);

            $members = [
                [$a, 'lab-atk'],
                [$v, 'lab-env'],
            ];

            try {
                if (!in_array(
                    $this->operation,
                    ['start', 'stop'],
                    true
                )) {
                    throw new RuntimeException(
                        'Invalid runtime operation.'
                    );
                }

                foreach ($members as [$env]) {
                    $fresh = $env->fresh();

                    if (
                        !$fresh
                        || $fresh->status
                            !== EnvironmentStatus::Provisioning
                        || $fresh->runtime_status
                            !== RuntimeStatus::Unknown
                    ) {
                        throw new RuntimeException(
                            'Pair operation state changed.'
                        );
                    }
                }

                // Inspect both before changing either.
                $actual = [];

                foreach ($members as [$env, $remote]) {
                    $actual[] = $this->inspect(
                        $remote.':'.$env->container_name
                    );
                }

                $desired = $this->operation === 'start'
                    ? 'RUNNING'
                    : 'STOPPED';

                foreach ($members as $index => [$env, $remote]) {
                    if ($actual[$index] === $desired) {
                        continue;
                    }

                    $target = $remote.':'.$env->container_name;

                    $process = new Process([
                        'incus',
                        $this->operation,
                        $target,
                    ]);

                    $process->setTimeout(100);
                    $process->run();

                    if (!$process->isSuccessful()) {
                        throw new RuntimeException(
                            'Incus '.$this->operation
                            .' failed on '.$remote.'.'
                        );
                    }

                    if ($this->inspect($target) !== $desired) {
                        throw new RuntimeException(
                            'Runtime verification failed: '.$remote
                        );
                    }
                }

                foreach ($members as [$env, $remote]) {
                    if (
                        $this->inspect(
                            $remote.':'.$env->container_name
                        ) !== $desired
                    ) {
                        throw new RuntimeException(
                            'Final pair verification failed.'
                        );
                    }
                }

                foreach ($members as [$env]) {
                    $env->forceFill([
                        'status' => $desired === 'RUNNING'
                            ? EnvironmentStatus::Ready
                            : EnvironmentStatus::Stopped,
                        'runtime_status' => $desired === 'RUNNING'
                            ? RuntimeStatus::Running
                            : RuntimeStatus::Stopped,
                        'last_error' => null,
                    ])->save();
                }
            } catch (Throwable $error) {
                report($error);

                foreach ($members as [$env, $remote]) {
                    try {
                        $state = $this->inspect(
                            $remote.':'.$env->container_name
                        );

                        $env->forceFill([
                            'status' => EnvironmentStatus::Error,
                            'runtime_status' => $state === 'RUNNING'
                                ? RuntimeStatus::Running
                                : RuntimeStatus::Stopped,
                            'last_error' =>
                                'Pair operation failed or was partial.',
                        ])->save();
                    } catch (Throwable $inspectionError) {
                        report($inspectionError);

                        $env->forceFill([
                            'status' => EnvironmentStatus::Error,
                            'runtime_status' => RuntimeStatus::Unknown,
                            'last_error' =>
                                'Pair operation failed; inspect Incus.',
                        ])->save();
                    }
                }
            }
        });
    }

    public function failed(?Throwable $error): void
    {
        $session = LabSession::find($this->sessionId);

        if (!$session) {
            return;
        }

        foreach ([
            $session->attacker_environment_id,
            $session->victim_environment_id,
        ] as $id) {
            $env = LabEnvironment::find($id);

            if (
                $env
                && $env->status === EnvironmentStatus::Provisioning
            ) {
                $env->forceFill([
                    'status' => EnvironmentStatus::Error,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'last_error' =>
                        'Pair worker terminated; inspect Incus.',
                ])->save();
            }
        }
    }

    private function inspect(string $target): string
    {
        $process = new Process([
            'incus', 'info', $target,
        ]);

        $process->setTimeout(25);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Cannot inspect '.$target
            );
        }

        if (!preg_match(
            '/^Status:\s*(RUNNING|STOPPED)\s*$/mi',
            $process->getOutput(),
            $match
        )) {
            throw new RuntimeException(
                'Unexpected runtime state: '.$target
            );
        }

        return strtoupper($match[1]);
    }
}
