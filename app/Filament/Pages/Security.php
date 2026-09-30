<?php

namespace App\Filament\Pages;

use App\Services\SecurityMonitoringService;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

final class Security extends Page
{
    protected string $view = 'filament.pages.security';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function summary(): array
    {
        return app(SecurityMonitoringService::class)->summary();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('shield-check');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 30;
}
