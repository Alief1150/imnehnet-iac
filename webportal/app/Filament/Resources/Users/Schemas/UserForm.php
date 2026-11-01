<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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

                DateTimePicker::make('email_verified_at')
                    ->label('Email verified at')
                    ->seconds(false),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    // Never required by itself. On create, leaving it blank
                    // makes the page generate a first-time password, so the
                    // only way this field is mandatory is when an operator has
                    // actually typed one and it fails validation.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank on create to generate a one-time password. It is shown once and never stored in plaintext.')
                    ->minLength(10),
            ]);
    }
}
