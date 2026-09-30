<?php

namespace App\Enums;

/**
 * Where an environment is in its provisioning lifecycle.
 *
 * This is deliberately separate from RuntimeStatus. An environment can be
 * READY in the lifecycle sense while the container is still stopped, and it
 * can be mid-RESETTING while the container happens to be running. Collapsing
 * the two would make "ready" mean something different on every screen.
 */
enum EnvironmentStatus: string
{
    case Provisioning = 'PROVISIONING';
    case Configuring = 'CONFIGURING';
    case Ready = 'READY';
    case Stopped = 'STOPPED';
    case Resetting = 'RESETTING';
    case Deleting = 'DELETING';
    case Error = 'ERROR';

    /**
     * Lifecycle states where an operation is still running. Actions that would
     * conflict with an in-flight operation are hidden for these.
     */
    public function isInFlight(): bool
    {
        return match ($this) {
            self::Provisioning, self::Configuring, self::Resetting, self::Deleting => true,
            self::Ready, self::Stopped, self::Error => false,
        };
    }

    /**
     * A terminal state is one the environment settles into until an operator
     * acts. Everything else is transient.
     */
    public function isSettled(): bool
    {
        return ! $this->isInFlight();
    }

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::Configuring => 'Configuring',
            self::Ready => 'Ready',
            self::Stopped => 'Stopped',
            self::Resetting => 'Resetting',
            self::Deleting => 'Deleting',
            self::Error => 'Error',
        };
    }

    /**
     * Colour token used by the Filament badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Provisioning => 'info',
            self::Configuring => 'warning',
            self::Ready => 'success',
            self::Stopped => 'gray',
            self::Resetting => 'orange',
            self::Deleting => 'danger',
            self::Error => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Provisioning, self::Configuring => 'lucide-loader-circle',
            self::Ready => 'lucide-circle-check',
            self::Stopped => 'lucide-square',
            self::Resetting => 'lucide-rotate-ccw',
            self::Deleting => 'lucide-trash-2',
            self::Error => 'lucide-circle-alert',
        };
    }
}
