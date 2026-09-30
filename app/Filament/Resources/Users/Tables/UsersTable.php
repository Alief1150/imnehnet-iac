<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
                    // The column is cast to a UserRole enum, so the value is
                    // unwrapped explicitly rather than assumed to be a string.
                    ->formatStateUsing(fn (mixed $state): string => self::asString($state))
                    ->color(fn (User $record): string => $record->isAdmin() ? 'teal' : 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::asString($state))
                    ->color(fn (User $record): string => $record->isActive() ? 'success' : 'danger')
                    ->sortable(),

                // A computed column, so it is deliberately not sortable or
                // searchable: there is no matching database column to query.
                TextColumn::make('lab_container_name')
                    ->label('Lab Environment')
                    ->state(fn (User $record): string => $record->lab?->container_name ?? 'No Lab Environment')
                    ->badge()
                    ->color(fn (User $record): string => $record->lab === null ? 'warning' : 'success')
                    ->toggleable(),

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

    /**
     * Badge labels are rendered uppercase, so a role reads as ADMIN or STUDENT
     * rather than Admin. Enum and null states are both handled.
     */
    private static function asString(mixed $state): string
    {
        $value = $state instanceof \BackedEnum ? (string) $state->value : (is_scalar($state) ? (string) $state : '');

        return strtoupper($value);
    }
}
