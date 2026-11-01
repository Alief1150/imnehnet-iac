<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use App\Notifications\LabProvisionFailed;
use App\Notifications\LabReady;
use App\Services\GuidedCtf\AttackerTools;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LabScenario;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\LifecycleFailure;
use App\Services\GuidedCtf\ProvisioningTimeline;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;

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

    public bool $failOnTimeout = true;

    public int $tries = 1;

    /** Two container creations, a firewall write and an Ansible run. */
    public int $timeout = 2400;

    public function __construct(public readonly int $sessionId) {}

    public function failed(?\Throwable $error): void
    {
        LifecycleFailure::record($this->sessionId, 'Provision Lab', $error ?? new RuntimeException('Worker timed out.'));

        // Notify administrators about the job failure.
        $session = LabSession::query()->find($this->sessionId);
        if ($session !== null) {
            $student = User::query()->find($session->user_id);
            foreach (User::query()->where('role', UserRole::Admin->value)->where('status', 'active')->get() as $admin) {
                $admin->notify(new LabProvisionFailed(
                    $session->victim_container ?? $session->container_name ?? 'Lab',
                    $error->getMessage(),
                    $student?->name,
                ));
            }
        }
    }

    public function handle(
        OpenTofuService $tofu,
        LabFirewall $firewall,
        LabScenario $scenario,
        LabSessionManager $sessions,
    ): void {
        Cache::lock(
            'imnehnet:ctf:session-lifecycle:'.$this->sessionId,
            2700,
        )->block(60, function () use (
            $tofu, $firewall, $scenario, $sessions
        ): void {
            $session = LabSession::query()->find($this->sessionId);

            if ($session === null) {
                // Deleted while the job waited. Nothing to build and nothing to report.
                return;
            }

            // A queued provisioning job must never revive a session that is
            // being torn down or has already been deleted.
            if (in_array($session->status, [
                LabSessionStatus::Deleting,
                LabSessionStatus::Deleted,
            ], true)) {
                return;
            }

            $session->forceFill(['status' => LabSessionStatus::Provisioning])->save();
            ProvisioningTimeline::record($this->sessionId, 'session', 'Provisioning worker started.');

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

                ProvisioningTimeline::record($this->sessionId, 'victim', 'Starting LAB-ENV victim provisioning through OpenTofu.');
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

                ProvisioningTimeline::record($this->sessionId, 'victim', 'LAB-ENV victim provisioning completed.', 'success');
                ProvisioningTimeline::record($this->sessionId, 'attacker', 'Starting LAB-ATK attacker provisioning through OpenTofu.');
                // --- 2. attacker ---------------------------------------------------
                $this->applyInstance(
                    $tofu,
                    LabEnvironment::query()->findOrFail($session->attacker_environment_id)->tofu_workspace,
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

                // Prepare an unprivileged shell account before LAB READY.
                // Only the attacker container of this session is modified.
                ProvisioningTimeline::record(
                    $this->sessionId,
                    'attacker',
                    'Preparing the student terminal account.'
                );

                $bootstrap = new Process([
                    (string) config('infrastructure.incus_binary', 'incus'),
                    'exec',
                    rtrim($session->attacker_remote, ':').':'.$session->attacker_container,
                    '--',
                    'sh',
                    '-ec',
                    'if ! id student >/dev/null 2>&1; then '.
                        'useradd --create-home --shell /bin/bash --user-group student; '.
                    'fi; '.
                    'test "$(id -u student)" -gt 0; '.
                    'test "$(id -g student)" -gt 0; '.
                    'test -x /bin/bash',
                ]);

                $bootstrap->setEnv(['INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus')]);
                $bootstrap->setTimeout(60);
                $bootstrap->run();

                if (! $bootstrap->isSuccessful()) {
                    throw new RuntimeException(
                        'Student terminal bootstrap failed: '.
                        trim($bootstrap->getErrorOutput())
                    );
                }

                ProvisioningTimeline::record(
                    $this->sessionId,
                    'attacker',
                    'Student terminal account ready.',
                    'success'
                );

                ProvisioningTimeline::record($this->sessionId, 'tools', 'Installing and checking Nmap, curl, Gobuster and wordlist.');
                app(AttackerTools::class)->ensure($session->refresh());
                ProvisioningTimeline::record($this->sessionId, 'tools', 'Attacker tools verified as student.', 'success');
                ProvisioningTimeline::record($this->sessionId, 'attacker', 'LAB-ATK attacker provisioning completed.', 'success');
                ProvisioningTimeline::record($this->sessionId, 'network', 'Applying paired network access policy.');
                // --- 3. permit the pair --------------------------------------------
                $firewall->permit($session->refresh());

                ProvisioningTimeline::record($this->sessionId, 'network', 'Paired network access policy applied.', 'success');
                ProvisioningTimeline::record($this->sessionId, 'scenario', 'Configuring Web Server Breach scenario.');
                // --- 4. the scenario ----------------------------------------------
                $session->forceFill(['status' => LabSessionStatus::Configuring])->save();

                $scenario->apply($session->refresh());

                $sessions->markReady($session->refresh());
                ProvisioningTimeline::record($this->sessionId, 'ready', 'Both containers are ready. Guided CTF is available.', 'success');

                // Notify the student that the lab is ready.
                $student = User::query()->find($session->user_id);
                if ($student !== null) {
                    $student->notify(new LabReady($session->victim_container ?? $session->container_name ?? 'Lab'));
                }
            } catch (RuntimeException $exception) {
                ProvisioningTimeline::record($this->sessionId, 'failed', 'Provisioning failed; cleanup is starting.', 'error');
                // Said plainly. A lab that half exists is worse than one that failed with a
                // reason, because the student cannot tell which of the two containers is
                // missing and neither can an operator from the page.
                $sessions->markFailed(
                    $session->refresh(),
                    'The lab could not be built: '.$exception->getMessage(),
                );

                // Notify the student and administrators about the failure.
                $student = User::query()->find($session->user_id);
                if ($student !== null) {
                    $student->notify(new LabProvisionFailed(
                        $session->victim_container ?? $session->container_name ?? 'Lab',
                        $exception->getMessage(),
                        $student->name,
                    ));
                }
                foreach (User::query()->where('role', UserRole::Admin->value)->where('status', 'active')->get() as $admin) {
                    $admin->notify(new LabProvisionFailed(
                        $session->victim_container ?? $session->container_name ?? 'Lab',
                        $exception->getMessage(),
                        $student?->name,
                    ));
                }

                $this->undo($tofu, $firewall, $session->refresh(), $victim);
            }
        });
    }

    /**
     * Put the compute planes back the way they were before this attempt.
     *
     * Runs after the failure has been recorded, and never throws: a cleanup that raises
     * would replace the real reason for the failure with a second, later one, and the
     * message that matters is the first.
     *
     * The workspaces are destroyed rather than deleted, so the containers they track go
     * with them. Deleting a workspace that still tracks an instance only makes OpenTofu
     * forget it, and the container keeps running, holds its address and blocks the next
     * attempt with "already exists".
     */
    private function undo(
        OpenTofuService $tofu,
        LabFirewall $firewall,
        LabSession $session,
        LabEnvironment $victim,
    ): void {
        try {
            $firewall->revoke($session);
        } catch (\Throwable) {
            // Recorded and moved past. A pair that stays permitted is bad, but a
            // teardown that stops here leaves both containers behind as well.
        }

        $attacker = LabEnvironment::query()->find($session->attacker_environment_id);

        foreach (array_filter([$victim->tofu_workspace, $attacker?->tofu_workspace]) as $workspace) {
            try {
                $tofu->selectOrCreateWorkspace($workspace);
                $tofu->destroy();
            } catch (\Throwable) {
                // Same reasoning: a surviving container is recoverable, an abort here
                // would leave the other one behind too.
            }
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
