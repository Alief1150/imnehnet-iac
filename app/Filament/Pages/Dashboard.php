<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Filament\Widgets\LabOverview;
use App\Filament\Widgets\MyLabOverview;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\ResourceUsageOverview;
use App\Filament\Widgets\SecurityOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

final class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected int|string|array $columnSpan = 'full';

    /**
     * The control plane reads better as an ordered brief than as an undifferentiated
     * widget grid, so the sections follow the order an operator triages in:
     * planes first, then capacity, then the labs being served, then security,
     * then the audit trail.
     *
     * Each widget spans the full width and lays its own cards out internally, so
     * the dashboard never ends up with a half-empty column beside a tall stack.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        if (auth()->user()?->isAdmin() !== true) {
            return [
                MyLabOverview::class,
                RecentActivity::class,
            ];
        }

        return [
            InfrastructureHealthOverview::class,
            ResourceUsageOverview::class,
            LabOverview::class,
            SecurityOverview::class,
            RecentActivity::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 1,
        ];
    }
}
