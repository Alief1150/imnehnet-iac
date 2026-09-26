<?php

namespace App\Policies;

use App\Models\LabOperation;
use App\Models\User;

final class LabOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, LabOperation $operation): bool
    {
        return $user->isActive()
            && ($user->isAdmin() || $operation->lab()->where('user_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LabOperation $operation): bool
    {
        return false;
    }

    public function delete(User $user, LabOperation $operation): bool
    {
        return false;
    }
}
