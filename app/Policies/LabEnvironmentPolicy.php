<?php

namespace App\Policies;

use App\Models\LabEnvironment;
use App\Models\User;

/**
 * Access rules for an environment record.
 *
 * The sprint is administrator only, so every lifecycle action is granted to an
 * administrator. The rule is written as a policy rather than hidden inside a
 * table action so the check exists even if the button is rendered somewhere
 * else: a button is presentation, and presentation is not a control.
 */
class LabEnvironmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, LabEnvironment $environment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Creating an environment is an administrative act. Students never create
     * their own lab; an administrator provisions one for them.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Lifecycle actions additionally refuse the reference environment. The
     * guard lives here as well as in the lifecycle service, so a caller that
     * reaches the policy directly is refused too.
     */
    public function update(User $user, LabEnvironment $environment): bool
    {
        return $user->isAdmin() && ! $environment->isProtected();
    }

    public function delete(User $user, LabEnvironment $environment): bool
    {
        return $user->isAdmin() && ! $environment->isProtected();
    }

    /**
     * Reading the actual state is always allowed, including for the reference
     * environment. That is the point of it existing.
     */
    public function reconcile(User $user, LabEnvironment $environment): bool
    {
        return $user->isAdmin();
    }
}
