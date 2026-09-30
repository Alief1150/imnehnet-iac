<?php

namespace App\Filament\Resources\Environments\Tables;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The operational view of every environment.
 *
 * Reads densely and in a fixed order, because an operator scans it rather than
 * reads it: identity, where it lives, what it is made of, what state both sides
 * of the split report, and then the actions that state actually allows.
 *
 * Lifecycle status and runtime status are two separate columns. An environment
 * can be ready in the lifecycle sense and stopped as a container, and
 * collapsing them into one column would make that impossible to see.
 */
class EnvironmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('container_name')
                    ->label('Container')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->description(fn (LabEnvironment $record): ?string => $record->isProtected()
                        ? 'Protected reference environment'
                        : null),

                TextColumn::make('internal_ip')
                    ->label('IP address')
                    ->placeholder('Not assigned')
                    ->fontFamily('mono')
                    // Never invented. An address that has not been observed is
                    // shown as such rather than left blank, which reads as an
                    // oversight rather than an honest gap.
                    ->color(fn (mixed $state): ?string => filled($state) ? null : 'gray'),

                TextColumn::make('image')
                    ->label('Image')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cpu_limit')
                    ->label('CPU')
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : sprintf('%s core%s', $state, (int) $state === 1 ? '' : 's'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('memory_limit_mb')
                    ->label('Memory')
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : sprintf('%d MiB', (int) $state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Provisioning')
                    ->badge()
                    ->formatStateUsing(fn (EnvironmentStatus $state): string => $state->label())
                    ->color(fn (EnvironmentStatus $state): string => $state->color())
                    ->icon(fn (EnvironmentStatus $state): ?Htmlable => $state->icon()),

                TextColumn::make('runtime_status')
                    ->label('Runtime')
                    ->badge()
                    ->formatStateUsing(fn (RuntimeStatus $state): string => $state->label())
                    ->color(fn (RuntimeStatus $state): string => $state->color())
                    ->icon(fn (RuntimeStatus $state): ?Htmlable => $state->icon())
                    // The whole point of the column is to disagree with the
                    // lifecycle column when it does, so the disagreement is
                    // shown rather than left for the operator to deduce.
                    ->extraAttributes(fn (LabEnvironment $record): array => $record->hasStateMismatch()
                        ? ['title' => 'State mismatch: the record and the container disagree']
                        : []),

                TextColumn::make('tofu_workspace')
                    ->label('Workspace')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not assigned'),

                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->since()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('container_name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Provisioning status')
                    ->options(collect(EnvironmentStatus::cases())
                        ->mapWithKeys(fn (EnvironmentStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                SelectFilter::make('runtime_status')
                    ->label('Runtime status')
                    ->options(collect(RuntimeStatus::cases())
                        ->mapWithKeys(fn (RuntimeStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                Filter::make('mismatch')
                    ->label('State mismatch only')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(function (Builder $query): void {
                            $query
                                ->where(fn (Builder $q): Builder => $q->where('status', 'READY')->where('runtime_status', '!=', RuntimeStatus::Running->value))
                                ->orWhere(fn (Builder $q): Builder => $q->where('status', 'STOPPED')->where('runtime_status', RuntimeStatus::Running->value));
                        })),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->actions([])
            ->headerActions([])
            ->paginated([10, 25, 50]);
    }
}
