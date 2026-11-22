<?php
namespace App\Services;

// IMNEHNET_SHARED_ADMIN_V11

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\OperateAdminPairRuntime;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AdminPairRuntimeManager
{
    public function request(User $actor, LabEnvironment $record, string $operation): void
    {
        if (!in_array($operation, ['start', 'stop'], true) || !$actor->isAdmin() || !$actor->isActive()) {
            throw new DomainException('Unauthorized pair runtime operation.');
        }
        $pairId = AdminEnvironmentPair::forEnvironment((int) $record->getKey())?->getKey();
        if (!$pairId) {
            throw new DomainException('Managed Admin Pair not found.');
        }
        $pair = AdminEnvironmentPair::query()->findOrFail($pairId);
        $connection = DB::connection($pair->getConnectionName());
        $connection->transaction(function () use ($actor, $pairId, $record): void {
            $pair = AdminEnvironmentPair::query()->lockForUpdate()->findOrFail($pairId);
            if (!in_array($pair->status, ['READY', 'ERROR'], true)) {
                throw new DomainException('Pair operation already in progress or unavailable.');
            }
            if ((int) $pair->attacker_environment_id !== (int) $record->getKey()) {
                throw new DomainException('Pair runtime actions must originate from Attacker row.');
            }
            $a = LabEnvironment::query()->findOrFail($pair->attacker_environment_id);
            $v = LabEnvironment::query()->findOrFail($pair->victim_environment_id);
            if (! $actor->canOperateInfrastructure()
                || (int) $a->user_id !== (int) $pair->user_id
                || (int) $v->user_id !== (int) $pair->user_id
                || $a->isProtected() || $v->isProtected()
                || $a->container_name !== 'attacker-user-'.$pair->user_id.'-p'.$pair->id
                || $v->container_name !== 'victim-user-'.$pair->user_id.'-p'.$pair->id
                || rtrim((string) $a->incus_remote, ':') !== 'lab-atk'
                || rtrim((string) $v->incus_remote, ':') !== 'lab-env') {
                throw new DomainException('Pair ownership or plane identity mismatch.');
            }
            if (LabSession::query()->whereIn('attacker_environment_id', [$a->id, $v->id])
                ->orWhereIn('victim_environment_id', [$a->id, $v->id])->exists()) {
                throw new DomainException('Student LabSession references this pair.');
            }
            $pair->forceFill(['status' => 'OPERATING', 'last_error' => null])->save();
            foreach ([$a, $v] as $env) {
                $env->forceFill(['status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown, 'last_error' => null])->save();
            }
        });
        try {
            OperateAdminPairRuntime::dispatch((int) $pairId, $operation);
            // IMNEHNET_AUDIT_REPAIR_V3
            // A queued request is not a completed operation.
            try {
                app(\App\Services\ActivityLogService::class)
                    ->record(
                        $actor,
                        'ADMIN_PAIR_' . strtoupper($operation) . '_REQUESTED',
                        $record,
                        'requested',
                        'Admin Pair #' . $pairId . ': ' . strtoupper($operation) . ' requested'
                    );
            } catch (\Throwable $auditError) {
                report($auditError);
            }

        } catch (\Throwable $e) {
            $pair = AdminEnvironmentPair::query()->find($pairId);
            $pair?->forceFill(['status' => 'ERROR', 'last_error' => 'Queue dispatch failed; inspect runtime.'])->save();
            throw $e;
        }
    }
}
