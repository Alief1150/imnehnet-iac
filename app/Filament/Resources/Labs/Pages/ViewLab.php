<?php

namespace App\Filament\Resources\Labs\Pages;

use App\Enums\LabStateRules;
use App\Filament\Resources\Labs\LabResource;
use App\Models\Lab;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewLab extends ViewRecord
{
    protected static string $resource = LabResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToLabs')
                ->label('Back to Labs')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(LabResource::getUrl('index')),

            $this->getDeleteRecordAction(),
        ];
    }

    protected function getDeleteRecordAction(): Action
    {
        return DeleteAction::make()
            ->label('Delete Record')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this lab record?')
            ->modalDescription('This removes the portal record and keeps the activity log. It is offered only when the container is already gone from the compute plane and no operation is in flight.')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true
                && ! LabStateRules::isInFlight($this->record->operation_state)
                && LabStateRules::isGone($this->record->actual_state))
            ->before(fn (Lab $record) => $record->delete())
            ->action(function (): void {
                Notification::make()
                    ->title('Lab record deleted')
                    ->body('The portal record was removed. The activity log was kept for audit.')
                    ->success()
                    ->send();
            });
    }
}
