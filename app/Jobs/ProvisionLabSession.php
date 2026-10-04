<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\LabScenario;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Builds both halves of a lab, permits the pair, and applies the scenario.
 *
 * The order is not a preference. It is the order in which each step is either useless
 * or harmful if the one before it did not happen:
 *
 *   1. victim on LAB-ENV       — without it there is nothing to attack
 *   2. attacker on LAB-ATK     — without it there is no shell and no traffic
 *   3. permit the pair         — until this exists the attacker cannot reach the
 *                                victim, so the lab exists but does not work
 *   4. configure the scenario  — until this exists the victim serves nothing worth
 *                                attacking and the objectives have no evidence
 *
 * Step three is the one that was missing, and its absence is why a pair created by
 * automation had to be added to the firewall by hand.
 *
 * One attempt only. A lab is two containers and a firewall rule; retrying after a
 * partial failure would leave half a lab and a rule pointing at an address that may
 * already belong to something else. The session is marked failed with the reason
 * instead, which the student can see and an operator can act on.
 */
final class ProvisionLabSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Two container creations, a firewall write and an Ansible run. */
    public int $timeout = 2400;

    public function __construct(public readonly int $sessionId) {}

    public function handle(
        OpenTofuService $tofu,
        LabFirewall $firewall,
        LabScenario $scenario,
        LabSessionManager $sessions,
    ): void {
        $session = LabSession::query()->find($this->sessionId);

        if ($session === null) {
            // Deleted while the job waited. Nothing to build and nothing to report.
            return;
        }

        $session->forceFill(['status' => LabSessionStatus::Provisioning])->save();

        $victim = LabEnvironment::query()->find($session->victim_environment_id);

        if ($victim === null) {
            $sessions->markFailed($session->refresh(), 'The victim record disappeared before the lab could be built.');

            return;
        }

        try {
            // Both halves are sized from the victim's record. They are the same values
            // for the pair, and the victim row is the one that already carries them —
            // the session holds addresses and status, not resources.
            $cpu = (int) $victim->cpu_limit;
            $megabytes = (int) $victim->memory_limit_mb;

            // --- 1. victim -----------------------------------------------------
            $this->applyInstance(
                $tofu,
                $victim->tofu_workspace,
                $victim->container_name,
                'lab-env',
                $victim->internal_ip,
                $cpu,
                $megabytes,
                // No ACL: LAB-ENV runs Incus 6.0.0 and does not support them, and the
                // victim is the side being reached rather than the side initiating.
                '',
            );

            // --- 2. attacker ---------------------------------------------------
            $this->applyInstance(
                $tofu,
                $session->attacker_container,
                $session->attacker_container,
                $session->attacker_remote,
                $session->attacker_ip,
                $cpu,
                $megabytes,
                // The ACL is what puts the restriction on the side that initiates the
                // connection. Without it the attacker has no reason to refuse another
                // student's victim, and the nftables set alone would be the only guard.
                (string) config('infrastructure.attacker_acl', 'ctf-attacker'),
            );

            // --- 3. permit the pair --------------------------------------------
            $firewall->permit($session->refresh());

            // --- 4. the scenario ----------------------------------------------
            $session->forceFill(['status' => LabSessionStatus::Configuring])->save();

            $scenario->apply($session->refresh());

            $sessions->markReady($session->refresh());
        } catch (RuntimeException $exception) {
            // Said plainly and left for a human. A lab that half exists is worse than
            // one that failed with a reason, because the student cannot tell which of
            // the two containers is missing and neither can an operator from the page.
            $sessions->markFailed(
                $session->refresh(),
                'The lab could not be built: '.$exception->getMessage(),
            );
        }
    }

    /**
     * Create one container through OpenTofu and confirm it is running.
     *
     * $acl is the ACL name or an empty string, and the empty case matters: an ACL key
     * with a blank value is rejected by Incus rather than ignored, so it is omitted
     * rather than emptied.
     */
    private function applyInstance(
        OpenTofuService $tofu,
        string $workspace,
        string $name,
        string $remote,
        string $address,
        int $cpu,
        int $megabytes,
        string $acl,
    ): void {
        $tofu->selectOrCreateWorkspace($workspace);

        $variables = [
            'container_name' => $name,
            'incus_remote' => $remote,
            'static_ipv4' => $address,
            'incus_network' => (string) config('infrastructure.incus_network', 'incusbr0'),
            'cpu_limit' => $cpu,
            // OpenTofu takes a quantity with a unit; the portal stores megabytes
            // because that is what the form and the record have always used.
            'memory_limit' => $megabytes.'MiB',
            'desired_running' => 'true',
        ];

        if ($acl !== '') {
            $variables['security_acls'] = $acl;
        }

        $applied = $tofu->apply($variables);

        if (! $applied->successful()) {
            throw new RuntimeException(sprintf(
                'the %s container "%s" could not be created on %s',
                $remote === 'lab-env' ? 'victim' : 'attacker',
                $name,
                $remote,
            ));
        }
    }
}