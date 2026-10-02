<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('environments_summary')
                    ->label('Environments')
                    ->state(fn (User $record): string => $record->environments()->count() === 0
                        ? 'None'
                        : (string) $record->environments()->count())
                    ->badge()
                    ->color(fn (User $record): string => $record->environments()->count() === 0 ? 'warning' : 'success')
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
                EditAction::make(),
                // Two accounts are never offered a delete: the one being used, and
                // the last active administrator. The first would lock you out
                // mid-session and the second would leave nobody able to manage
                // the compute plane, so neither is something to confirm away.
                //
                // Deleting the account does not delete its environments. That is
                // deliberate: removing a container goes through the environment,
                // which runs OpenTofu, verifies the result and logs it. Removing
                // infrastructure as a side effect of removing a login would be a
                // quieter and less accountable path to the same outcome.
                DeleteAction::make()
                    ->visible(fn (User $record): bool => ListUsers::mayDelete($record->getKey()))
                    ->modalHeading('Delete this account?')
                    ->modalDescription('The portal account is removed. Any environment it owns stays on the compute plane and has to be deleted separately through the environment itself, so an account can never quietly take a running container with it.'),
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
