<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RedirectToOwnPanel;
use App\Support\Wordmark;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            ->brandName('IMNEHNET')
            // The same wordmark on both panels and on the registration form,
            // so the name is written down in exactly one place.
            ->brandLogo(Wordmark::make())
            ->favicon('favicon.ico')

            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                'panels::body.end',
                fn (): string => Vite::withEntryPoints(['resources/js/app.js'])->toHtml(),
            )

            ->login(Login::class)

            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])

            ->defaultThemeMode(ThemeMode::Light)
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()

            // No theme toggle in the topbar.
            //
            // There were two: this one, pinned next to the search field, and the one
            // Filament's AccountWidget already puts in the profile menu. Two controls for
            // one setting is worse than either alone — a reader cannot tell whether they
            // do the same thing, and they write to the same key, so the one that looks
            // "wrong" is simply whichever was not clicked last.
            //
            // The profile menu's is kept. It is where a person expects their own
            // preferences to live, and it is the control Filament's own markup keeps in
            // step with the theme it renders. See also StudentPanelProvider, which never
            // had the duplicate.
            ->databaseNotifications()
            ->navigationGroups([
                'INFRASTRUCTURE',
                'LAB MANAGEMENT',
                'USERS',
                'GUIDED CTF',
                'OPERATIONS',
            ])
            ->maxContentWidth('full')

            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )

            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )

            ->pages([
                Dashboard::class,
            ])

            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )

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
