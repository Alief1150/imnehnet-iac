<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        // Only Create lives here. A delete belongs on the row it deletes: as a
        // header action it has no record to act on, so every row would appear to
        // carry the same button and it would not know which account it meant.
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Two accounts can never be deleted: the one being used, and the last active
     * administrator.
     *
     * The first locks you out mid-session. The second leaves the portal with no
     * way back in, which is not recoverable from the portal itself. Kept here so
     * the table row and the record page ask exactly the same question.
     */
    public static function mayDelete(mixed $recordKey): bool
    {
        $actor = auth()->user();

        if ($actor === null || $recordKey === null) {
            return false;
        }

        if ((int) $recordKey === (int) $actor->getKey()) {
            return false;
        }

        $record = User::query()->find($recordKey);

        if (! $record instanceof User) {
            return false;
        }

        if ($record->role !== UserRole::Admin || $record->status !== UserStatus::Active) {
            return true;
        }

        return User::query()
            ->where('role', UserRole::Admin)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($record->getKey())
            ->exists();
    }
}
