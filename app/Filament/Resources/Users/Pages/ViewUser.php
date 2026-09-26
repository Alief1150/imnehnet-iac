<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Services\LabDeletionService;
use App\Services\LabProvisioningService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getProvisionLabAction(),
            $this->getDeleteLabAction(),
            EditAction::make(),
        ];
    }

    /**
     * Provisioning is a separate action from account creation, matching the
     * initial state in the specification: an active account with no lab.
     */
    protected function getProvisionLabAction(): Action
    {
        return Action::make('provisionLab')
            ->label('Provision Lab')
            ->icon('heroicon-o-server-stack')
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true
                && $this->record->role === UserRole::Student
                && $this->record->isActive()
                && $this->record->lab === null)
            ->schema([
                TextInput::make('name')
                    ->label('Lab name')
                    ->default('Cybersecurity Lab')
                    ->required(),
                TextInput::make('cpu_limit')
                    ->label('CPU (cores)')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(2)
                    ->required(),
                TextInput::make('memory_limit')
                    ->label('Memory (MB)')
                    ->numeric()
                    ->default(1024)
                    ->minValue(256)
                    ->maxValue(2048)
                    ->required(),
                TextInput::make('storage_limit')
                    ->label('Storage (GB)')
                    ->numeric()
                    ->default(10)
                    ->minValue(5)
                    ->maxValue(50)
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $operation = app(LabProvisioningService::class)->request(auth()->user(), $this->record, $data);
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('Lab could not be provisioned')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Provisioning queued')
                    ->body("Operation {$operation->id} will create the container on LAB-ENV. Watch the lab record for the result.")
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Removing a lab always goes through the compute plane first, so the
     * container is gone before the lab is unassigned from the account.
     */
    protected function getDeleteLabAction(): Action
    {
        return Action::make('deleteLab')
            ->label('Delete Lab')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this lab?')
            ->modalDescription('The container is removed from LAB-ENV first, then the account is returned to the no-lab state. The activity log is kept.')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true && $this->record->lab !== null)
            ->action(function (): void {
                $lab = $this->record->lab;

                $outcome = app(LabDeletionService::class)->delete(auth()->user(), $lab);

                if (! $outcome['deleted']) {
                    Notification::make()
                        ->title('Lab was not deleted')
                        ->body($outcome['message'])
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Lab deleted')
                    ->body('The container was removed and the account is back to no lab.')
                    ->success()
                    ->send();
            });
    }
}
