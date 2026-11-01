<?php

namespace App\Filament\Resources\Environments\Pages;

use App\Filament\Resources\Environments\EnvironmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEnvironment extends ViewRecord
{
    protected static string $resource = EnvironmentResource::class;

    /**
     * No edit action. A change to an environment is an operation against the
     * infrastructure, not an edit of a row, and offering a form would let the
     * record drift from what actually exists.
     *
     * @return array<Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
