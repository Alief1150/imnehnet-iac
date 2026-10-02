<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ReportsHealthFor;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The management plane: this host.
 *
 * Shown separately from LAB-ENV because "the portal is down" and "the lab host
 * is down" are different incidents with different responses, and an operator
 * should not have to work out which one they are looking at from a single grid.
 */
final class ManagementPlane extends Page
{
    use ReportsHealthFor;

    protected string $view = 'filament.pages.plane';

    protected static ?string $title = 'IAAS-MGMT';

    protected static ?string $navigationLabel = 'IAAS-MGMT';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('server');
    }

    /**
     * The management plane and the tools that run on it. The compute plane's own
     * probe is deliberately absent: it answers a question about another host,
     * and repeating it here would blur the distinction this page exists to make.
     *
     * @return list<string>
     */
    protected function healthGroups(): array
    {
        return ['management_plane', 'infrastructure', 'automation'];
    }

    protected function healthHeading(): string
    {
        return 'IAAS-MGMT — management plane';
    }

    protected function healthDescription(): string
    {
        return 'This host. The portal, its database, and the automation that drives the compute plane.';
    }
}
