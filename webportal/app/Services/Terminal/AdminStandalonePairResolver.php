<?php

namespace App\Services\Terminal;

use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use RuntimeException;

final class AdminStandalonePairResolver
{
    public function resolve(User $admin, int $pairId): array
    {
        if (! $admin->isAdmin() || ! $admin->isActive() || ! $admin->canOperateInfrastructure()) {
            throw new RuntimeException('Active administrator required.');
        }
        $pair = AdminEnvironmentPair::query()->find($pairId);
        if (! $pair || $pair->status !== 'READY') {
            throw new RuntimeException('Admin Pair is not ready.');
        }
        $attacker = LabEnvironment::query()->find($pair->attacker_environment_id);
        $victim = LabEnvironment::query()->find($pair->victim_environment_id);
        if (! $attacker || ! $victim || $attacker->getKey() === $victim->getKey()) {
            throw new RuntimeException('Incomplete Admin Pair.');
        }
        foreach (['attacker' => $attacker, 'victim' => $victim] as $role => $environment) {
            $expectedRemote = rtrim((string) config($role === 'attacker' ? 'infrastructure.incus_attacker_remote' : 'infrastructure.incus_remote'), ':');
            if ($environment->isProtected()
                || ! LabEnvironment::isValidContainerName((string) $environment->container_name)
                || (int) $environment->user_id !== (int) $pair->user_id
                || rtrim((string) $environment->incus_remote, ':') !== $expectedRemote
                || ! str_starts_with((string) $environment->container_name, $role.'-user-')
                || $environment->status !== EnvironmentStatus::Ready
                || $environment->runtime_status !== RuntimeStatus::Running) {
                throw new RuntimeException('Admin Pair '.$role.' identity, permission, or runtime mismatch.');
            }
        }
        return ['lab' => $pair, 'attacker' => $attacker, 'victim' => $victim];
    }
}
