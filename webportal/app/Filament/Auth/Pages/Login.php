<?php

namespace App\Filament\Auth\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The sign-in form for both panels.
 *
 * It is deliberately panel-blind about who may sign in.
 *
 * Filament's own check refuses an account that may not enter the current panel
 * and reports it as "these credentials do not match our records". For a student
 * who typed a correct password into /admin/login that is simply untrue: the
 * password was right and the address was wrong, and telling them otherwise sends
 * them off to reset a password that does not need resetting. So that check is
 * answered yes here, and panel access is enforced on every subsequent request by
 * RedirectToOwnPanel instead. Nothing is reachable that should not be; a student
 * who signs in at the administrative address is simply redirected to their own
 * panel before any page renders.
 *
 * The registration link is the other reason this class exists. Registration lives
 * at /login/regist rather than under /admin, so a student creating an account is
 * not made to look like they are reaching for an administrative task.
 */
class Login extends BaseLogin
{
    // IMNEHNET_LOGIN_CUSTOM_V5
    // Custom Blade presentation; native Filament authentication.
    protected string $view = 'imnehnet.auth.login';

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),

            Action::make('registerStudent')
                ->label('Register as a student')
                ->url(route('register'))
                ->link()
                ->color('gray'),
        ];
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        return ($panel = \Filament\Facades\Filament::getCurrentPanel()) !== null && $user instanceof \App\Models\User && $user->canAccessPanel($panel);
    }

    /**
     * The same line the registration form carries under its title.
     *
     * It is the subheading rather than part of the wordmark because the wordmark
     * also appears in the sidebar, where a second line would be clipped by the
     * logo box's fixed height.
     */
    public function getSubHeading(): string|Htmlable|null
    {
        return null;
    }
}
