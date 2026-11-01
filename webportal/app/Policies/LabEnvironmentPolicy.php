<?php

namespace App\Policies;

use App\Models\LabEnvironment;
use App\Models\User;

/**
 * Access rules for an environment record.
 *
 * Written as a policy rather than hidden inside a table action so the check
 * exists even if the button is rendered somewhere else: a button is presentation,
 * and presentation is not a control.
 *
 * An administrator may manage everything except the protected reference
 * environment. Everyone else may manage only what they own, and may create their
 * own. Ownership is compared against the record rather than assumed, because the
 * whole point of the session and record design is that nothing can be pointed
 * somewhere it was not granted.
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
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, LabEnvironment $environment): bool
    {
        return $user->isActive()
            && ($user->isAdmin() || $this->owns($user, $environment));
    }

    /**
     * Creating an environment is not an administrative act.
     *
     * A student provisions their own lab and owns it from the moment the record
     * exists. The owner is assigned by the create page from the session, never
     * from the request, so the only environment a student can create is their
     * own.
     */
    public function create(User $user): bool
    {
        // Registration is open, so an account that has never been approved by
        // anyone can reach this. The quota is what stops each of them asking the
        // compute plane for an unbounded number of containers.
        return $user->isActive()
            && app(\App\Services\EnvironmentQuota::class)->allowsAnother($user);
    }

    /**
     * Lifecycle actions additionally refuse the reference environment. The guard
     * lives here as well as in the lifecycle service, so a caller that reaches
     * the policy directly is refused too.
     */
    public function update(User $user, LabEnvironment $environment): bool
    {
        return $this->view($user, $environment) && ! $environment->isProtected();
    }

    public function delete(User $user, LabEnvironment $environment): bool
    {
        return $this->view($user, $environment) && ! $environment->isProtected();
    }

    /**
     * Reading the actual state is always allowed, including for the reference
     * environment. That is the point of it existing.
     */
    public function reconcile(User $user, LabEnvironment $environment): bool
    {
        return $user->isActive();
    }

    private function owns(User $user, LabEnvironment $environment): bool
    {
        return $environment->user_id !== null
            && (int) $environment->user_id === (int) $user->getKey();
    }
}
