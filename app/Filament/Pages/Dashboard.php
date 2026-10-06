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
            ? 'Cybersecurity lab orchestration and guided CTF control center'
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

        if ($user === null || ! $user->isActive() || $user->isAdmin()) {
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
