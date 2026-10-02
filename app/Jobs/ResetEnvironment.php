<?php

namespace App\Jobs;

use App\Contracts\LifecycleDriver;
use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;

final class ResetEnvironment implements ShouldQueue
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

        // Phase one: destroy the container and apply again.
        try {
            $result = $driver->reset($environment);
        } catch (\Throwable $exception) {
            $lifecycle->fail($actor, $environment->refresh(), 'reset', new ProcessResult(
                command: [],
                exitCode: -1,
                stdout: '',
                stderr: $exception->getMessage(),
                durationMs: 0.0,
                errorCode: 'driver_exception',
            ));

            return;
        }

        if (! $result->successful()) {
            $lifecycle->fail($actor, $environment->refresh(), 'reset', $result);

            return;
        }

        // Read before the bootstrap runs. The address comes from the apply, and a
        // reset may have handed the container a different one, so it has to be
        // captured from this phase rather than looked for afterwards.
        $outputs = $this->decodeOutputs($result);

        // Phase two: bootstrap the new container.
        //
        // A reset destroys the container and applies again, so what comes back is
        // a brand new container with none of the bootstrap in it. Settling on the
        // apply alone produced a record claiming READY for a container with no
        // student account in it, which is the same broken environment a create
        // used to produce.
        $lifecycle->markConfiguring($actor, $environment->refresh());

        try {
            $configured = $driver->configure($environment);
        } catch (\Throwable $exception) {
            $lifecycle->fail($actor, $environment->refresh(), 'reset', new ProcessResult(
                command: $result->command,
                exitCode: -1,
                stdout: $result->stdout,
                stderr: $exception->getMessage(),
                durationMs: $result->durationMs,
                errorCode: 'driver_exception',
            ), $outputs);

            return;
        }

        // The container exists and its address is known, so a bootstrap failure is
        // reported as a failure rather than passed off as a usable environment.
        // The record keeps the address, because it is real.
        if (! $configured->successful()) {
            $lifecycle->fail($actor, $environment->refresh(), 'reset', new ProcessResult(
                command: $configured->command,
                exitCode: $configured->exitCode,
                stdout: $result->stdout,
                stderr: $configured->stderr !== '' ? $configured->stderr : $configured->stdout,
                durationMs: $result->durationMs + $configured->durationMs,
                errorCode: $configured->errorCode ?? 'configure_failed',
            ), $outputs);

            return;
        }

        $lifecycle->settle(
            $actor,
            $environment->refresh(),
            'reset',
            $this->combine($result, $configured),
            $outputs,
        );
    }

    /**
     * The record of a two phase operation, for the log entry that settles it.
     *
     * Both commands have to succeed and both took time, so the combined result
     * reports the command that was actually run and the time it actually took
     * rather than one phase of it. The outputs carried through are the ones the
     * state needs, which only the first phase produces.
     */
    private function combine(ProcessResult $first, ProcessResult $second): ProcessResult
    {
        return new ProcessResult(
            command: [...$first->command, '&&', ...$second->command],
            exitCode: 0,
            stdout: $first->stdout,
            stderr: trim($first->stderr."\n".$second->stderr),
            durationMs: $first->durationMs + $second->durationMs,
        );
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

        // Flattened for the same reason as the create path: the machine readable
        // form wraps every value, and settle() stores the address out of it.
        return is_array($decoded) ? OpenTofuService::flattenOutputs($decoded) : [];
    }
}