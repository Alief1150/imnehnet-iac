<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Services\InfrastructureStatusService;
use App\Support\Lucide;
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

    /** @return array<string, array<string, mixed>> */
    public function statusSummary(): array
    {
        return app(InfrastructureStatusService::class)->summary();
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('network');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 10;
}
