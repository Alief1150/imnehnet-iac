<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EnvironmentStatsOverview;
use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\RecentEnvironments;
use App\Support\Lucide;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The control plane's front page.
 *
 * Reads as an ordered brief rather than a grid of widgets: what exists, what
 * is running, what is wrong, and what happened most recently. An operator
 * opens this to answer those four questions, so they appear in that order.
 *
 * Every figure comes from the inventory record. Nothing here is invented: an
 * environment whose runtime has never been observed is counted as unknown
 * rather than being folded into stopped.
 */
final class Dashboard extends BaseDashboard
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'IMNEHNET DASHBOARD';
    }

    public function getSubheading(): ?string
    {
        return 'Cybersecurity Infrastructure Control Plane';
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('layout-dashboard');
    }

    protected static ?int $navigationSort = 1;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            EnvironmentStatsOverview::class,
            InfrastructureHealthOverview::class,
            RecentEnvironments::class,
            RecentActivity::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}
