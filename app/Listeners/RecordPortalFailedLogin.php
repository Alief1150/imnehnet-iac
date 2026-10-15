<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;

class RecordPortalFailedLogin
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function handle(Failed $event): void
    {
        $credentials = is_array($event->credentials) ? $event->credentials : [];
        $email = isset($credentials['email']) && is_string($credentials['email']) && $credentials['email'] !== ''
            ? $credentials['email']
            : 'unknown';

        // Never persist the credential payload: it carries the password.
        $user = $event->user instanceof User ? $event->user : null;

        $this->activity->record(
            actor: $user,
            action: 'LOGIN',
            resource: $user instanceof Model ? $user : $email,
            status: 'failed',
            description: sprintf('Failed sign-in attempt for %s.', $email),
        );
    }
}
