<?php

namespace App\Filament\Pages;

use App\Services\IncusInventoryMonitoringService;
use App\Services\InfrastructureStatusService;
use App\Services\MonitoringService;
use App\Services\MonitoringVmSeriesService;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Throwable;

final class Monitoring extends Page
{
    protected string $view = 'filament.pages.monitoring';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function hostDetails(): array
    {
        return app(\App\Services\MonitoringHostDetailsService::class)->summary();
    }

    public function physicalHost(): array

    {

        return app(\App\Services\MonitoringHostService::class)->summary();

    }



    public function resources(): array
    {
        return app(MonitoringService::class)->summary();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function trends(): array
    {
        return app(MonitoringService::class)->trend();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function health(): array
    {
        return app(InfrastructureStatusService::class)->summary();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backends(): array
    {
        return app(MonitoringService::class)->backends();
    }

    /** @return array<string, mixed> */
    public function containerInventory(): array
    {
        return app(IncusInventoryMonitoringService::class)->summary();
    }

    /** @return array<string, mixed> */
    public function vmSeries(): array
    {
        return app(MonitoringVmSeriesService::class)->series();
    }

    public function isPolling(): bool
    {
        return true;
    }

    public function refreshedAt(): ?Carbon
    {
        try {
            $checked = collect($this->health())->max('checked_at');

            return is_string($checked) ? Carbon::parse($checked) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('gauge');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'INFRASTRUCTURE';

    protected static ?int $navigationSort = 20;
}
