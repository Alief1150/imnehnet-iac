<?php

namespace App\Notifications;

use App\Filament\Pages\MyLabs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LabReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $environmentName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_ready',
            'title' => 'Lab siap digunakan',
            'message' => 'Lingkungan '.$this->environmentName.' telah siap dan dapat diakses.',
            'action_url' => MyLabs::getUrl(panel: 'student'),
            'action_label' => 'Buka lab',
            'notifiable_type' => 'student',
            'created_at' => now()->toISOString(),
        ];
    }
}
