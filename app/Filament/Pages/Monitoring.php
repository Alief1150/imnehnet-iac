<?php

namespace App\Filament\Pages;

use App\Services\InfrastructureStatusService;
use App\Services\MonitoringService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;
use UnitEnum;

final class Monitoring extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Monitoring';

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.monitoring';

    /**
     * @return array<string, array<string, mixed>>
     */
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
}
