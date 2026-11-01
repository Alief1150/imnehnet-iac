<?php

namespace App\Services\GuidedCtf;

use App\Models\LabSession;
use RuntimeException;

/**
 * Permits one lab's attacker to reach its victim, and nothing else.
 *
 * The micro-segmentation on the attacker plane is the table inet laa-ctf, loaded at
 * boot by laa-ctf-config.service. The rule that matters is a set of permitted pairs:
 * traffic from 10.30.0.0/24 to 10.20.0.0/24 is accepted when the pair appears in that
 * set and dropped when it does not. That single rule is what stops one student
 * reaching another's victim, and it was measured rather than assumed:
 *
 *     attacker A -> victim A     200    its own pair is in the set
 *     attacker B -> victim A     dropped, because the pair is not
 *
 * A pair is created before the lab is usable and withdrawn when the lab ends. Doing it
 * in that order matters: a lab whose pair is not yet permitted is inert and harmless,
 * whereas a lab whose containers exist with no route between them looks broken to the
 * student and cannot be diagnosed from the portal.
 *
 * This class is the domain intent — permit this lab, revoke this lab — and holds the
 * ordering and the error handling. The mechanics of talking to the other host belong
 * to AttackerPlane.
 */
class LabFirewall
{
    public function __construct(private readonly AttackerPlane $plane) {}

    /**
     * Allow a session's attacker to reach its victim, and make it survive a restart.
     *
     * Both halves are required and either alone is a defect: adding the element to
     * the live set without writing the file leaves a lab that stops working at the next
     * reboot of the attacker plane, and writing the file without adding the element
     * leaves a lab that never worked at all. The element goes first, because until it
     * is there the lab cannot be used, whereas until the file is written the lab is
     * still working right now.
     */
    public function permit(LabSession $session): void
    {
        $this->assertPairIsKnown($session);

        $this->plane->allowPair($session->attacker_ip, $session->victim_ip);

        // A failure here raises, and the caller records the lab as failed rather than
        // as ready. A lab that will not survive a restart should not be handed to a
        // student as though it will.
        $this->plane->persistRuleset();
    }

    /**
     * Withdraw a session's pair.
     *
     * Leaving it permitted would not be untidy, it would be wrong. The octet stays
     * marked as in use, so it is never allocated again, and the next lab that receives
     * it would share a route with a lab that no longer exists.
     */
    public function revoke(LabSession $session): void
    {
        if (blank($session->attacker_ip) || blank($session->victim_ip)) {
            // A session that never got as far as having addresses has nothing to
            // withdraw. Treating that as an error would make teardown fail on exactly
            // the sessions where there is least to undo.
            return;
        }

        $this->plane->denyPair($session->attacker_ip, $session->victim_ip);

        $this->plane->persistRuleset();
    }

    /**
     * Whether the session's pair is currently permitted.
     */
    public function permits(LabSession $session): bool
    {
        if (blank($session->attacker_ip) || blank($session->victim_ip)) {
            return false;
        }

        return $this->plane->pairIsAllowed($session->attacker_ip, $session->victim_ip);
    }

    private function assertPairIsKnown(LabSession $session): void
    {
        if (blank($session->attacker_ip) || blank($session->victim_ip)) {
            throw new RuntimeException(
                'This lab has no addresses, so there is no pair to permit. Provisioning did '
                .'not finish.',
            );
        }
    }
}