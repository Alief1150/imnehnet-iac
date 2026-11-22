<?php

namespace App\Filament\Resources\Environments\Pages;

use App\Enums\EnvironmentStatus;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\AdminEnvironmentPairCreator;
use Filament\Notifications\Notification;
use App\Support\Lucide;
use Filament\Forms\Components\TextInput;

use Filament\Actions\Action;
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
            Action::make('createAdminPair')
                ->label('Create Pair')
                ->icon(Lucide::icon('plus'))
                ->color('primary')
                ->modalHeading('Create Environment Pair')
                ->modalDescription(
                    'Preview konfigurasi Admin Pair. '
                    .'Admin Pair dibuat terpisah dari Guided CTF.'
                )
                ->form([
                    TextInput::make('pair_name')
                        ->label('Pair Name')
                        ->placeholder('admin-001')
                        ->required()
                        ->regex('/^[a-z][a-z0-9-]{2,38}$/'),

                    TextInput::make('attacker_resources')
                        ->label('Attacker · LAB-ATK')
                        ->default(fn (): string =>
                            config('infrastructure.default_cpu_limit', 2).' vCPU · '.
                            config('infrastructure.ctf_attacker_memory_limit_mb', 1024).' MiB · '.
                            config('infrastructure.attacker_image', 'images:kali'))
                        ->disabled()->dehydrated(false),

                    TextInput::make('victim_resources')
                        ->label('Victim · LAB-ENV')
                        ->default(fn (): string =>
                            config('infrastructure.default_cpu_limit', 2).' vCPU · '.
                            config('infrastructure.default_memory_limit_mb', 2048).' MiB · '.
                            config('infrastructure.lab_image', 'images:ubuntu/24.04'))
                        ->disabled()->dehydrated(false),
                ])
                ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                ->modalSubmitActionLabel('Create Pair')
                ->modalCancelActionLabel('Cancel')
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User || ! $actor->isAdmin() || ! $actor->isActive()) {
                        Notification::make()->title('Not authorized')->danger()->send();
                        return;
                    }
                    try {
                        $pair = app(AdminEnvironmentPairCreator::class)->create(
                            $actor,
                            (string) ($data['pair_name'] ?? '')
                        );
                        Notification::make()
                            ->title('Admin Pair queued')
                            ->body('Pair #'.$pair->getKey().' is provisioning. Two rows will appear in Environments.')
                            ->warning()
                            ->send();
                    } catch (\Throwable $error) {
                        report($error);
                        Notification::make()
                            ->title('Could not create Admin Pair')
                            ->body('No success was reported. See Laravel logs for details.')
                            ->danger()->send();
                    }
                }),
        ];
    }

    /**
     * Independent admin-only section, above the existing student table.
     *
     * @return array<class-string>
     */


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
