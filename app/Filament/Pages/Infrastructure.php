<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Services\InfrastructureStatusService;
use App\Services\InfrastructureTopologyService;
use App\Support\Lucide;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

final class Infrastructure extends Page
{
    protected string $view = 'filament.pages.infrastructure';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [InfrastructureHealthOverview::class];
    }

    /**
     * The component probe verdicts, unchanged in meaning.
     *
     * Read from the service the dashboard reads, so the two pages cannot disagree about
     * whether something is up.
     *
     * @return array<string, array<string, mixed>>
     */
    public function statusSummary(): array
    {
        return app(InfrastructureStatusService::class)->summary();
    }

    /**
     * The planes, hosts, network segments and provisioning stack.
     *
     * Collected here rather than passed to the view as data so that the service stays the
     * only place that knows how any of it is measured. The template holds no constants:
     * a page whose job is to describe this installation must not carry its own
     * description of it as a literal, because the installation changes and the literal
     * would not.
     *
     * @return array<string, mixed>
     */
    public function topology(): array
    {
        return app(InfrastructureTopologyService::class)->topology();
    }

    /**
     * When the figures above were read.
     *
     * Stated rather than implied. A topology page with no timestamp reads as current even
     * though it was assembled from probes that each have their own age, and an operator
     * comparing a plane card to a `ping` needs to know which is older.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('lastCollected')
                ->label(fn (): string => 'Measured '.(
                    \Illuminate\Support\Carbon::parse($this->topology()['collectedAt'] ?? null)
                        ->diffForHumans()
                ))
                ->icon(Lucide::icon('clock'))
                ->color('gray')
                ->disabled(),
        ];
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('network');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'INFRASTRUCTURE';

    protected static ?int $navigationSort = 10;
}
