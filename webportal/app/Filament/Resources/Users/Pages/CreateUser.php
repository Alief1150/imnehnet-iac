<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\ActivityLogService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * A generated password is handed to the administrator once and never
     * persisted in plaintext. The activity log records that a first-time
     * password was issued, not the password itself.
     */
    public ?string $generatedPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            $this->generatedPassword = $this->generatePassword();

            $data['password'] = $this->generatedPassword;
            $data['must_change_password'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ActivityLogService::class)->record(
            actor: auth()->user(),
            action: 'CREATE_USER',
            resource: $this->record,
            status: 'completed',
            description: sprintf(
                'Created the %s account %s with no lab assigned.',
                $this->record->role->value,
                $this->record->email,
            ),
            metadata: [
                'previous_status' => 'none',
                'new_status' => $this->record->status->value,
            ],
        );

        if ($this->generatedPassword !== null) {
            Notification::make()
                ->title('Account created')
                ->body('First-time password: '.$this->generatedPassword.' — share it securely. It is not stored in plaintext and the account must change it at first login.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    private function generatePassword(): string
    {
        do {
            $password = Str::password(16, symbols: true);
        } while (
            ! preg_match('/[A-Z]/', $password)
            || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/[0-9]/', $password)
            || ! preg_match('/[^A-Za-z0-9]/', $password)
        );

        return $password;
    }
}
