<?php

namespace App\Services;

// IMNEHNET_ADMIN_REFRESH_INCUS_V1

use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\User;
use DomainException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AdminPairIncusStatusRefresher
{
    public function refresh(
        User $actor,
        LabEnvironment $record
    ): RuntimeStatus {
        if (! $actor->isAdmin() || ! $actor->isActive()) {
            throw new DomainException('Unauthorized refresh.');
        }

        $pair = AdminEnvironmentPair::forEnvironment(
            (int) $record->getKey()
        );

        if (! $pair) {
            throw new DomainException('Not an Admin Pair environment.');
        }

        if (
            ! $actor->canOperateInfrastructure()
            || $pair->status !== 'READY'
        ) {
            throw new DomainException(
                'Pair ownership mismatch or lifecycle is busy.'
            );
        }

        $isAttacker = (int) $record->getKey()
            === (int) $pair->attacker_environment_id;

        $isVictim = (int) $record->getKey()
            === (int) $pair->victim_environment_id;

        if (! $isAttacker && ! $isVictim) {
            throw new DomainException('Invalid Pair member.');
        }

        $role = $isAttacker ? 'attacker' : 'victim';
        $remote = $isAttacker ? 'lab-atk' : 'lab-env';

        $expectedName = $role
            .'-user-'.$pair->user_id
            .'-p'.$pair->id;

        if (
            $record->isProtected()
            || (int) $record->user_id !== (int) $pair->user_id
            || (string) $record->container_name !== $expectedName
            || rtrim((string) $record->incus_remote, ':') !== $remote
            || ($record->incus_project ?: 'default') !== 'default'
            || (string) $record->tofu_workspace
                !== 'admin-'.$role.'-p'.$pair->id
        ) {
            throw new DomainException(
                'Unsafe container identity. Refresh refused.'
            );
        }

        $target = $remote.':'.$expectedName;

        // Read-only: no OpenTofu, start, stop, or delete.
        $process = new Process(['incus', 'info', $target]);
        $process->setTimeout(25);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Unable to read Incus status for '.$target
            );
        }

        if (! preg_match(
            '/^Status:\s*(RUNNING|STOPPED)\s*$/mi',
            $process->getOutput(),
            $matches
        )) {
            throw new RuntimeException(
                'Unexpected Incus status. Database unchanged.'
            );
        }

        $runtime = strtoupper($matches[1]) === 'RUNNING'
            ? RuntimeStatus::Running
            : RuntimeStatus::Stopped;

        // Only synchronize runtime status.
        // Do not modify provisioning status or pair lifecycle.
        $record->forceFill([
            'runtime_status' => $runtime,
        ])->save();

        return $runtime;
    }
}
