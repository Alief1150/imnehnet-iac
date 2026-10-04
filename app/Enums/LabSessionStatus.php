<?php

namespace App\Enums;

/**
 * Lifecycle of one guided lab session.
 *
 * The specification fixes this list, including the two that are not linear:
 * RESETTING is a transition and FAILED is reachable from any point in the
 * sequence. Both are here because the interface has to be able to say what is
 * happening, and a state the interface cannot name is a state it will describe
 * as "something went wrong".
 */
enum LabSessionStatus: string
{
    /** The record exists. Nothing has been asked of the infrastructure yet. */
    case Creating = 'CREATING';

    /** Queued. Containers are being created on the two compute planes. */
    case Provisioning = 'PROVISIONING';

    /** The containers exist and the scenario is being applied to them. */
    case Configuring = 'CONFIGURING';

    /** Both containers are up and paired. The terminal can be opened. */
    case Ready = 'READY';

    /** A terminal session is open. Distinct from Ready because it is observable. */
    case Running = 'RUNNING';

    /** Every objective is completed. The session stays readable afterwards. */
    case Completed = 'COMPLETED';

    /** Provisioning or configuration did not finish. last_error says why. */
    case Failed = 'FAILED';

    /** The containers are stopped on purpose. The session is not over. */
    case Stopped = 'STOPPED';

    /** Being returned to its initial state. Objective progress is kept. */
    case Resetting = 'RESETTING';

    /** Being removed. */
    case Deleting = 'DELETING';

    /**
     * States in which a shell may be opened against the attacker container.
     *
     * Ready is included because a session that is ready and has no open terminal
     * is still perfectly usable. Running is the same thing after a terminal has
     * been opened, so the two are treated alike here rather than making every
     * caller work out which one it is looking at.
     */
    public function allowsTerminal(): bool
    {
        return $this === self::Ready || $this === self::Running;
    }

    /**
     * States in which the session still exists and should be listed.
     *
     * Deleting is absent on purpose: once teardown has begun the record is on its
     * way out, and showing it as a lab the student can open is worse than
     * showing it for a moment too few.
     */
    public function isListed(): bool
    {
        return $this !== self::Deleting;
    }

    /**
     * Whether the interface should offer to provision, start or reset.
     *
     * A failed session can be retried, a stopped one can be started, and a
     * completed one can still be reset for practice. A session that is mid-flight
     * can do none of those, because asking would queue a second operation behind
     * the first and the result would be two operations racing for one workspace.
     */
    public function acceptsOperation(): bool
    {
        return match ($this) {
            self::Creating,
            self::Provisioning,
            self::Configuring,
            self::Resetting,
            self::Deleting => false,
            default => true,
        };
    }
}