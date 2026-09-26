<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (BackedEnum $state): string => $state instanceof BackedEnum
                        ? ucfirst($state->value)
                        : (string) $state)
                    ->color(fn (string $state): string => $state === 'admin' ? 'teal' : 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'disabled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('lab')
                    ->label('Lab Environment')
                    // Mirrors the initial state in the specification: an
                    // account exists before it is assigned a lab.
                    ->state(fn (User $record): string => $record->lab
                        ? $record->lab->container_name
                        : 'No Lab Environment')
                    ->badge()
                    ->color(fn (User $record): string => $record->lab ? 'success' : 'warning')
                    ->searchable(),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('Never signed in')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('must_change_password')
                    ->label('Must set password')
                    ->boolean()
                    ->tooltip('The account still uses a first-time password')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Admin->value => 'Administrator',
                        UserRole::Student->value => 'Student',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        UserStatus::Active->value => 'Active',
                        UserStatus::Disabled->value => 'Disabled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
