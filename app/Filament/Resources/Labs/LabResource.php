<?php

namespace App\Filament\Resources\Labs;

use App\Enums\LabOperationState;
use App\Filament\Resources\Labs\Pages\ListLabs;
use App\Filament\Resources\Labs\Pages\ViewLab;
use App\Filament\Resources\Labs\Schemas\LabInfolist;
use App\Models\Lab;
use App\Services\LabLifecycleService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LabResource extends Resource
{
    protected static ?string $model = Lab::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('owner:id,name,email');
        $user = auth()->user();

        if ($user && ! $user->isAdmin()) {
            return $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // The provisioning form is deliberately empty: labs are created by
            // LabProvisioningService, never by editing a resource directly.
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LabInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Lab Name')
                    ->description(fn (Lab $record): ?string => $record->container_name)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('actual_state')
                    ->label('Container')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state))
                    ->description(fn (Lab $record): ?string => $record->ip_address
                        ? $record->ip_address.($record->ssh_port ? ':'.$record->ssh_port : '')
                        : null)
                    ->color(fn (mixed $state): string => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                        'running' => 'success',
                        'error' => 'danger',
                        'created', 'stopped' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('operation_state')
                    ->label('Operation')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state))
                    ->color(fn (mixed $state): string => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                        'failed' => 'danger',
                        'queued', 'running' => 'warning',
                        'idle' => 'success',
                        default => 'gray',
                    })
                    ->description(fn (Lab $record): ?string => $record->last_error_message),

                Tables\Columns\TextColumn::make('resources')
                    ->label('Limits')
                    ->state(fn (Lab $record): string => sprintf('%d C / %d MB / %d GB', $record->cpu_limit, $record->memory_limit, $record->storage_limit))
                    ->fontFamily('mono')
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),

                ...collect(['start', 'stop', 'reset', 'delete'])
                    ->map(fn (string $action): Action => Action::make("request{$action}")
                        ->label(ucfirst($action))
                        ->icon(match ($action) {
                            'start' => Heroicon::OutlinedPlay,
                            'stop' => Heroicon::OutlinedStop,
                            'reset' => Heroicon::OutlinedArrowPath,
                            'delete' => Heroicon::OutlinedTrash,
                            default => Heroicon::OutlinedWrenchScrewdriver,
                        })
                        ->requiresConfirmation()
                        ->visible(fn (Lab $record): bool => auth()->user()?->can($action, $record) === true
                            && in_array($record->operation_state, [LabOperationState::Idle, LabOperationState::Failed], true)
                            && app(LabLifecycleService::class)->isAvailable($record, $action))
                        ->action(function (Lab $record) use ($action): void {
                            try {
                                $operation = app(LabLifecycleService::class)->request(auth()->user(), $record, $action);
                            } catch (DomainException) {
                                Notification::make()
                                    ->title('Action unavailable')
                                    ->body('The requested action is not valid for the current lab state.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(ucfirst($action).' request queued')
                                ->body("Operation {$operation->id} is fail-closed until a live provider is approved.")
                                ->warning()
                                ->send();
                        }))
                    ->all(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabs::route('/'),
            'view' => ViewLab::route('/{record}'),
        ];
    }
}
