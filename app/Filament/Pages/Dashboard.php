<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Widgets\AdminDashboardOverview;
use App\Filament\Widgets\EnvironmentStatsOverview;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RecentEnvironments;
use App\Services\EnvironmentQuota;
use App\Support\CurrentPanel;
use App\Support\Lucide;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

final class Dashboard extends BaseDashboard
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        return auth()->user()?->isAdmin()
            ? 'Overview of your cybersecurity lab infrastructure'
            : 'Your cybersecurity lab workspace';
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
        if (auth()->user()?->isAdmin() === true) {
            return [
                AdminDashboardOverview::class,
            ];
        }

        return [
            EnvironmentStatsOverview::class,
            NeedsAttention::class,
            RecentEnvironments::class,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        // The period the figures below were drawn over, stated rather than offered as a
        // control.
        //
        // It is disabled on purpose. Every figure on this dashboard is a total or a
        // per-day count over a window the widget owns, and a control that looks like it
        // changes them would promise something the page does not do. What it does is say
        // how far back a total reaches, which is the thing a bare number never tells you:
        // three accounts over thirty days and three accounts over an hour are the same
        // figure and mean different things.
        if ($user->isAdmin()) {
            return [
                Action::make('dashboardPeriod')
                    ->label('Last 30 days')
                    ->icon(Lucide::icon('calendar-days'))
                    ->color('gray')
                    ->disabled(),
            ];
        }

        if (! $user->isActive()) {
            return [];
        }

        return [
            Action::make('createEnvironment')
                ->label('Create Lab')
                ->icon(Lucide::icon('plus'))
                ->visible(fn (): bool => app(EnvironmentQuota::class)->allowsAnother($user))
                ->url(fn (): string => EnvironmentResource::getUrl('create', panel: CurrentPanel::id())),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}
