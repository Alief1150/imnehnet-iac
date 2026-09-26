<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\User;

class LabPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Lab $lab): bool
    {
        return $user->isActive() && ($user->isAdmin() || $lab->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function update(User $user, Lab $lab): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function provision(User $user, User $student): bool
    {
        return $user->isActive() && $user->isAdmin() && $student->isActive() && ! $student->isAdmin();
    }

    public function start(User $user, Lab $lab): bool
    {
        return $this->ownsOrAdmin($user, $lab);
    }

    public function stop(User $user, Lab $lab): bool
    {
        return $this->ownsOrAdmin($user, $lab);
    }

    public function reset(User $user, Lab $lab): bool
    {
        return $this->ownsOrAdmin($user, $lab);
    }

    public function delete(User $user, Lab $lab): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    public function restore(User $user, Lab $lab): bool
    {
        return $this->delete($user, $lab);
    }

    public function forceDelete(User $user, Lab $lab): bool
    {
        return false;
    }

    public function reconcile(User $user, Lab $lab): bool
    {
        return $user->isActive() && $user->isAdmin();
    }

    private function ownsOrAdmin(User $user, Lab $lab): bool
    {
        return $user->isActive() && ($user->isAdmin() || $lab->user_id === $user->id);
    }
}
