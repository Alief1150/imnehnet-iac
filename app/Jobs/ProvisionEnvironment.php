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

        // Phase one: OpenTofu creates the container.
        try {
            $applied = $driver->provision($environment);
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

        if (! $applied->successful()) {
            $lifecycle->fail($actor, $environment->refresh(), 'create', $applied);

            return;
        }

        // Read here, before the bootstrap runs. The address and the runtime the
        // record settles with both come from the apply; the Ansible phase reports
        // neither, so it cannot supply them afterwards.
        $outputs = $this->decodeOutputs($applied);

        // Phase two: Ansible makes the container usable.
        //
        // A container is not a usable lab environment. OpenTofu creates it, but
        // this is what makes it one: the student account the terminal logs in as,
        // the packages a lab exercise needs, the shell. The phase was declared on
        // the driver contract, implemented, given its own in-flight status and
        // shown in the table, and then called by nothing. Environments were
        // recorded READY with no student account inside them, so the portal
        // promised a shell that did not exist.
        $lifecycle->markConfiguring($actor, $environment->refresh());

        try {
            $configured = $driver->configure($environment);
        } catch (\Throwable $exception) {
            $lifecycle->fail($actor, $environment->refresh(), 'create', new ProcessResult(
                command: $applied->command,
                exitCode: -1,
                stdout: $applied->stdout,
                stderr: $exception->getMessage(),
                durationMs: $applied->durationMs,
                errorCode: 'driver_exception',
            ), $outputs);

            return;
        }

        // The container exists and its address is known, so a bootstrap failure
        // is reported as a failure rather than passed off as a usable
        // environment. The record keeps the address, because it is real and it is
        // what an operator needs in order to look at the half-built container.
        // Delete is offered from ERROR and removes it.
        if (! $configured->successful()) {
            $lifecycle->fail($actor, $environment->refresh(), 'create', new ProcessResult(
                command: $configured->command,
                exitCode: $configured->exitCode,
                stdout: $applied->stdout,
                stderr: $configured->stderr !== '' ? $configured->stderr : $configured->stdout,
                durationMs: $applied->durationMs + $configured->durationMs,
                errorCode: $configured->errorCode ?? 'configure_failed',
            ), $outputs);

            return;
        }

        // settle() is where a result becomes state, so an exception thrown inside
        // it leaves the record in PROVISIONING for ever with nothing to explain
        // it. That happened: the create crashed casting an output, the row sat in
        // PROVISIONING showing "Not observed yet", and only the failed job table
        // said why. Anything thrown from here on becomes a recorded failure.
        try {
            $lifecycle->settle(
                $actor,
                $environment->refresh(),
                'create',
                $this->combine($applied, $configured),
                $outputs,
            );
        } catch (\Throwable $exception) {
            $lifecycle->fail($actor, $environment->refresh(), 'create', new ProcessResult(
                command: $applied->command,
                exitCode: $applied->exitCode,
                stdout: $applied->stdout,
                stderr: $exception->getMessage(),
                durationMs: $applied->durationMs + $configured->durationMs,
                errorCode: 'settle_failed',
            ), $outputs);
        }
    }

    /**
     * The record of a two phase operation, for the log entry that settles it.
     *
     * Provisioning is two commands, not one: OpenTofu creates the container and
     * Ansible then makes it usable. Both have to succeed, and both took time, so
     * the combined result reports the command that was actually run and the time
     * it actually took rather than one phase of it. The outputs carried through
     * are the ones the state needs, which only the first phase produces.
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

        // Flattened again here because the driver re-encodes what it read. Doing
        // it in both places means an output is never an array by the time the
        // lifecycle looks at it, whichever path produced it.
        return is_array($decoded) ? OpenTofuService::flattenOutputs($decoded) : [];
    }
}