<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ReportsHealthFor;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The compute plane: LAB-ENV.
 *
 * Shown separately from IAAS-MGMT because the two fail independently. Everything
 * below this page is driven through Incus, so a stopped container here and a
 * stopped web server on the management plane look nothing alike from the
 * dashboard and need completely different responses.
 */
final class ComputePlane extends Page
{
    use ReportsHealthFor;

    protected string $view = 'filament.pages.plane';

    protected static ?string $title = 'LAB-ENV';

    protected static ?string $navigationLabel = 'LAB-ENV';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('cpu');
    }

    /**
     * The compute plane's own probe plus the hypervisor. Ansible is left out
     * because it runs on the management plane and talks to this host, so it is
     * evidence about the management plane's view of here rather than about here.
     *
     * @return list<string>
     */
    protected function healthGroups(): array
    {
        return ['compute_plane', 'infrastructure'];
    }

    protected function healthHeading(): string
    {
        return 'LAB-ENV — compute plane';
    }

    protected function healthDescription(): string
    {
        return 'The hypervisor that runs every student container. Never the student lab itself.';
    }
}
