<?php

namespace App\Filament\Resources\Labs\Pages;

use App\Filament\Resources\Labs\LabResource;
use App\Models\User;
use App\Services\LabProvisioningService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLabs extends ListRecords
{
    protected static string $resource = LabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queueProvisioningRequest')
                ->label('Queue Lab Request')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                ->schema([
                    Select::make('user_id')
                        ->label('Student')
                        ->options(fn (): array => User::query()
                            ->where('role', 'student')
                            ->doesntHave('lab')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required(),
                    TextInput::make('name')->maxLength(120),
                    TextInput::make('cpu_limit')->numeric()->minValue(1)->maxValue(2)->default(1)->required(),
                    TextInput::make('memory_limit')->numeric()->minValue(256)->maxValue(2048)->default(1024)->required(),
                    TextInput::make('storage_limit')->numeric()->minValue(5)->maxValue(50)->default(10)->required(),
                ])
                ->action(function (array $data): void {
                    $student = User::query()->findOrFail($data['user_id']);
                    $operation = app(LabProvisioningService::class)->request(auth()->user(), $student, $data);

                    Notification::make()
                        ->title('Request queued')
                        ->body("Operation {$operation->id} was queued. Live mutation remains disabled in Milestone 1.")
                        ->warning()
                        ->send();
                }),
        ];
    }
}
