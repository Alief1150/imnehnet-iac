<?php

namespace App\Enums;

/**
 * What the container is actually doing right now.
 *
 * Read from the hypervisor, never from a request. When it is not known the
 * value is Unknown rather than a guess, so a disconnected control plane
 * cannot make a stopped container look healthy.
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

    public function icon(): string
    {
        return match ($this) {
            self::Running => 'lucide-circle-check',
            self::Stopped => 'lucide-circle-pause',
            self::Frozen => 'lucide-snowflake',
            self::Unknown => 'lucide-circle-help',
        };
    }
}
