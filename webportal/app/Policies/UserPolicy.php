<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isActive() && ($user->isAdmin() || $user->is($subject));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isActive() && $user->isAdmin() && ! $user->is($subject);
    }

    public function delete(User $user, User $subject): bool
    {
        return $this->update($user, $subject);
    }
}
