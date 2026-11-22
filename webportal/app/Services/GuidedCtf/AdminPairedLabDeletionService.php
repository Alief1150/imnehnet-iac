<?php

namespace App\Services\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Jobs\DeleteLabSession;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use DomainException;
use Throwable;

final class AdminPairedLabDeletionService
{
    /**
     * Administrative entrypoint for coordinated paired-lab deletion.
     * Never destroys infrastructure inside an HTTP request.
     */
    public function request(User $admin, LabEnvironment $attacker): int
    {
        if (! $admin->isActive() || ! $admin->canOperateInfrastructure()) {
            throw new DomainException('Akses admin tidak diizinkan.');
        }

        if ($attacker->isProtected()) {
            throw new DomainException('Protected environment tidak boleh dihapus.');
        }

        $lab = LabSession::query()
            ->where('attacker_environment_id', $attacker->getKey())
            ->where('status', '!=', LabSessionStatus::Deleted->value)
            ->first();

        if (! $lab) {
            throw new DomainException('Paired lab tidak ditemukan.');
        }

        $userId = (int) $lab->user_id;
        $labId = (int) $lab->getKey();

        if ($userId <= 0) {
            throw new DomainException('Pemilik lab tidak valid.');
        }

        return Cache::lock(
            'imnehnet:ctf:user-start:'.$userId,
            60
        )->block(15, function () use ($admin, $labId, $userId): int {
            return Cache::lock(
                'imnehnet:ctf:session-lifecycle:'.$labId,
                2100
            )->block(15, function () use ($admin, $labId, $userId): int {
                $lab = LabSession::query()->findOrFail($labId);

                if ((int) $lab->user_id !== $userId) {
                    throw new DomainException('Identitas pemilik berubah.');
                }

                if (! in_array($lab->status, [
                    LabSessionStatus::Ready,
                    LabSessionStatus::Running,
                    LabSessionStatus::Completed,
                    LabSessionStatus::Stopped,
                    LabSessionStatus::Failed,
                ], true)) {
                    throw new DomainException(
                        'Lab sedang menjalankan operasi lain atau tidak siap dihapus.'
                    );
                }

                $attacker = $lab->attackerEnvironment;
                $victim = $lab->victimEnvironment;

                if (! $attacker || ! $victim) {
                    throw new DomainException(
                        'Data pasangan tidak lengkap. Perlu pemulihan manual.'
                    );
                }

                if (
                    $attacker->isProtected()
                    || $victim->isProtected()
                    || ($attacker->user_id !== null && (int) $attacker->user_id !== $userId)
                    || ($victim->user_id !== null && (int) $victim->user_id !== $userId)
                    || (($attacker->user_id === null || $victim->user_id === null)
                        && $lab->status !== LabSessionStatus::Failed)
                    || ($attacker->user_id === null && ! in_array($attacker->status, [
                        \App\Enums\EnvironmentStatus::Provisioning,
                        \App\Enums\EnvironmentStatus::Configuring,
                        \App\Enums\EnvironmentStatus::Error,
                    ], true))
                    || ($victim->user_id === null && ! in_array($victim->status, [
                        \App\Enums\EnvironmentStatus::Provisioning,
                        \App\Enums\EnvironmentStatus::Configuring,
                        \App\Enums\EnvironmentStatus::Error,
                    ], true))
                    || ($attacker->incus_project ?: 'default') !== 'default'
                    || ($victim->incus_project ?: 'default') !== 'default'
                    || (string) $lab->attacker_container !== 'attacker-user-'.$userId
                    || (string) $lab->victim_container !== 'victim-user-'.$userId
                    || (string) $attacker->tofu_workspace === ''
                    || (string) $victim->tofu_workspace === ''
                    || $attacker->getKey() === $victim->getKey()
                    || (string) $attacker->container_name !== 'attacker-user-'.$userId
                    || (string) $victim->container_name !== 'victim-user-'.$userId
                    || rtrim((string) $attacker->incus_remote, ':')
                        !== rtrim((string) $lab->attacker_remote, ':')
                    || rtrim((string) $victim->incus_remote, ':')
                        !== rtrim((string) $lab->victim_remote, ':')
                ) {
                    throw new DomainException(
                        'Validasi identitas attacker/victim gagal.'
                    );
                }

                // Block ambiguous cross-session ownership.
                if (LabSession::query()
                    ->where('id', '!=', $labId)
                    ->where(function ($query) use ($attacker, $victim): void {
                        $ids = [$attacker->getKey(), $victim->getKey()];
                        $query->whereIn('attacker_environment_id', $ids)
                            ->orWhereIn('victim_environment_id', $ids);
                    })->exists()) {
                    throw new DomainException('Environment direferensikan sesi lain.');
                }

                // IMNEHNET_DELETE_STATUS_V13
                $originalAttackerOwner = $attacker->user_id;
                $originalVictimOwner = $victim->user_id;
                $previousStatus = $lab->status;
                $attackerPreviousStatus = $attacker->status;
                $victimPreviousStatus = $victim->status;

                try {
                    // Recover ownership only for the verified FAILED pair.
                    // The existing teardown still checks container, remote,
                    // project, workspace and ownership before destruction.
                    foreach ([$attacker, $victim] as $environment) {
                        if ($environment->user_id !== null) {
                            continue;
                        }

                        $claimed = LabEnvironment::query()
                            ->whereKey($environment->getKey())
                            ->whereNull('user_id')
                            ->whereIn('status', [
                                \App\Enums\EnvironmentStatus::Provisioning->value,
                                \App\Enums\EnvironmentStatus::Configuring->value,
                                \App\Enums\EnvironmentStatus::Error->value,
                            ])
                            ->update(['user_id' => $userId]);

                        if ($claimed !== 1) {
                            throw new DomainException('Ownership changed during cleanup.');
                        }

                        $environment->user_id = $userId;
                    }

                    $lab->forceFill([
                        'status' => LabSessionStatus::Deleting,
                        'last_error' => null,
                    ])->save();

                    // Make both rows visibly DELETING before queue dispatch.
                    $attacker->forceFill([
                        'status' => \App\Enums\EnvironmentStatus::Deleting,
                    ])->save();

                    $victim->forceFill([
                        'status' => \App\Enums\EnvironmentStatus::Deleting,
                    ])->save();

                    // IMNEHNET_ADMIN_DELETE_NOTIFICATION_V11
                    Queue::push(new DeleteLabSession($labId, true));
                } catch (Throwable $e) {
                    // Restore only statuses changed by this request.
                    foreach ([
                        [$attacker, $attackerPreviousStatus],
                        [$victim, $victimPreviousStatus],
                    ] as [$environment, $oldStatus]) {
                        \App\Models\LabEnvironment::query()
                            ->whereKey($environment->getKey())
                            ->where(
                                'status',
                                \App\Enums\EnvironmentStatus::Deleting->value
                            )
                            ->update(['status' => $oldStatus->value]);
                    }

                    LabSession::query()
                        ->whereKey($labId)
                        ->where('status', LabSessionStatus::Deleting->value)
                        ->update(['status' => $previousStatus->value]);

                    foreach ([
                        [$attacker, $originalAttackerOwner],
                        [$victim, $originalVictimOwner],
                    ] as [$environment, $oldOwner]) {
                        if ($oldOwner === null) {
                            LabEnvironment::query()
                                ->whereKey($environment->getKey())
                                ->where('user_id', $userId)
                                ->update(['user_id' => null]);
                        }
                    }

                    throw $e;
                }

                \Illuminate\Support\Facades\Log::notice(
                    'ADMIN_DELETE_PAIR_QUEUED',
                    [
                        'admin_id' => $admin->getKey(),
                        'student_id' => $userId,
                        'lab_session_id' => $labId,
                    ]
                );

                return $labId;
            });
        });
    }
}
