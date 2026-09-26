<?php

namespace App\Filament\Pages;

use App\Services\SecurityMonitoringService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Security extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Security';

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 30;

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
}
