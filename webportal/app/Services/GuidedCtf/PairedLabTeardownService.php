<?php

namespace App\Services\GuidedCtf;

use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\OpenTofuService;
use RuntimeException;

/**
 * Paired CTF teardown. Does not mutate database status or records.
 * The coordinating job owns lifecycle transitions and quota release.
 */
final class PairedLabTeardownService
{
    public function __construct(
        private readonly OpenTofuService $tofu,
        private readonly ContainerAbsenceVerifier $absence,
        private readonly LabFirewall $firewall,
    ) {}

    public function teardown(LabSession $session): void
    {
        ProvisioningTimeline::record((int) $session->id, 'delete-identity', 'Memeriksa owner, ID environment, remote, nama container, dan proteksi baseline.');
        $userId = (int) $session->user_id;

        if ($userId <= 0) {
            throw new RuntimeException(
                'Cannot verify teardown: session owner is missing.'
            );
        }

        $victimContainer = 'victim-user-'.$userId;
        $attackerContainer = 'attacker-user-'.$userId;

        // Validate BOTH environments before changing any infrastructure.
        $this->assertOwnedEnvironment(
            $session->victimEnvironment,
            (string) $session->victim_remote,
            (string) $session->victim_container,
            $userId,
            $victimContainer,
        );

        $this->assertOwnedEnvironment(
            $session->attackerEnvironment,
            (string) $session->attacker_remote,
            (string) $session->attacker_container,
            $userId,
            $attackerContainer,
        );

        ProvisioningTimeline::record((int) $session->id, 'delete-firewall', 'Identitas pasangan cocok. Mencabut akses jaringan pasangan.');
        $this->firewall->revoke($session);
        ProvisioningTimeline::record((int) $session->id, 'delete-firewall', 'Akses jaringan pasangan dicabut.', 'success');

        ProvisioningTimeline::record((int) $session->id, 'delete-victim', 'Menghapus victim environment #'.$session->victim_environment_id.' melalui workspace yang diverifikasi.');
        $this->destroyOne(
            $session->victimEnvironment,
            (string) $session->victim_remote,
            (string) $session->victim_container,
            $userId,
            $victimContainer,
        );

        ProvisioningTimeline::record((int) $session->id, 'delete-victim', 'Victim terverifikasi tidak ada; workspace dibersihkan.', 'success');
        ProvisioningTimeline::record((int) $session->id, 'delete-attacker', 'Menghapus attacker environment #'.$session->attacker_environment_id.' melalui workspace yang diverifikasi.');
        $this->destroyOne(
            $session->attackerEnvironment,
            (string) $session->attacker_remote,
            (string) $session->attacker_container,
            $userId,
            $attackerContainer,
        );
        ProvisioningTimeline::record((int) $session->id, 'delete-attacker', 'Attacker terverifikasi tidak ada; workspace dibersihkan.', 'success');
    }

    private function assertOwnedEnvironment(
        ?LabEnvironment $environment,
        string $remote,
        string $container,
        int $userId,
        string $expectedContainer,
    ): void {
        if ($environment === null) {
            throw new RuntimeException(
                'Cannot verify teardown: environment record is missing.'
            );
        }

        if ($environment->isProtected()) {
            throw new RuntimeException(
                'Refusing to destroy a protected environment.'
            );
        }

        if (
            ! in_array(rtrim($remote, ':'), [rtrim((string) config('infrastructure.incus_remote'), ':'), rtrim((string) config('infrastructure.incus_attacker_remote'), ':')], true)
            || ($environment->incus_project ?: 'default') !== 'default'
            || $remote === ''
            || $container === ''
            || (int) $environment->user_id !== $userId
            || (string) $environment->incus_remote !== $remote
            || (string) $environment->container_name !== $container
            || $container !== $expectedContainer
        ) {
            throw new RuntimeException(
                'Session and environment container identity mismatch.'
            );
        }
    }

    private function destroyOne(
        ?LabEnvironment $environment,
        string $remote,
        string $container,
        int $userId,
        string $expectedContainer,
    ): void {
        $this->assertOwnedEnvironment(
            $environment,
            $remote,
            $container,
            $userId,
            $expectedContainer,
        );

        $this->tofu->reconcilePairedWorkspace($environment);
        $workspace = $this->tofu->workspaceFor($environment);

        if ($this->tofu->workspaceExists($workspace)) {
            // Never create a missing workspace during teardown.
            // Keep state until OpenTofu has destroyed its resources.
            $this->tofu->destroyWorkspaceResourcesIfPresent($environment);

            if (! $this->absence->isAbsent($remote, $container)) {
                throw new RuntimeException(
                    'Container remains present after infrastructure destroy.'
                );
            }

            // Only remove state after independent Incus verification.
            $this->tofu->cleanupWorkspaceIfPresent($environment);

            return;
        }

        // Idempotent retry after a previously completed cleanup.
        // Missing state is not sufficient proof of container absence.
        if (! $this->absence->isAbsent($remote, $container)) {
            throw new RuntimeException(
                'Container exists but its OpenTofu workspace is missing.'
            );
        }
    }
}
