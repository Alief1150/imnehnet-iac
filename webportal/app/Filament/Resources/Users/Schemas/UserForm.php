<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->autocomplete('off')
                    ->default('')
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Admin->value => 'Administrator',
                        UserRole::Student->value => 'Student',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Administrators may provision labs, view every lab and change infrastructure settings.'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        UserStatus::Active->value => 'Active',
                        UserStatus::Disabled->value => 'Disabled',
                    ])
                    ->required()
                    ->native(false)
                    ->default(UserStatus::Active->value)
                    ->helperText('A disabled account cannot sign in and cannot be provisioned a lab.'),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->autocomplete('new-password')
                    ->default('')
                    ->revealable()
                    // Never required by itself. On create, leaving it blank
                    // makes the page generate a first-time password, so the
                    // only way this field is mandatory is when an operator has
                    // actually typed one and it fails validation.
                    ->visibleOn('create')
                    ->dehydrated(fn (?string $state, string $operation): bool => $operation === 'create' && filled($state))
                    ->helperText('Leave blank on create to generate a one-time password. It is shown once and never stored in plaintext.')
                    ->live(debounce: 350)
                    ->rules([Password::min(8)->mixedCase()->numbers()->symbols()])
                    ->belowContent(fn (Get $get) => new HtmlString(
                        view('filament.pages.partials.admin-password-rules', [
                            'password' => (string) ($get('password') ?? ''),
                        ])->render()
                    )),
            ]);
    }
}
