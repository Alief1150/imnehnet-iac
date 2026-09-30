<?php

namespace App\Jobs;

use App\Contracts\LifecycleDriver;
use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;

/**
 * Runs the create half of an environment lifecycle.
 *
 * Separate job classes rather than one job with a type column, so a failed
 * create is retried or inspected on its own terms and the queue is readable
 * by what it is doing.
 *
 * One attempt only. A lifecycle operation is not idempotent in the useful
 * sense: re-running an apply after a partial failure can adopt a container the
 * operator wanted replaced. On failure the record is marked ERROR with a
 * sanitised message and an operator decides what happens next.
 */
final class ProvisionEnvironment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $environmentId) {}

    public function handle(
        LifecycleDriver $driver,
        EnvironmentLifecycleService $lifecycle,
    ): void {
        $environment = LabEnvironment::query()->find($this->environmentId);

        if ($environment === null) {
            // The record was removed while the job waited, most likely by the
            // delete action. There is nothing left to act on and nothing to
            // report against.
            return;
        }

        $actor = $this->actorFor($environment);

        try {
            $result = $driver->provision($environment);
        } catch (\Throwable $exception) {
            $lifecycle->fail($actor, $environment->refresh(), 'create', new ProcessResult(
                command: [],
                exitCode: -1,
                stdout: '',
                stderr: $exception->getMessage(),
                durationMs: 0.0,
                errorCode: 'driver_exception',
            ));

            return;
        }

        $outputs = $this->decodeOutputs($result);
        $lifecycle->settle($actor, $environment->refresh(), 'create', $result, $outputs);
    }

    /**
     * The record is owned by the infrastructure database while the job runs, so
     * the acting user is read from the portal database and only used for the
     * audit trail.
     */
    private function actorFor(LabEnvironment $environment): ?User
    {
        $authenticated = Auth::user();

        if ($authenticated instanceof User) {
            return $authenticated;
        }

        $ownerId = $environment->user_id;

        return $ownerId === null ? null : User::query()->find($ownerId);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOutputs(ProcessResult $result): array
    {
        $decoded = json_decode($result->stdout, true);

        return is_array($decoded) ? $decoded : [];
    }
}
