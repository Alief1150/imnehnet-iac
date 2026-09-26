<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\MapsStatusColor;
use App\Models\ActivityLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class RecentActivity extends TableWidget
{
    use MapsStatusColor;

    protected static ?string $heading = 'Recent Activity';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityLog::query()
                    ->with('actor:id,name,email')
                    ->when(
                        auth()->user()?->isAdmin() !== true,
                        fn (Builder $query): Builder => $query->where('actor_id', auth()->id()),
                    )
                    ->latest()
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (ActivityLog $record): string => match ($record->status) {
                        'completed', 'success' => 'success',
                        'failed' => 'danger',
                        'queued', 'running' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('resource_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('Source IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('status')
                    ->label('Status')
                    ->icon(fn (string $state): string => match ($state) {
                        'completed', 'success' => 'heroicon-m-check-circle',
                        'failed' => 'heroicon-m-x-circle',
                        'queued' => 'heroicon-m-clock',
                        'running' => 'heroicon-m-arrow-path',
                        default => 'heroicon-m-minus-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'success' => 'success',
                        'failed' => 'danger',
                        'queued', 'running' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTooltip()
                    ->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isActive();
    }
}
