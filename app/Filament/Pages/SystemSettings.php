<?php

namespace App\Filament\Pages;

use App\Support\Lucide;
use Composer\InstalledVersions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

final class SystemSettings extends Page
{
    protected static ?string $navigationLabel = 'System Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.system-settings';

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    public function getSubheading(): ?string
    {
        return 'Runtime configuration and platform information';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'application' => [
                'Application' => config('app.name', 'IMNEHNET'),
                'Environment' => app()->environment(),
                'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
                'Timezone' => config('app.timezone'),
            ],
            'platform' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Filament' => InstalledVersions::getPrettyVersion('filament/filament') ?? 'Unknown',
            ],
        ];
    }
}
