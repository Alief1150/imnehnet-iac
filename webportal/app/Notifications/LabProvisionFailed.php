<?php

namespace App\Notifications;

use App\Filament\Resources\Environments\EnvironmentResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LabProvisionFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $environmentName,
        public string $error,
        public ?string $userName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_provision_failed',
            'title' => 'Provisioning lab gagal',
            'message' => 'Pengaturan lingkungan '.$this->environmentName.' gagal: '.$this->error.'.'.($this->userName ? ' (pemilik: '.$this->userName.')' : ''),
            'action_url' => EnvironmentResource::getUrl('index', panel: 'admin'),
            'action_label' => 'Lihat lingkungan',
            'notifiable_type' => 'admin',
            'created_at' => now()->toISOString(),
        ];
    }
}
