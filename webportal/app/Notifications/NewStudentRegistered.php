<?php

namespace App\Notifications;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewStudentRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_student_registered',
            'title' => 'Pendaftaran mahasiswa baru',
            'message' => 'Akun mahasiswa baru telah terdaftar: '.$this->student->name.' ('.$this->student->email.').',
            'action_url' => UserResource::getUrl('index', panel: 'admin'),
            'action_label' => 'Kelola pengguna',
            'notifiable_type' => 'admin',
            'created_at' => now()->toISOString(),
        ];
    }
}
