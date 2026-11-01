<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Models\ActivityLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('What happened')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('action')
                            ->label('Action')
                            ->badge()
                            ->color(fn (string $state): string => str_ends_with($state, '_RESULT') ? 'success' : 'gray'),
                        TextEntry::make('status')
                            ->label('Result')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed', 'success' => 'success',
                                'failed' => 'danger',
                                'queued', 'running' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->label('When')
                            ->dateTime()
                            ->since()
                            ->dateTooltip(),
                    ]),

                Section::make('Detail')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Who and what')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('actor.name')
                            ->label('Actor')
                            ->placeholder('System'),
                        TextEntry::make('actor.email')
                            ->label('Actor email')
                            ->placeholder('—'),
                        TextEntry::make('resource_type')
                            ->label('Resource type')
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state)),
                        TextEntry::make('resource_id')
                            ->label('Resource id')
                            ->placeholder('—')
                            ->fontFamily('mono'),
                    ]),

                Section::make('Request context')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('Source IP')
                            ->placeholder('Not recorded')
                            ->fontFamily('mono'),
                        TextEntry::make('user_agent')
                            ->label('User agent')
                            ->placeholder('Not recorded'),
                    ]),

                Section::make('Metadata')
                    ->description('Structured detail recorded with the event.')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('metadata')
                            ->label('')
                            ->state(fn (ActivityLog $record): string => $record->metadata === null || $record->metadata === []
                                ? 'No metadata was recorded.'
                                : json_encode($record->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
