<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyLabOverview;
use App\Models\Lab;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class MyLab extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'My Lab';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.my-lab';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isActive() === true && ! $user->isAdmin();
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [MyLabOverview::class];
    }

    public function lab(): ?Lab
    {
        return auth()->user()?->lab()->with('operations')->first();
    }
}
