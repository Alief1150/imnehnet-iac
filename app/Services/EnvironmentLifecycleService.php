<?php

namespace App\Services;

use App\Data\ProcessResult;
use App\Enums\ActivityAction;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\DeleteEnvironment;
use App\Jobs\ProvisionEnvironment;
use App\Jobs\ResetEnvironment;
use App\Jobs\StartEnvironment;
use App\Jobs\StopEnvironment;
use App\Models\LabEnvironment;
use App\Models\User;
use DomainException;

/**
 * Decides what a lifecycle action means and records what it did.
 *
 * A Filament action calls request(), which validates the transition, writes the
 * record into its in-flight state and hands the work to a queue. The job later
 * calls settle(), which is the only place a result becomes state.
 *
 * The split matters because the two halves run in different processes. The
 * request half must be fast and must never touch infrastructure, and the
 * result half must never be able to report success for work that is still
 * running.
 */
class EnvironmentLifecycleService
{
    /**
     * Which lifecycle status an action puts the record into while it runs.
     *
     * PROVISIONING is the generic "converging onto the desired state" state
     * and covers create, start and stop: in all three the infrastructure is
     * being asked to reach a new configuration, and the specification treats
     * that as provisioning rather than inventing a state per verb.
     */
    private const IN_FLIGHT = [
        'create' => EnvironmentStatus::Provisioning,
        'start' => EnvironmentStatus::Provisioning,
        'stop' => EnvironmentStatus::Provisioning,
        'reset' => EnvironmentStatus::Resetting,
        'delete' => EnvironmentStatus::Deleting,
    ];

    private const JOB = [
        'create' => ProvisionEnvironment::class,
        'start' => StartEnvironment::class,
        'stop' => StopEnvironment::class,
        'reset' => ResetEnvironment::class,
        'delete' => DeleteEnvironment::class,
    ];

    /**
     * Actions that do not depend on the container being up or down.
     *
     * @var list<string>
     */
    private const ALWAYS = ['reset', 'refresh'];

    /**
     * Extra actions allowed from a settled state, on top of the ones that are
     * always available.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        EnvironmentStatus::Ready->value => ['delete'],
        EnvironmentStatus::Stopped->value => ['start', 'delete'],
        EnvironmentStatus::Error->value => ['delete'],
    ];

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * @return list<string>
     */
    public function allowedActions(LabEnvironment $environment): array
    {
        if ($environment->isProtected()) {
            // The reference environment exists to be observed, never operated
            // on. It is not even listed as an action.
            return ['refresh'];
        }

        // Nothing else is offered while an operation is in flight: a second
        // operation would race the first through a shared OpenTofu working
        // directory.
        if (! $environment->isSettled()) {
            return ['refresh'];
        }

        // A failed record is not asked to converge anywhere. Its runtime may be
        // known, but the record itself is not trustworthy until an operator has
        // read the error, so only the read-only refresh and the destructive
        // delete are offered. Guessing from the runtime here would act on a
        // record that is known to be wrong.
        if ($environment->status === EnvironmentStatus::Error) {
            return ['delete', 'refresh'];
        }

        $actions = self::ALWAYS;

        foreach (self::ALLOWED[$environment->status->value] ?? [] as $action) {
            $actions[] = $action;
        }

        // Start and stop follow what the container is actually doing, not the
        // lifecycle status. Offering Start on a running container is a button
        // that does nothing; offering Stop on a stopped one is worse than
        // useless, because it looks like the container is under control when it
        // is not.
        //
        // Exactly one of the two is offered. A record whose status and runtime
        // disagree is a state mismatch, and two contradictory buttons for the
        // same container would help nobody. When the runtime is unknown neither
        // is offered, because the record cannot be trusted to say which one the
        // container needs.
        $runtime = $environment->runtime_status;

        if ($runtime === RuntimeStatus::Running) {
            $actions[] = 'stop';
            $actions = array_diff($actions, ['start']);
        } elseif ($runtime === RuntimeStatus::Stopped) {
            $actions[] = 'start';
            $actions = array_diff($actions, ['stop']);
        }

        $actions = array_values(array_unique($actions));

        sort($actions);

        return $actions;
    }

    public function isActionAllowed(LabEnvironment $environment, string $action): bool
    {
        return in_array($action, $this->allowedActions($environment), true);
    }

