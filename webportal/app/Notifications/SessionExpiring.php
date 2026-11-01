<?php

namespace App\Notifications;

use App\Filament\Pages\Terminal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $environmentName,
        public int $minutesRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_expiring',
            'title' => 'Sesi terminal akan berakhir',
            'message' => 'Sesi terminal untuk '.$this->environmentName.' akan berakhir dalam '.$this->minutesRemaining.' menit.',
            'action_url' => Terminal::getUrl(panel: 'student'),
            'action_label' => 'Buka terminal',
            'notifiable_type' => 'student',
            'created_at' => now()->toISOString(),
        ];
    }
}
