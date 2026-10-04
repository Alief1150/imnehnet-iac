<?php

namespace App\Services;

use App\Models\LabEnvironment;
use App\Models\User;

/**
 * How many environments one student may hold at a time.
 *
 * Registration is open, which means anyone who can reach this portal can make an
 * account, and without a limit each of them can ask the compute plane for as many
 * containers as they like. The portal's own limits are per container: four cores
 * and eight gigabytes. A limit on how many a person may hold is the other half,
 * because a thousand one-gigabyte containers cost the same as a thousand
 * eight-gigabyte ones and the specification is a shared machine.
 *
 * Administrators are not counted. They provision environments for other people
 * and for testing, and a number meant to stop one student flooding the plane
 * would only get in their way.
 */
final class EnvironmentQuota
{
    public function limit(): int
    {
        return max(0, (int) config('infrastructure.student_environment_limit', 3));
    }

    /**
     * Environments currently attributed to this student.
     */
    public function usedBy(User $user): int
    {
        if ($user->isAdmin()) {
            return 0;
        }

        return LabEnvironment::query()
            ->where('user_id', $user->getKey())
            ->count();
    }

    public function remainingFor(User $user): int
    {
        if ($user->isAdmin()) {
            // Administrators are outside the quota, so there is nothing to count
            // down. Returning the limit keeps the wording honest rather than
            // showing a negative number that would read as a bug.
            return $this->limit();
        }

        return max(0, $this->limit() - $this->usedBy($user));
    }

    public function allowsAnother(User $user): bool
    {
        return $this->remainingFor($user) > 0;
    }

    /**
     * The sentence to show when the quota is reached.
     *
     * States the number and what to do about it, because a student who is told
     * only that they may not create an environment has no way to tell whether
     * they did something wrong or the portal is broken.
     */
    public function messageFor(User $user): string
    {
        return sprintf(
            'You are holding %d of %d environments. Delete one before creating another, so the compute plane stays within what it can serve.',
            $this->usedBy($user),
            $this->limit(),
        );
    }
}