<?php

namespace App\Filament\Concerns;

use Filament\Support\Colors\Color;

/**
 * Maps the status vocabulary defined in the specification
 * (healthy / degraded / offline / unknown / not_configured)
 * onto the Filament colour palette.
 */
trait MapsStatusColor
{
    protected function statusColor(?string $status): string
    {
        return match ($status) {
            'healthy' => 'success',
            'degraded' => 'warning',
            'offline' => 'danger',
            'not_configured', 'not_connected' => 'gray',
            default => 'gray',
        };
    }

    protected function statusBadgeColor(?string $status): string
    {
        return match ($status) {
            'healthy' => Color::Emerald,
            'degraded' => Color::Amber,
            'offline' => Color::Rose,
            'not_configured', 'not_connected' => Color::Slate,
            default => Color::Slate,
        };
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'healthy' => 'Healthy',
            'degraded' => 'Degraded',
            'offline' => 'Offline',
            'not_configured' => 'Not Configured',
            'not_connected' => 'Not Connected',
            'unknown' => 'Unknown',
            default => ucfirst((string) $status),
        };
    }
}
