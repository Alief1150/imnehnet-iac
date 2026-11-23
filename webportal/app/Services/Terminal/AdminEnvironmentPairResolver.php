<?php

namespace App\Services\Terminal;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use RuntimeException;

/**
 * Resolves an authorised attacker/victim pair.
 *
 * This class only validates and resolves targets.
 * It does not launch shells or change containers.
 */
final class AdminEnvironmentPairResolver
{
    /**
     * @return array{
     *     lab: LabSession,
     *     attacker: LabEnvironment,
     *     victim: LabEnvironment
     * }
     */
    public function resolve(User $admin, int $labId): array
    {
        if (
            ! $admin->isActive()
            || ! $admin->canOperateInfrastructure()
        ) {
            throw new RuntimeException(
                'Administrative infrastructure access is required.'
            );
        }

        $lab = LabSession::query()->find($labId);

        if (! $lab) {
            throw new RuntimeException(
                'Lab session not found.'
            );
        }

        if (
            ! $lab->attacker_environment_id
            || ! $lab->victim_environment_id
            || ! $lab->user_id
        ) {
            throw new RuntimeException(
                'Lab pairing is incomplete.'
            );
        }

        $attacker = LabEnvironment::query()
            ->find($lab->attacker_environment_id);

        $victim = LabEnvironment::query()
            ->find($lab->victim_environment_id);

        if (! $attacker || ! $victim) {
            throw new RuntimeException(
                'One or both paired containers are missing.'
            );
        }

        if ($attacker->getKey() === $victim->getKey()) {
            throw new RuntimeException(
                'Attacker and victim must be different containers.'
            );
        }

        // Verify student ownership using the application database.
        $owner = User::query()->find($lab->user_id);

        if (! $owner) {
            throw new RuntimeException(
                'The student account cannot be verified.'
            );
        }

        foreach (
            [
                'attacker' => $attacker,
                'victim' => $victim,
            ] as $type => $environment
        ) {
            if (
                $environment->user_id === null
                || (int) $environment->user_id !== (int) $lab->user_id
            ) {
                throw new RuntimeException(
                    "Ownership mismatch for {$type}."
                );
            }

            if ($environment->isProtected()) {
                throw new RuntimeException(
                    'Protected environments cannot be accessed.'
                );
            }

            if (
                $environment->status !== EnvironmentStatus::Ready
                || $environment->runtime_status !== RuntimeStatus::Running
            ) {
                throw new RuntimeException(
                    "The {$type} container is not ready and running."
                );
            }
        }

        $this->verifyTarget(
            $attacker,
            (string) $lab->attacker_container,
            (string) $lab->attacker_remote,
            (string) config(
                'infrastructure.incus_attacker_remote',
                'lab-atk'
            )
        );

        $this->verifyTarget(
            $victim,
            (string) $lab->victim_container,
            (string) $lab->victim_remote,
            (string) config(
                'infrastructure.incus_remote',
                'lab-env'
            )
        );

        return [
            'lab' => $lab,
            'attacker' => $attacker,
            'victim' => $victim,
        ];
    }

    private function verifyTarget(
        LabEnvironment $environment,
        string $expectedName,
        string $expectedRemote,
        string $configuredRemote
    ): void {
        $actualRemote = rtrim(
            (string) $environment->incus_remote,
            ':'
        );

        if (
            ! LabEnvironment::isValidContainerName(
                (string) $environment->container_name
            )
            || $environment->container_name !== $expectedName
            || $actualRemote !== rtrim($expectedRemote, ':')
            || $actualRemote !== rtrim($configuredRemote, ':')
        ) {
            throw new RuntimeException(
                'Container identity or Incus remote mismatch.'
            );
        }
    }
}
