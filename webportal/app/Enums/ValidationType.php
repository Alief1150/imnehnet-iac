<?php

namespace App\Enums;

/**
 * How an objective is proven.
 *
 * The specification is explicit that objectives one to five are settled by what
 * happened on the machines, not by what the student typed. Command matching is
 * named and rejected: it rewards memorising a string and punishes anyone who
 * solves the problem a different way.
 */
enum ValidationType: string
{
    /** Proven by an event or condition observed on the victim's own records. */
    case Event = 'event';

    /** Proven by a submitted flag, compared server side against the victim's copy. */
    case Flag = 'flag';
}