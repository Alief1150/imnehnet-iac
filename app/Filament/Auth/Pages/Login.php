<?php

namespace App\Filament\Auth\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * The panel sign-in, with a way to register.
 *
 * Registration lives at /login/regist rather than under /admin, because a
 * student creating an account is not an administrator doing an administrative
 * task and should not appear to reach for one. This adds the only link to it,
 * so the form is reachable from where a first-time user actually starts.
 */
class Login extends BaseLogin
{
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
}
