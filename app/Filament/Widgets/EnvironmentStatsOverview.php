<?php

namespace App\Filament\Widgets;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
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
    protected ?string $heading = 'Environments';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $total = LabEnvironment::query()->count();

        $running = LabEnvironment::query()
            ->where('runtime_status', RuntimeStatus::Running->value)
            ->count();

        $ready = LabEnvironment::query()
            ->where('status', EnvironmentStatus::Ready->value)
            ->count();

        $stopped = LabEnvironment::query()
            ->where('status', EnvironmentStatus::Stopped->value)
            ->count();

        $errors = LabEnvironment::query()
            ->where('status', EnvironmentStatus::Error->value)
            ->count();

        $inFlight = LabEnvironment::query()
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
