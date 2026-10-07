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

    /**
 * No header widget.
     *
     * There was one — InfrastructureHealthOverview — and it rendered a second, complete
     * set of the same eleven probes directly above the page's own probe section. Two lists
     * of the same facts, in two visual languages, on one screen.
     *
     * It could not simply be restyled into agreement. It is a Filament StatsOverviewWidget,
     * so it inherits Filament's stat-card markup and its own icon set, while the rest of
     * this page is FlyonUI cards with Lucide icons. That is not a colour difference to
     * tune: the two widgets disagree structurally, which is also why the icons in it
     * rendered as bare markers — it asked for Heroicons in a project that ships Lucide.
     *
     * So the probes are shown once, here, and the widget is gone from this page. It is
     * still registered, because it is the dashboard's view of the same service, and two
     * pages answering the same question from one service is the arrangement worth
     * keeping; two answers on one screen is not.
     *
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [];
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

    /**
     * The Lucide icon for a probed component.
     *
     * Named here rather than carried on the health record, because the probe reports what
     * it found and the icon is a property of how this page presents it. Keeping the two
     * apart means a new probe gets a sensible default rather than no icon at all.
     *
     * The widget this page used to render at the top asked for `heroicon-*` names. This
     * project generates its icon set from Lucide, so those resolved to nothing and the
     * cards showed a bare marker where an icon should have been.
     *
     * @return string
     */
    public function probeIcon(string $key): string
    {
        return match ($key) {
            'iaas_mgmt' => 'server',
            'lab_env' => 'box',
            'incus' => 'layers',
            'database' => 'database',
            'prometheus' => 'activity',
            'grafana' => 'chart-bar',
            'blackbox' => 'radio',
            'opentofu' => 'boxes',
            'ansible' => 'workflow',
            'automation' => 'cog',
            'wazuh' => 'shield',
            default => 'circle',
        };
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('network');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'INFRASTRUCTURE';

    protected static ?int $navigationSort = 10;
}
