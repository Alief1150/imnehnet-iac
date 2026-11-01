<?php

namespace App\Support;

use Filament\Facades\Filament;

/**
 * Which panel a link should point at.
 *
 * There are two panels, one per role, so a URL built for one of them is wrong for
 * everybody using the other. Hard-coding 'admin' put a student who created a lab
 * on the create page and then redirected them back to an administrative page.
 *
 * Inside a request there is always a current panel and that is the answer. The
 * fallback exists for tests and for console callers, and it follows the viewer
 * rather than guessing at the administrator's panel.
 */
final class CurrentPanel
{
    public static function id(): string
    {
        $current = Filament::getCurrentPanel();

        if ($current !== null) {
            return $current->getId();
        }

        return auth()->user()?->isAdmin() === true ? 'admin' : 'student';
    }
}