<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InfrastructureHealthOverview;
use App\Services\InfrastructureStatusService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class Infrastructure extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Infrastructure';

    protected static ?int $navigationSort = 10;

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
}
