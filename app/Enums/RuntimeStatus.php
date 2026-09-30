<?php

namespace App\Enums;

use App\Support\Lucide;
use Illuminate\Contracts\Support\Htmlable;

/**
 * What the container is actually doing right now.
 *
 * Read from the hypervisor, never from a request. When it is not known the
 * value is Unknown rather than a guess, so a disconnected control plane cannot
 * make a stopped container look healthy.
 */
enum RuntimeStatus: string
{
    case Running = 'Running';
    case Stopped = 'Stopped';
    case Frozen = 'Frozen';
    case Unknown = 'Unknown';

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Frozen => 'Frozen',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Stopped, self::Frozen => 'gray',
            self::Unknown => 'gray',
        };
    }

    public function icon(): ?Htmlable
    {
        return match ($this) {
            self::Running => Lucide::icon('circle-check'),
            self::Stopped => Lucide::icon('circle-pause'),
            self::Frozen => Lucide::icon('snowflake'),
            self::Unknown => Lucide::icon('circle-question-mark'),
        };
    }
}
