<?php

namespace App\Filament\Widgets;

use App\Services\InfrastructureStatusService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class InfrastructureHealthOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Infrastructure Health';

    protected function getStats(): array
    {
        $summary = app(InfrastructureStatusService::class)->summary();

        return collect($summary)
            ->map(fn (array $service, string $key): Stat => Stat::make($service['label'] ?? ucfirst($key), strtoupper($service['status']))
                ->description($service['summary'])
                ->color(match ($service['status']) {
                    'healthy' => 'success',
                    'degraded' => 'warning',
                    'offline' => 'danger',
                    default => 'gray',
                }))
            ->values()
            ->all();
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
