<?php

namespace App\Services;

// IMNEHNET_PAIR_REFRESH_V2

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PairIncusStatusRefresher
{
    public function refresh(User $actor, LabEnvironment $record): array
    {
        if (! $actor->isAdmin() || ! $actor->isActive()) {
            throw new DomainException('Unauthorized Pair Refresh.');
        }

        $pair = AdminEnvironmentPair::forEnvironment(
            (int) $record->getKey()
        );

        if ($pair !== null) {
            return $this->refreshAdmin($actor, $record, $pair);
        }

        return $this->refreshStudent($record);
    }

    private function refreshAdmin(
        User $actor,
        LabEnvironment $record,
        AdminEnvironmentPair $pair
    ): array {
        if (
            ! $actor->canOperateInfrastructure()
            || (int) $pair->attacker_environment_id
                !== (int) $record->getKey()
            || $pair->status !== 'READY'
        ) {
            throw new DomainException(
                'Admin Pair identity or lifecycle mismatch.'
            );
        }

        $attacker = LabEnvironment::findOrFail(
            $pair->attacker_environment_id
        );

        $victim = LabEnvironment::findOrFail(
            $pair->victim_environment_id
        );

        foreach ([
            ['attacker', $attacker, 'lab-atk'],
            ['victim', $victim, 'lab-env'],
        ] as [$role, $environment, $remote]) {
            if (
                $environment->isProtected()
                || (int) $environment->user_id
                    !== (int) $pair->user_id
                || $environment->container_name
                    !== "{$role}-user-{$pair->user_id}-p{$pair->id}"
                || rtrim((string) $environment->incus_remote, ':')
                    !== $remote
                || ($environment->incus_project ?: 'default')
                    !== 'default'
                || $environment->tofu_workspace
                    !== "admin-{$role}-p{$pair->id}"
            ) {
                throw new DomainException(
                    'Unsafe Admin Pair identity.'
                );
            }
        }

        $observed = $this->observeBoth($attacker, $victim);

        $this->commitBoth(
            $attacker,
            $victim,
            $observed,
            function () use ($pair, $actor): void {
                $fresh = AdminEnvironmentPair::findOrFail($pair->id);

                if (
                    $fresh->status !== 'READY'
                    || (int) $fresh->user_id !== (int) $pair->user_id
                    // IMNEHNET_SHARED_REFRESH_FIX_V12
                    || ! $actor->isAdmin()
                    || ! $actor->isActive()
                    || ! $actor->canOperateInfrastructure()
                    || (int) $fresh->attacker_environment_id
                        !== (int) $pair->attacker_environment_id
                    || (int) $fresh->victim_environment_id
                        !== (int) $pair->victim_environment_id
                ) {
                    throw new DomainException(
                        'Admin Pair changed during refresh.'
                    );
                }
            }
        );

        return $observed;
    }

    private function refreshStudent(LabEnvironment $record): array
    {
        $id = (int) $record->getKey();

        $sessions = LabSession::query()
            ->where(function ($query) use ($id) {
                $query->where('attacker_environment_id', $id)
                    ->orWhere('victim_environment_id', $id);
            })
            ->where('status', '!=', 'DELETED')
            ->get();

        if ($sessions->count() !== 1) {
            throw new DomainException(
                'Expected exactly one Student LabSession.'
            );
        }

        $session = $sessions->first();

        if (
            (int) $session->attacker_environment_id !== $id
            || ! in_array($this->statusValue($session->status), [
                'READY', 'RUNNING', 'STOPPED', 'COMPLETED',
            ], true)
        ) {
            throw new DomainException(
                'Refresh must originate from an active Student Attacker.'
            );
        }

        $userId = (int) $session->user_id;

        if ($userId <= 0) {
            throw new DomainException('Invalid Student owner.');
        }

        $attacker = LabEnvironment::findOrFail(
            $session->attacker_environment_id
        );

        $victim = LabEnvironment::findOrFail(
            $session->victim_environment_id
        );

        foreach ([
            ['attacker', $attacker, 'lab-atk',
                $session->attacker_container,
                $session->attacker_remote],
            ['victim', $victim, 'lab-env',
                $session->victim_container,
                $session->victim_remote],
        ] as [$role, $environment, $remote, $sessionName, $sessionRemote]) {
            $name = "{$role}-user-{$userId}";

            if (
                $environment->isProtected()
                || (int) $environment->user_id !== $userId
                || $environment->container_name !== $name
                || (string) $sessionName !== $name
                || rtrim((string) $environment->incus_remote, ':')
                    !== $remote
                || rtrim((string) $sessionRemote, ':') !== $remote
                || ($environment->incus_project ?: 'default')
                    !== 'default'
                || ! preg_match(
                    '/^ctf-'.$role.'-[0-9]+$/D',
                    (string) $environment->tofu_workspace
                )
            ) {
                throw new DomainException(
                    'Unsafe Student Pair identity.'
                );
            }
        }

        $observed = $this->observeBoth($attacker, $victim);

        $this->commitBoth(
            $attacker,
            $victim,
            $observed,
            function () use ($session): void {
                $fresh = LabSession::findOrFail($session->id);

                if (
                    (int) $fresh->user_id !== (int) $session->user_id
                    || (int) $fresh->attacker_environment_id
                        !== (int) $session->attacker_environment_id
                    || (int) $fresh->victim_environment_id
                        !== (int) $session->victim_environment_id
                    || ! in_array($this->statusValue($fresh->status), [
                        'READY', 'RUNNING', 'STOPPED', 'COMPLETED',
                    ], true)
                ) {
                    throw new DomainException(
                        'Student LabSession changed during refresh.'
                    );
                }
            }
        );

        return $observed;
    }

    private function observeBoth(
        LabEnvironment $attacker,
        LabEnvironment $victim
    ): array {
        // No database changes unless BOTH Incus reads succeed.
        return [
            'Attacker' => $this->inspect($attacker),
            'Victim' => $this->inspect($victim),
        ];
    }

    private function inspect(LabEnvironment $environment): RuntimeStatus
    {
        $target = rtrim((string) $environment->incus_remote, ':')
            .':'.$environment->container_name;

        // Read-only Incus command.
        $process = new Process(['incus', 'info', $target]);
        $process->setTimeout(25);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Cannot inspect '.$target.'. No status was updated.'
            );
        }

        if (! preg_match(
            '/^Status:\s*(RUNNING|STOPPED|FROZEN)\s*$/mi',
            $process->getOutput(),
            $match
        )) {
            throw new RuntimeException(
                'Unexpected Incus state for '.$target
            );
        }

        return match (strtoupper($match[1])) {
            'RUNNING' => RuntimeStatus::Running,
            'STOPPED' => RuntimeStatus::Stopped,
            'FROZEN' => RuntimeStatus::Frozen,
        };
    }

    private function commitBoth(
        LabEnvironment $attacker,
        LabEnvironment $victim,
        array $observed,
        callable $verifyPair
    ): void {
        $connection = (string) config(
            'infrastructure.environment_connection',
            'imnehnet'
        );

        DB::connection($connection)->transaction(function () use (
            $attacker,
            $victim,
            $observed,
            $verifyPair
        ): void {
            $verifyPair();

            $records = [
                'Attacker' => $attacker,
                'Victim' => $victim,
            ];

            $locked = LabEnvironment::query()
                ->whereIn('id', [
                    $attacker->id,
                    $victim->id,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($locked->count() !== 2) {
                throw new DomainException('Pair members changed.');
            }

            foreach ($records as $role => $original) {
                $fresh = $locked->get($original->id);

                if (
                    ! $fresh
                    || (int) $fresh->user_id
                        !== (int) $original->user_id
                    || $fresh->container_name
                        !== $original->container_name
                    || $fresh->incus_remote
                        !== $original->incus_remote
                    || $fresh->tofu_workspace
                        !== $original->tofu_workspace
                    || ! in_array($fresh->status, [
                        EnvironmentStatus::Ready,
                        EnvironmentStatus::Stopped,
                    ], true)
                ) {
                    throw new DomainException(
                        'Environment lifecycle changed. Refresh cancelled.'
                    );
                }
            }

            foreach ($records as $role => $original) {
                $locked->get($original->id)->forceFill([
                    'runtime_status' => $observed[$role],
                ])->save();
            }
        });
    }

    private function statusValue(mixed $value): string
    {
        return $value instanceof \BackedEnum
            ? (string) $value->value
            : (string) $value;
    }
}
