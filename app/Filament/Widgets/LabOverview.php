<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\MapsStatusColor;
use App\Models\Lab;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class LabOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    use MapsStatusColor;

    protected ?string $heading = 'Lab Overview';

    protected ?string $description = 'Every count below is read from the portal database. Container state itself is reported by the infrastructure widgets.';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'lg' => 4];

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $students = User::query()->where('role', 'student');
        $labs = Lab::query();

        return [
            Stat::make('Total Users', (clone $students)->count())
                ->description('Accounts with the student role')
                ->descriptionIcon('heroicon-m-users')
                ->icon('heroicon-m-user-group'),

            Stat::make('Users Without Lab', (clone $students)->doesntHave('lab')->count())
                ->description('Registered but not provisioned')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-m-user-plus')
                ->color('warning'),

            Stat::make('Total Labs', $labs->count())
                ->description('Lab environment records')
                ->descriptionIcon('heroicon-m-cube')
                ->icon('heroicon-m-cube'),

            Stat::make('Running', (clone $labs)->where('actual_state', 'running')->count())
                ->description('Containers reported as running')
                ->descriptionIcon('heroicon-m-play')
                ->icon('heroicon-m-play-circle')
                ->color('success'),

            Stat::make('Stopped', (clone $labs)->where('actual_state', 'stopped')->count())
                ->description('Containers currently stopped')
                ->descriptionIcon('heroicon-m-pause')
                ->icon('heroicon-m-pause-circle')
                ->color('gray'),

            Stat::make('Provisioning', (clone $labs)
                ->whereIn('operation_state', ['queued', 'running'])
                ->orWhere('status', ['PENDING', 'PROVISIONING'])
                ->count())
                ->description('Queued or in progress')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->icon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Errors', (clone $labs)
                ->where('operation_state', 'failed')
                ->orWhere('status', 'ERROR')
                ->count())
                ->description('Failed operations needing attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('State Mismatch', $this->mismatches())
                ->description('Desired state differs from actual state')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->icon('heroicon-m-arrows-right-left')
                ->color('warning'),
        ];
    }

    private function mismatches(): int
    {
        return Lab::query()
            ->whereNotNull('actual_state')
            ->whereColumn('desired_state', '!=', 'actual_state')
            ->whereNotIn('actual_state', ['absent', 'missing', 'unknown'])
            ->whereNotIn('operation_state', ['queued', 'running', 'failed'])
            ->count();
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
