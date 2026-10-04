<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Widgets\EnvironmentStatsOverview;
use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\PeopleOverview;
use App\Filament\Widgets\RecentEnvironments;
use App\Services\EnvironmentQuota;
use App\Support\Lucide;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The control plane's front page.
 *
 * Reads as an ordered brief rather than a grid of widgets: what exists, what
 * needs doing now, who is using it, whether the plane is healthy, and what this
 * viewer's own labs are doing. An operator opens this to answer those in order,
 * so they appear in that order.
 *
 * A Recent Activity table used to sit at the bottom. It was the Activity Logs
 * page repeated in a smaller box, including a second search field for the same
 * records, and answering a question about history that the page answers better.
 * Needs attention took its place, because that question was not being asked
 * anywhere.
 *
 * Every figure comes from a real observation. Nothing here is invented: an
 * environment whose runtime has never been observed is counted as unknown rather
 * than folded into stopped, and the compute plane is reported as not verified
 * rather than as empty when it cannot be reached.
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
            NeedsAttention::class,
            PeopleOverview::class,
            InfrastructureHealthOverview::class,
            RecentEnvironments::class,
        ];
    }

    /**
     * The two things a student came here to do.
     *
     * Both live behind a sidebar item otherwise, and the point of a landing page
     * for someone who is here to practise is that the first move is one click
     * rather than a hunt through the navigation.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->isActive()) {
            return [];
        }

        return [
            Action::make('createEnvironment')
                ->label('Create Lab')
                ->icon(Lucide::icon('plus'))
                // Hidden rather than disabled once the quota is reached: a button
                // that is visibly there and does nothing is a worse experience
                // than one that is honestly absent, and the Environments page
                // states the reason where the count is visible.
                ->visible(fn (): bool => app(EnvironmentQuota::class)->allowsAnother($user))
                ->url(fn (): string => EnvironmentResource::getUrl('create', panel: 'admin')),
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