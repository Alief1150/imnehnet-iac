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

        $this->firewall->revoke($session);

        $this->destroyOne(
            $session->victimEnvironment,
            (string) $session->victim_remote,
            (string) $session->victim_container,
            $userId,
            $victimContainer,
        );

        $this->destroyOne(
            $session->attackerEnvironment,
            (string) $session->attacker_remote,
            (string) $session->attacker_container,
            $userId,
            $attackerContainer,
        );
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
            $remote === ''
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
