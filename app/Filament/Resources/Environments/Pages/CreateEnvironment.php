<?php

namespace App\Filament\Resources\Environments\Pages;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use App\Support\EnvironmentWorkspace;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Creating an environment records an intention and queues the work.
 *
 * Nothing here touches the infrastructure. A container takes far longer than a
 * request may spend, so the record is written in its provisioning state and a
 * job does the rest. The operator is told it has started, never that it has
 * finished.
 */
class CreateEnvironment extends CreateRecord
{
    protected static string $resource = EnvironmentResource::class;

    /**
     * The reference environment is protected, so it is never offered as a name
     * someone could type. Validation would catch it anyway, but the point of a
     * protected resource is that it does not appear as something to reach for.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['incus_remote'] = (string) config('infrastructure.incus_remote', 'lab-env:');
        $data['incus_project'] = 'default';
        // Stopped, not Provisioning. The lifecycle service is the only thing that
        // writes an in-flight status, and it does so when it accepts the request.
        // Setting Provisioning here made the record in-flight before anyone asked
        // for it, which meant request('create') then refused it as an operation
        // already running: the row was created and never queued, and stayed in
        // PROVISIONING with no container and no address.
        //
        // Stopped is the honest starting point. Nothing exists yet, so nothing is
        // running, and the record has never been provisioned.
        $data['status'] = EnvironmentStatus::Stopped;
        $data['runtime_status'] = RuntimeStatus::Stopped;
        $data['tofu_workspace'] = EnvironmentWorkspace::forContainer($data['container_name']);
        $data['terminal_enabled'] = true;

        // The owner comes from the session, never from the form. A field for it
        // would let whoever submits the form choose, and every access check for a
        // student's environment rests on this value being trustworthy. Reading it
        // from the session means the only environment a student can create is
        // their own, and an administrator's is theirs.
        $owner = auth()->id();

        if ($owner !== null) {
            $data['user_id'] = $owner;
        }

        return $data;
    }

    /**
     * Refuse the page with a reason rather than letting it 403.
     *
     * The policy already hides the create action once the quota is reached, so a
     * student should not get here from the interface. Someone can still reach the
     * URL, and an unexplained 403 reads as a broken portal rather than as a
     * limit, so the page says which limit and what to do about it.
     */
    public function mount(): void
    {
        $user = $this->actor();
        $quota = app(\App\Services\EnvironmentQuota::class);

        if ($user === null || $quota->allowsAnother($user)) {
            return;
        }

        Notification::make()
            ->title('You have reached your environment limit')
            ->body($quota->messageFor($user))
            ->danger()
            ->persistent()
            ->send();

        $this->redirect(EnvironmentResource::getUrl('index', panel: 'admin'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Environment')
                    ->description('A Lab Environment is one Linux system container inside Incus on the compute plane. The compute plane itself is never provisioned.')
                    ->columns(2)
                    ->schema($this->formSchema()),
            ]);
    }

    /**
     * @return array<Filament\Schemas\Components\Component>
     */
    protected function formSchema(): array
    {
        return [
            TextInput::make('container_name')
                ->label('Container name')
                ->required()
                ->maxLength(63)
                // The name becomes an Incus instance name, an OpenTofu
                // workspace suffix and a shell argument, so the character set
                // is checked here rather than escaped later.
                ->rules([
                    'regex:/^[a-z0-9][a-z0-9-]{2,62}$/',
                    'not_in:'.implode(',', (array) config('infrastructure.protected_containers', [])),
                ])
                ->helperText('Lowercase letters, digits and dashes. It becomes the container name and the OpenTofu workspace.'),

            Select::make('image')
                ->label('Image')
                ->options([
                    'images:ubuntu/24.04' => 'Ubuntu 24.04',
                ])
                ->default('images:ubuntu/24.04')
                ->required()
                ->native(false),

            TextInput::make('cpu_limit')
                ->label('CPU limit (cores)')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(4)
                ->required(),

            TextInput::make('memory_limit_mb')
                ->label('Memory limit (MiB)')
                ->numeric()
                ->default(1024)
                ->minValue(256)
                ->maxValue(8192)
                ->helperText('1024 MiB is 1 GiB.')
                ->required(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * After the record exists, hand it to the lifecycle service, which is the
     * only thing allowed to move it out of the provisioning state.
     */
    protected function afterCreate(): void
    {
        $this->record->refresh();

        try {
            app(EnvironmentLifecycleService::class)->request($this->actor(), $this->record, 'create');
        } catch (\DomainException $exception) {
            // The record exists and the request did not, so the row now claims an
            // environment that was never created. Say what that means and offer the
            // one action that resolves it, rather than leaving an operator looking
            // at a PROVISIONING row that will never move.
            $this->record->forceFill([
                'status' => EnvironmentStatus::Error,
                'last_error' => 'The environment was recorded but never queued: '.$exception->getMessage(),
            ])->save();

            Notification::make()
                ->title('Environment was recorded but not queued')
                ->body('Nothing was created on the compute plane. The record is marked ERROR, so it can be deleted from the list.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Provisioning started')
            ->body(sprintf(
                'Container %s is being created in workspace %s. The page will show the real address once the infrastructure reports it.',
                (string) $this->record->container_name,
                (string) $this->record->tofu_workspace
            ))
            ->warning()
            ->persistent()
            ->send();
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
