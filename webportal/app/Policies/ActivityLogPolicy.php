<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

final class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
