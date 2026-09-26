<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
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
                            ->color(fn (string $state): string => $state === 'admin' ? 'teal' : 'gray'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'disabled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('last_login_at')
                            ->label('Last login')
                            ->since()
                            ->placeholder('Never signed in'),
                        TextEntry::make('must_change_password')
                            ->label('Password state')
                            ->formatStateUsing(fn (bool $state): string => $state
                                ? 'A first-time password is still in use'
                                : 'Password has been set by the user')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                    ]),

                Section::make('Lab Environment')
                    ->description('A student account exists before a lab is assigned. Provisioning is a separate action.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lab.name')
                            ->label('Lab name')
                            ->placeholder('No Lab Environment')
                            ->badge(fn (User $record): bool => $record->lab === null)
                            ->color(fn (User $record): string => $record->lab ? 'success' : 'warning'),
                        TextEntry::make('lab.container_name')
                            ->label('Container')
                            ->placeholder('—')
                            ->fontFamily('mono'),
                        TextEntry::make('lab.ip_address')
                            ->label('IP address')
                            ->placeholder('Not assigned')
                            ->fontFamily('mono'),
                        TextEntry::make('lab.ssh_port')
                            ->label('SSH port')
                            ->placeholder('Not published'),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Updated')->dateTime(),
                    ]),
            ]);
    }
}
