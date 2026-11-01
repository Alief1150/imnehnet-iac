<?php

namespace App\Enums;

/**
 * State of one objective within one lab session.
 *
 * Three states, and the transition between them is strictly sequential: an
 * objective becomes active only when the one before it is completed. That is the
 * whole of the progression rule, and it is why there is no "skipped" state to
 * handle.
 */
enum ObjectiveStatus: string
{
    /** Not yet available. The interface shows it, without its detail. */
    case Locked = 'LOCKED';

    /** The objective the student is working on. Exactly one at a time. */
    case Active = 'ACTIVE';

    /** Proven done. The proof is recorded, not the claim. */
    case Completed = 'COMPLETED';

    /** Whether the interface may show this objective's description and hint. */
    public function revealsDetail(): bool
    {
        return $this !== self::Locked;
    }
}