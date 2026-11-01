<?php

namespace App\Enums;

use RuntimeException;

/**
 * Every action the audit log can record.
 *
 * The lifecycle actions used to be assembled by string concatenation at each
 * call site, which meant the vocabulary existed only in the shape of the code
 * that happened to run. A missing action could not be noticed by reading
 * anything, and could not be checked at all. Naming them here makes the list
 * exhaustive by construction and lets a test assert it.
 *
 * The three outcomes are the reason this is an enum rather than free text: every
 * operation is expected to be able to record that it was requested, that it
 * finished, and that it failed, and an operation that can only record two of
 * those three is a gap someone should see rather than infer.
 */
enum ActivityAction: string
{
    case CreateRequested = 'CREATE_REQUESTED';
    case CreateCompleted = 'CREATE_COMPLETED';
    case CreateFailed = 'CREATE_FAILED';

    case StartRequested = 'START_REQUESTED';
    case StartCompleted = 'START_COMPLETED';
    case StartFailed = 'START_FAILED';

    case StopRequested = 'STOP_REQUESTED';
    case StopCompleted = 'STOP_COMPLETED';
    case StopFailed = 'STOP_FAILED';

    case ResetRequested = 'RESET_REQUESTED';
    case ResetCompleted = 'RESET_COMPLETED';
    case ResetFailed = 'RESET_FAILED';

    case DeleteRequested = 'DELETE_REQUESTED';
    case DeleteCompleted = 'DELETE_COMPLETED';
    case DeleteFailed = 'DELETE_FAILED';

    case TerminalOpened = 'TERMINAL_OPENED';

    case ReconciliationDetected = 'RECONCILIATION_DETECTED';
    case ReconciliationCompleted = 'RECONCILIATION_COMPLETED';

    // Portal administration, outside the environment lifecycle. Present here so
    // that anything reading the audit log has one closed set to work with.
    // Distinct from CreateUser on purpose: an administrator making a student
    // account and a student arriving of their own accord are different events,
    // and an operator reading the trail should not have to open the record to
    // tell them apart.
    case Registered = 'REGISTERED';

    case Login = 'LOGIN';
    case CreateUser = 'CREATE_USER';
    case UpdateUser = 'UPDATE_USER';

    /**
     * The operations that go through the queued lifecycle, keyed by the short
     * name the driver and the jobs already use.
     *
     * @var array<string, string>
     */
    private const OPERATIONS = [
        'create' => 'Create',
        'start' => 'Start',
        'stop' => 'Stop',
        'reset' => 'Reset',
        'delete' => 'Delete',
    ];

    /**
     * @return list<string>
     */
    public static function operations(): array
    {
        return array_keys(self::OPERATIONS);
    }

    public static function requested(string $operation): self
    {
        return self::resolve($operation, 'Requested');
    }

    public static function completed(string $operation): self
    {
        return self::resolve($operation, 'Completed');
    }

    public static function failed(string $operation): self
    {
        return self::resolve($operation, 'Failed');
    }

    /**
     * An unknown operation is a programming error, not a runtime condition.
     *
     * Falling back to a generic action would quietly produce an audit trail
     * that cannot be filtered by operation, which is worse than refusing: the
     * failure would be discovered by whoever later goes looking for what
     * happened during an incident.
     */
    private static function resolve(string $operation, string $suffix): self
    {
        $prefix = self::OPERATIONS[$operation] ?? null;

        if ($prefix === null) {
            throw new RuntimeException(sprintf(
                'Unknown lifecycle operation "%s". Known operations: %s.',
                $operation,
                implode(', ', self::operations()),
            ));
        }

        // Matched by case name rather than by self::from(), which compares
        // against the backing value. The constructed name and the stored value
        // differ in case and separator, so self::from() would reject every one
        // of them and the lookup would have to be rewritten as a match statement.
        $name = $prefix.$suffix;

        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new RuntimeException(sprintf(
            'The activity action "%s" does not exist. This is a programming error, not a runtime condition.',
            $name,
        ));
    }

    /**
     * Whether this action reports an operation that did not succeed.
     */
    public function isFailure(): bool
    {
        return $this === self::CreateFailed
            || $this === self::StartFailed
            || $this === self::StopFailed
            || $this === self::ResetFailed
            || $this === self::DeleteFailed
            || $this === self::ReconciliationDetected;
    }

    /**
     * The lifecycle operation this action belongs to, or null for actions that
     * are not tied to one.
     */
    public function operation(): ?string
    {
        $prefix = $this->name;

        foreach (self::OPERATIONS as $short => $long) {
            if (str_starts_with($prefix, $long)) {
                return $short;
            }
        }

        return null;
    }

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }
}
