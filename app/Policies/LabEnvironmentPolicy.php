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
    /**
     * An administrator role is not on its own sufficient.
     *
     * The panel already refuses a disabled account, so this looks redundant
     * until a caller reaches the policy from somewhere the panel middleware did
     * not run: a table action closure, a queued job, or the lifecycle service
     * itself. All three are covered by this, and a deactivated account should not
     * be able to drive a compute plane through any of them.
     */
    private function mayManage(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function viewAny(User $user): bool
    {
        return $this->mayManage($user);
    }

    public function view(User $user, LabEnvironment $environment): bool
    {
        return $this->mayManage($user);
    }

    /**
     * Creating an environment is an administrative act. Students never create
     * their own lab; an administrator provisions one for them.
     */
    public function create(User $user): bool
    {
        return $this->mayManage($user);
    }

    /**
     * Lifecycle actions additionally refuse the reference environment. The
     * guard lives here as well as in the lifecycle service, so a caller that
     * reaches the policy directly is refused too.
     */
    public function update(User $user, LabEnvironment $environment): bool
    {
        return $this->mayManage($user) && ! $environment->isProtected();
    }

    public function delete(User $user, LabEnvironment $environment): bool
    {
        return $this->mayManage($user) && ! $environment->isProtected();
    }

    /**
     * Reading the actual state is always allowed, including for the reference
     * environment. That is the point of it existing.
     */
    public function reconcile(User $user, LabEnvironment $environment): bool
    {
        return $this->mayManage($user);
    }
}
