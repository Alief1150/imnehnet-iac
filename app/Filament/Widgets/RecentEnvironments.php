<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Environments\EnvironmentResource;
use App\Models\LabEnvironment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A short, dense list rather than a repeat of the full table. The purpose is
 * to show what exists without making the operator scroll a second screen to
 * find it.
 */
final class RecentEnvironments extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Environments';

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LabEnvironment::query()->latest('updated_at'))
            ->columns([
                TextColumn::make('container_name')
                    ->label('Container')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('internal_ip')
                    ->label('IP address')
                    ->placeholder('Not assigned')
                    ->fontFamily('mono'),

                TextColumn::make('status')
                    ->label('Provisioning')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->icon(fn ($state): ?Htmlable => $state->icon()),

                TextColumn::make('runtime_status')
                    ->label('Runtime')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->icon(fn ($state): ?Htmlable => $state->icon()),

                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->since(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (LabEnvironment $record): string => EnvironmentResource::getUrl('view', ['record' => $record]));
    }
}
