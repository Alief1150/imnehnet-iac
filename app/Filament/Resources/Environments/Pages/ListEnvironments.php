<?php

namespace App\Filament\Resources\Environments\Pages;

use App\Enums\EnvironmentStatus;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Models\LabEnvironment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnvironments extends ListRecords
{
    protected static string $resource = EnvironmentResource::class;

    /**
     * @return array<Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Environment'),
        ];
    }

    /**
     * Poll only while something is actually in flight. A permanent timer would
     * keep hitting the database and the compute plane for a list that is not
     * changing, and would make a healthy system look busy.
     */
    protected function getPollingInterval(): ?string
    {
        // Read the flag from the query string the table already sends, so the
        // page does not need its own query.
        $busy = LabEnvironment::query()
            ->whereIn('status', [
                EnvironmentStatus::Provisioning->value,
                EnvironmentStatus::Configuring->value,
                EnvironmentStatus::Resetting->value,
                EnvironmentStatus::Deleting->value,
            ])
            ->exists();

        return $busy ? '3s' : null;
    }
}
