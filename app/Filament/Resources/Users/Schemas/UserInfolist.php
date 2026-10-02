<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Name'),
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('role')
                            ->label('Role')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper(self::asString($state)))
                            ->color(fn (User $record): string => $record->isAdmin() ? 'teal' : 'gray'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper(self::asString($state)))
                            ->color(fn (User $record): string => $record->isActive() ? 'success' : 'danger'),
                        TextEntry::make('last_login_at')
                            ->label('Last login')
                            ->since()
                            ->placeholder('Never signed in'),
                        TextEntry::make('must_change_password')
                            ->label('Password state')
                            ->formatStateUsing(fn (mixed $state): string => $state
                                ? 'A first-time password is still in use'
                                : 'Password has been set by the user')
                            ->badge()
                            ->color(fn (mixed $state): string => $state ? 'warning' : 'success'),
                    ]),
                Section::make('Environments')
                    ->description('A student account may exist before any environment is provisioned for it.')
                    ->columns(2)
                    ->schema([
                        // Read through the relation rather than dot notation on a
                        // single lab: an account can own several, and an account
                        // that owns none has to render a placeholder rather than
                        // an error.
                        TextEntry::make('environments_count')
                            ->label('Environments')
                            ->state(fn (User $record): string => (string) $record->environments()->count())
                            ->badge(),
                        TextEntry::make('environments_summary')
                            ->label('Containers')
                            ->state(fn (User $record): string => $record->environments()->count() === 0
                                ? 'None provisioned'
                                : $record->environments()->pluck('container_name')->implode(', '))
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Updated')->dateTime(),
                    ]),
            ]);
    }

    private static function asString(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (is_scalar($state) ? (string) $state : '');
    }
}
