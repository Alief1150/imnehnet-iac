<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Schemas\Schema;

class ActivityLogForm
{
    /**
     * Activity logs are immutable by design, so there is nothing to edit. The
     * model blocks updates and deletes outright.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Intentionally empty: see LabDomainTest for the immutability
            // guarantee this resource relies on.
        ]);
    }
}
