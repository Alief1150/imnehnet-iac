<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTooltip()
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->color(fn (string $state): string => str_ends_with($state, '_RESULT') ? 'success' : 'gray'),

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'completed', 'success' => 'heroicon-m-check-circle',
                        'failed' => 'heroicon-m-x-circle',
                        'queued' => 'heroicon-m-clock',
                        default => 'heroicon-m-minus-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'success' => 'success',
                        'failed' => 'danger',
                        'queued', 'running' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('resource_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('Source IP')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Result')
                    ->options([
                        'queued' => 'Queued',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