    /**
     * Validate the transition, mark the record in-flight and queue the work.
     *
     * Nothing here touches the infrastructure: the request half has to return
     * in milliseconds so the interface can go straight back to polling.
     */
    public function request(?User $actor, LabEnvironment $environment, string $action): LabEnvironment
    {
        if ($action === 'refresh') {
            throw new DomainException('Refreshing is a read-only action and is not queued.');
        }

        if (! array_key_exists($action, self::JOB)) {
            throw new DomainException(sprintf('"%s" is not a lifecycle action.', $action));
        }

        if ($environment->isProtected()) {
            throw new DomainException(sprintf(
                '"%s" is the protected reference environment and cannot be changed by the portal.',
                (string) $environment->container_name
            ));
        }

        if (! $this->isActionAllowed($environment, $action)) {
            throw new DomainException(sprintf(
                'The environment is %s, so "%s" is not available from that state.',
                $environment->status->value,
                $action
            ));
        }

        $before = $environment->status;

        $environment->update([
            'status' => self::IN_FLIGHT[$action],
            'runtime_status' => RuntimeStatus::Unknown,
            'last_error' => null,
        ]);

        $this->activity->record(
            actor: $actor,
            action: ActivityAction::requested($action),
            resource: $environment,
            status: 'requested',
            description: sprintf(
                'Requested %s for %s.',
                $action,
                (string) $environment->container_name
            ),
            metadata: [
                'container_name' => (string) $environment->container_name,
                'workspace' => (string) $environment->tofu_workspace,
                'from_state' => $before->value,
                'to_state' => self::IN_FLIGHT[$action]->value,
                'requested_action' => $action,
            ],
        );

        $job = self::JOB[$action];
        $job::dispatch((int) $environment->getKey());

        return $environment->refresh();
    }

    /**
     * Apply the outcome of a queued operation to the record.
     *
     * Called from the job, never from the request. Nothing reports success
     * while infrastructure work is still running, because this is the only
     * place a status becomes settled.
     */
    public function settle(
        ?User $actor,
        LabEnvironment $environment,
        string $action,
        ProcessResult $result,
        array $outputs = [],
    ): LabEnvironment {
        if (! $result->successful()) {
            return $this->fail($actor, $environment, $action, $result);
        }

        $attributes = [
            'status' => $action === 'stop' ? EnvironmentStatus::Stopped : EnvironmentStatus::Ready,
            'last_error' => null,
            'runtime_status' => $this->runtimeFromOutputs($outputs, $action),
        ];

        if (($outputs['container_ipv4'] ?? null) !== null) {
            $attributes['internal_ip'] = $this->addressFromOutputs((string) $outputs['container_ipv4']);
        }

        if ($action === 'create' || $action === 'reset') {
            $attributes['provisioned_at'] = now();
        }

        // A record only leaves the inventory once the infrastructure confirms
        // the container is gone. Deleting the row first would leave a container
        // running with nothing tracking it.
        if ($action === 'delete') {
            $environment->delete();
            $this->activity->record(
                actor: $actor,
                action: ActivityAction::DeleteCompleted,
                resource: $environment,
                status: 'completed',
                description: sprintf(
                    'Destroyed container %s and removed its workspace.',
                    (string) $environment->container_name
                ),
                metadata: [
                    'container_name' => (string) $environment->container_name,
                    'workspace' => (string) $environment->tofu_workspace,
                    'exit_code' => $result->exitCode,
                    'apply_seconds' => round($result->durationMs / 1000, 2),
                ],
            );

            return $environment;
        }

        $environment->update($attributes);

        $this->activity->record(
            actor: $actor,
            action: ActivityAction::completed($action),
            resource: $environment,
            status: 'completed',
            description: sprintf(
                '%s %s. Reported %s.',
                $this->verb($action),
                (string) $environment->container_name,
                $environment->runtime_status->value
            ),
            metadata: [
                'container_name' => (string) $environment->container_name,
                'workspace' => (string) $environment->tofu_workspace,
                'to_state' => $attributes['status']->value,
                'runtime_status' => $environment->runtime_status->value,
                'internal_ip' => $environment->internal_ip,
                'exit_code' => $result->exitCode,
                'apply_seconds' => round($result->durationMs / 1000, 2),
            ],
        );

        return $environment;
    }

