<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class MyLabOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'My Lab Environment';

    protected function getStats(): array
    {
        $lab = auth()->user()?->lab;

        if (! $lab) {
            return [
                Stat::make('Environment', 'Not provisioned')
                    ->description('No Lab Environment has been provisioned yet')
                    ->color('gray'),
            ];
        }

        return [
            Stat::make('Desired State', strtoupper($lab->desired_state->value)),
            Stat::make('Actual State', strtoupper($lab->actual_state->value)),
            Stat::make('Operation', strtoupper($lab->operation_state->value)),
        ];
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isActive() === true && ! $user->isAdmin();
    }
}
