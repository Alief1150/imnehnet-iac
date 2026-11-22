<?php

namespace App\Services;

// IMNEHNET_STUDENT_REFRESH_INCUS_V1

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use DomainException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class StudentIncusStatusRefresher
{
    public function refresh(
        User $actor,
        LabEnvironment $record
    ): RuntimeStatus {
        if (! $actor->isAdmin() || ! $actor->isActive()) {
            throw new DomainException('Unauthorized Student refresh.');
        }

        if (AdminEnvironmentPair::forEnvironment(
            (int) $record->getKey()
        ) !== null) {
            throw new DomainException('Admin Pair cannot use Student refresh.');
        }

        if ($record->isProtected()) {
            throw new DomainException('Protected environment.');
        }

        if (! in_array($record->status, [
            EnvironmentStatus::Ready,
            EnvironmentStatus::Stopped,
        ], true)) {
            throw new DomainException(
                'Environment is not in a refreshable lifecycle state.'
            );
        }

        $environmentId = (int) $record->getKey();

        $labs = LabSession::query()
            ->where(function ($query) use ($environmentId): void {
                $query
                    ->where('attacker_environment_id', $environmentId)
                    ->orWhere('victim_environment_id', $environmentId);
            })
            ->whereIn('status', [
                'READY',
                'RUNNING',
                'STOPPED',
                'COMPLETED',
            ])
            ->get();

        if ($labs->count() !== 1) {
            throw new DomainException(
                'Expected exactly one active Student LabSession.'
            );
        }

        $lab = $labs->first();
        $userId = (int) $lab->user_id;

        if ($userId <= 0 || (int) $record->user_id !== $userId) {
            throw new DomainException('Student ownership mismatch.');
        }

        $isAttacker = (int) $lab->attacker_environment_id
            === $environmentId;

        $isVictim = (int) $lab->victim_environment_id
            === $environmentId;

        if ($isAttacker === $isVictim) {
            throw new DomainException('Invalid Student member identity.');
        }

        $role = $isAttacker ? 'attacker' : 'victim';
        $remote = $isAttacker ? 'lab-atk' : 'lab-env';
        $name = $role.'-user-'.$userId;
        $sessionName = $isAttacker
            ? $lab->attacker_container
            : $lab->victim_container;

        $sessionRemote = $isAttacker
            ? $lab->attacker_remote
            : $lab->victim_remote;

        $workspace = (string) $record->tofu_workspace;

        if (
            (string) $record->container_name !== $name
            || (string) $sessionName !== $name
            || rtrim((string) $record->incus_remote, ':') !== $remote
            || rtrim((string) $sessionRemote, ':') !== $remote
            || ($record->incus_project ?: 'default') !== 'default'
            || ! preg_match(
                '/^ctf-'.$role.'-[0-9]+$/D',
                $workspace
            )
        ) {
            throw new DomainException(
                'Student container identity mismatch.'
            );
        }

        // READ-ONLY Incus operation.
        // Never select OpenTofu workspaces here.
        $target = $remote.':'.$name;

        $process = new Process([
            'incus',
            'info',
            $target,
        ]);

        $process->setTimeout(25);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Cannot read actual Incus state for '.$target
            );
        }

        if (! preg_match(
            '/^Status:\s*(RUNNING|STOPPED|FROZEN)\s*$/mi',
            $process->getOutput(),
            $match
        )) {
            throw new RuntimeException(
                'Unrecognized Incus state. Database unchanged.'
            );
        }

        $runtime = match (strtoupper($match[1])) {
            'RUNNING' => RuntimeStatus::Running,
            'STOPPED' => RuntimeStatus::Stopped,
            'FROZEN' => RuntimeStatus::Frozen,
        };

        // Revalidate the session and environment after Incus observation.
        // Never overwrite a concurrent provisioning/reset/delete transition.
        $freshLab = LabSession::query()
            ->find($lab->getKey());

        if (
            ! $freshLab
            || ! in_array(
                $freshLab->status instanceof \BackedEnum
                    ? $freshLab->status->value
                    : (string) $freshLab->status,
                ['READY', 'RUNNING', 'STOPPED', 'COMPLETED'],
                true
            )
            || (int) $freshLab->user_id !== $userId
            || (int) (
                $isAttacker
                    ? $freshLab->attacker_environment_id
                    : $freshLab->victim_environment_id
            ) !== $environmentId
        ) {
            throw new DomainException(
                'Student session changed during refresh.'
            );
        }

        $updated = LabEnvironment::query()
            ->whereKey($environmentId)
            ->where('user_id', $userId)
            ->where('container_name', $name)
            ->where('incus_remote', $remote)
            ->where('tofu_workspace', $workspace)
            ->whereIn('status', [
                EnvironmentStatus::Ready->value,
                EnvironmentStatus::Stopped->value,
            ])
            ->update([
                'runtime_status' => $runtime->value,
            ]);

        if ($updated !== 1) {
            throw new DomainException(
                'Environment changed during refresh; status not updated.'
            );
        }

        return $runtime;
    }
}
