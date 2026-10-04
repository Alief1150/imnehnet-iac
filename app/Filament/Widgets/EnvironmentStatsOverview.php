<?php

namespace App\Filament\Widgets;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Filament\Resources\Environments\EnvironmentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The five figures the specification puts at the top of the dashboard.
 *
 * Errors are counted by lifecycle status and running by runtime status,
 * because those are two different questions. An environment whose container is
 * stopped but which is ready in the lifecycle sense is ready, not errored, so
 * the two counts do not have to add up to the total.
 *
 * No icon is set on these tiles. A stat icon has to be a name the icon
 * registry can resolve, and the generated Lucide set is passed to components
 * as markup because Filament resolves names through a different registry. A
 * missing icon is better than an icon that means the wrong thing, and the
 * colour plus the label already carry the meaning.
 */
final class EnvironmentStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Environments';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        // Counted through the resource query, not the model, so the figures
        // describe the environments this viewer is allowed to see. Counting the
        // model directly told a student how many environments other students own,
        // which is precisely what the ownership rules keep to each owner. Cloning
        // keeps the per-figure wheres from stacking onto one query.
        $visible = EnvironmentResource::getEloquentQuery();

        $total = (clone $visible)->count();

        $running = (clone $visible)
            ->where('runtime_status', RuntimeStatus::Running->value)
            ->count();

        $ready = (clone $visible)
            ->where('status', EnvironmentStatus::Ready->value)
            ->count();

        $stopped = (clone $visible)
            ->where('status', EnvironmentStatus::Stopped->value)
            ->count();

        $errors = (clone $visible)
            ->where('status', EnvironmentStatus::Error->value)
            ->count();

        $inFlight = (clone $visible)
            ->whereIn('status', [
                EnvironmentStatus::Provisioning->value,
                EnvironmentStatus::Configuring->value,
                EnvironmentStatus::Resetting->value,
                EnvironmentStatus::Deleting->value,
            ])
            ->count();

        return [
            Stat::make('Total Environments', $total)
                ->description($inFlight > 0 ? sprintf('%d in progress', $inFlight) : 'No operations in progress')
                ->descriptionIcon($inFlight > 0 ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                ->color('gray'),

            Stat::make('Running', $running)
                ->description('Observed on the compute plane')
                ->descriptionIcon('heroicon-o-play')
                ->color('success'),

            Stat::make('Ready', $ready)
                ->description('Provisioned and settled')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Stopped', $stopped)
                ->description('Deliberately stopped')
                ->descriptionIcon('heroicon-o-pause-circle')
                ->color('gray'),

            Stat::make('Errors', $errors)
                ->description($errors > 0 ? 'Needs an operator' : 'None')
                ->descriptionIcon($errors > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($errors > 0 ? 'danger' : 'gray'),
        ];
    }
}
