<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Environments are created and destroyed from the Environments
        // resource, which is where the lifecycle and its guard rules live.
        // Offering it here as well would be a second way to do the same thing
        // with none of the checks the table has.
        return [
            EditAction::make(),
        ];
    }
}
