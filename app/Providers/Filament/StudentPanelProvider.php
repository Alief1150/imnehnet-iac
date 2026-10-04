<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GuidedCtf;
use App\Filament\Pages\Terminal;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Http\Middleware\RedirectToOwnPanel;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The student's own panel, at /student.
 *
 * This exists because a student arriving after registration was being dropped
 * into /admin, which said two things at once: that student accounts are
 * administrators, and that the administrative navigation is theirs. Neither is
 * true. They get their own address, their own login, and only what a student
 * needs.
 *
 * Resources are listed rather than discovered. Discovery would pull in the Users
 * resource, the Activity Logs and the infrastructure pages, each of which carries
 * its own guard but which should not be reachable from here at all: the page not
 * existing is a stronger answer than the page existing and refusing.
 *
 * The colours, theme and layout are the administrator's, deliberately. A student
 * who opens /student must not feel they have been sent somewhere else, and there
 * is nothing in this panel a different palette would communicate.
 */
class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('student')
            ->path('student')
            ->brandName('IMNEHNET')
            // The same wordmark on both panels and on the registration form,
            // so the name is written down in exactly one place.
            ->brandLogo(\App\Support\Wordmark::make())
            ->favicon('favicon.ico')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Auth\Pages\Login::class)
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->maxContentWidth('full')
            // Named rather than discovered. A new administrative resource must not
            // appear in the student's panel by being added to a directory.
            ->resources([
                EnvironmentResource::class,
            ])
            ->pages([
                Dashboard::class,
                // The guided lab comes before the terminal: it is where a student goes
                // to start work, and the terminal is reached from there.
                GuidedCtf::class,
                Terminal::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Not Filament's Authenticate but a subclass of it, so that an account
            // which may not enter this panel is redirected to the one it may,
            // rather than answered with 403. Runs after the panel's middleware and
            // therefore after the session, which is the only point where the
            // signed-in user is readable.
            ->authMiddleware([
                RedirectToOwnPanel::class,
            ]);
    }
}