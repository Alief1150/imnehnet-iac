<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;

class RecordPortalLogin
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! method_exists($user, 'forceFill')) {
            return;
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->saveQuietly();

        $this->activity->record(
            actor: $user,
            action: 'LOGIN',
            resource: $user,
            status: 'completed',
            description: sprintf('Signed in to the portal as %s.', $user->email),
        );
    }
}
