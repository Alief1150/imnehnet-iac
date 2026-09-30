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

                Section::make('Lab Environment')
                    ->description('A student account exists before a lab is assigned. Provisioning is a separate action.')
                    ->columns(2)
                    ->schema([
                        // Read through the relation explicitly instead of
                        // relying on dot notation, so a student with no lab
                        // renders the placeholder instead of erroring.
                        TextEntry::make('lab_name')
                            ->label('Lab name')
                            ->state(fn (User $record): string => $record->lab?->name ?? 'No Lab Environment')
                            ->badge()
                            ->color(fn (User $record): string => $record->lab === null ? 'warning' : 'success'),
                        TextEntry::make('lab_container_name')
                            ->label('Container')
                            ->state(fn (User $record): string => $record->lab?->container_name ?? '—')
                            ->fontFamily('mono'),
                        TextEntry::make('lab_ip_address')
                            ->label('IP address')
                            ->state(fn (User $record): string => $record->lab?->ip_address ?? 'Not assigned')
                            ->fontFamily('mono'),
                        TextEntry::make('lab_ssh_port')
                            ->label('SSH port')
                            ->state(fn (User $record): string => $record->lab?->ssh_port !== null
                                ? (string) $record->lab->ssh_port
                                : 'Not published'),
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