    /**
     * Record a configuration step that ran between the infrastructure phases
     * of a create or a reset.
     */
    public function markConfiguring(?User $actor, LabEnvironment $environment): void
    {
        $environment->update(['status' => EnvironmentStatus::Configuring]);
    }

    public function fail(
        ?User $actor,
        LabEnvironment $environment,
        string $action,
        ProcessResult $result,
    ): LabEnvironment {
        // The message is sanitised before it is stored, because OpenTofu and
        // Ansible both print enough to leak a path or a token into a browser.
        $message = $result->summary();

        $environment->update([
            'status' => EnvironmentStatus::Error,
            'runtime_status' => RuntimeStatus::Unknown,
            'last_error' => mb_substr($message, 0, 500),
        ]);

        $this->activity->record(
            actor: $actor,
            action: ActivityAction::failed($action),
            resource: $environment,
            status: 'failed',
            description: $message,
            metadata: [
                'container_name' => (string) $environment->container_name,
                'workspace' => (string) $environment->tofu_workspace,
                'to_state' => EnvironmentStatus::Error->value,
                'error_code' => $result->errorCode ?? 'exit_'.$result->exitCode,
                'exit_code' => $result->exitCode,
                'command' => $result->commandLine(),
            ],
        );

        return $environment;
    }

    /**
     * Read-only reconciliation. Never overwrites the desired lifecycle status:
     * the portal's intent stays as it was and only the observed runtime is
     * refreshed, so a disagreement is visible rather than papered over.
     */
    public function reconcile(LabEnvironment $environment, ProcessResult $result): LabEnvironment
    {
        if (! $result->successful()) {
            $this->activity->record(
                actor: auth()->user(),
                action: ActivityAction::ReconciliationDetected,
                resource: $environment,
                status: 'failed',
                description: 'Could not read the actual state: '.$result->summary(120),
                metadata: [
                    'container_name' => (string) $environment->container_name,
                    'workspace' => (string) $environment->tofu_workspace,
                ],
            );

            return $environment;
        }

        $outputs = json_decode($result->stdout, true);
        $outputs = is_array($outputs) ? $outputs : [];
        $mismatchBefore = $environment->hasStateMismatch();

        $environment->update([
            'runtime_status' => $this->runtimeFromOutputs($outputs, null),
            'internal_ip' => $outputs['container_ipv4'] ?? $environment->internal_ip,
        ]);

        // A reconciliation that found a disagreement is recorded as detected
        // rather than completed, so filtering the log for problems does not
        // require reading the description to tell the two apart.
        $action = $mismatchBefore || $environment->hasStateMismatch()
            ? ActivityAction::ReconciliationDetected
            : ActivityAction::ReconciliationCompleted;

        $this->activity->record(
            actor: auth()->user(),
            action: $action,
            resource: $environment,
            status: 'completed',
            description: $mismatchBefore
                ? sprintf('The record said %s while the container reported %s.', $environment->status->value, $environment->runtime_status->value)
                : sprintf('Read the actual state: %s.', $environment->runtime_status->value),
            metadata: [
                'container_name' => (string) $environment->container_name,
                'workspace' => (string) $environment->tofu_workspace,
                'runtime_status' => $environment->runtime_status->value,
            ],
        );

        return $environment;
    }

    /**
     * OpenTofu reports an address as "eth0=10.20.0.202", so only the address
     * part is kept. Nothing is invented when the field is absent.
     */
    private function addressFromOutputs(string $raw): ?string
    {
        if (preg_match('/([0-9a-fA-F:.]+)$/', trim($raw), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $outputs
     */
    private function runtimeFromOutputs(array $outputs, ?string $action): RuntimeStatus
    {
        $status = $outputs['container_status'] ?? null;

        if (! is_string($status) || $status === '') {
            // No observation is not the same as stopped.
            return RuntimeStatus::Unknown;
        }

        return match (strtolower($status)) {
            'running' => RuntimeStatus::Running,
            'stopped' => RuntimeStatus::Stopped,
            'frozen' => RuntimeStatus::Frozen,
            default => RuntimeStatus::Unknown,
        };
    }

    private function verb(string $action): string
    {
        return match ($action) {
            'create' => 'Provisioned',
            'start' => 'Started',
            'stop' => 'Stopped',
            'reset' => 'Recreated',
            default => ucfirst($action),
        };
    }
}
