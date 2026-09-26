<?php

namespace App\Filament\Resources\Labs;

use App\Enums\LabOperationState;
use App\Filament\Resources\Labs\Pages\ListLabs;
use App\Filament\Resources\Labs\Pages\ViewLab;
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
        $query = parent::getEloquentQuery();
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
            // Form provisioning lab akan kita isi berikutnya
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Lab Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('desired_state')
                    ->label('Desired')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state)),

                Tables\Columns\TextColumn::make('actual_state')
                    ->label('Actual')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state)),

                Tables\Columns\TextColumn::make('operation_state')
                    ->label('Operation')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state)),

                Tables\Columns\TextColumn::make('cpu_limit')
                    ->label('CPU')
                    ->numeric()
                    ->suffix(' Core'),

                Tables\Columns\TextColumn::make('memory_limit')
                    ->label('RAM')
                    ->numeric()
                    ->suffix(' MB'),

                Tables\Columns\TextColumn::make('storage_limit')
                    ->label('Storage')
                    ->numeric()
                    ->suffix(' GB'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                ...collect(['start', 'stop', 'reset', 'delete'])
                    ->map(fn (string $action): Action => Action::make("request{$action}")
                        ->label(ucfirst($action))
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
