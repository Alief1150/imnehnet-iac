<?php

namespace App\Filament\Resources\Labs\Pages;

use App\Filament\Resources\Labs\LabResource;
use App\Models\Lab;
use App\Services\LabDeletionService;
use Filament\Actions\Action;
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

            $this->getDeleteLabAction(),
        ];
    }

    /**
     * Deleting a lab removes the container on the compute plane first. The
     * portal record is only archived once the container is confirmed gone, so
     * the UI can never claim a lab is deleted while a student can still reach
     * it.
     */
    protected function getDeleteLabAction(): Action
    {
        return Action::make('deleteLab')
            ->label('Delete Lab')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this lab?')
            ->modalDescription('The container is deleted from the compute plane first, then the portal record is archived. The activity log is kept. This cannot be undone.')
            ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) === true)
            ->action(function (): void {
                /** @var LabDeletionService $deletion */
                $deletion = app(LabDeletionService::class);

                $outcome = $deletion->delete(auth()->user(), $this->record);

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
                    ->body('The container was removed from the compute plane and the record archived.')
                    ->success()
                    ->send();

                $this->redirect(LabResource::getUrl('index'));
            });
    }
}
