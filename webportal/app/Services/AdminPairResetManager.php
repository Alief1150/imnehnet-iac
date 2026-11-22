<?php

namespace App\Services;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\ResetAdminEnvironmentPair;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AdminPairResetManager
{
    public function request(User $actor, LabEnvironment $record): void
    {
        if (! $actor->isAdmin() || ! $actor->isActive()) {
            throw new DomainException('Active admin required.');
        }
        $pair = AdminEnvironmentPair::forEnvironment((int) $record->getKey());
        if (! $pair) {
            throw new DomainException('Admin Pair not found.');
        }
        $id = (int) $pair->getKey();
        DB::connection($pair->getConnectionName())->transaction(function () use ($actor, $record, $id): void {
            $pair = AdminEnvironmentPair::query()->lockForUpdate()->findOrFail($id);
            if ($pair->status !== 'READY') {
                throw new DomainException('Pair must be READY for reset.');
            }
            if ((int) $pair->attacker_environment_id !== (int) $record->getKey()) {
                throw new DomainException('Reset only from attacker row.');
            }
            $a = LabEnvironment::query()->findOrFail($pair->attacker_environment_id);
            $v = LabEnvironment::query()->findOrFail($pair->victim_environment_id);
            if (! $actor->canOperateInfrastructure()
                || (int) $a->user_id !== (int) $pair->user_id
                || (int) $v->user_id !== (int) $pair->user_id
                || $a->isProtected() || $v->isProtected()
                || $a->container_name !== "attacker-user-{$pair->user_id}-p{$id}"
                || $v->container_name !== "victim-user-{$pair->user_id}-p{$id}"
                || $a->tofu_workspace !== "admin-attacker-p{$id}"
                || $v->tofu_workspace !== "admin-victim-p{$id}"
                || rtrim((string) $a->incus_remote, ':') !== 'lab-atk'
                || rtrim((string) $v->incus_remote, ':') !== 'lab-env'
                || $a->incus_project !== 'default' && $a->incus_project !== null
                || $v->incus_project !== 'default' && $v->incus_project !== null
                || $a->status !== EnvironmentStatus::Ready
                || $v->status !== EnvironmentStatus::Ready
                || $a->runtime_status !== RuntimeStatus::Running
                || $v->runtime_status !== RuntimeStatus::Running) {
                throw new DomainException('Pair identity or settled runtime mismatch.');
            }
            if (LabSession::query()->whereIn('attacker_environment_id', [$a->id, $v->id])
                ->orWhereIn('victim_environment_id', [$a->id, $v->id])->exists()) {
                throw new DomainException('Pair linked to Student LabSession.');
            }
            if (TerminalSession::query()->where('purpose', 'admin_lab_pair')
                ->whereIn('lab_environment_id', [$a->id, $v->id])
                ->whereNull('closed_at')->exists()) {
                throw new DomainException('Close both Admin Pair terminal sessions first.');
            }
            $pair->forceFill(['status' => 'RESETTING', 'last_error' => null])->save();
            foreach ([$a, $v] as $env) {
                $env->forceFill(['status' => EnvironmentStatus::Resetting,
                    'runtime_status' => RuntimeStatus::Unknown, 'last_error' => null])->save();
            }
        });
        try {
            ResetAdminEnvironmentPair::dispatch($id);
            // IMNEHNET_AUDIT_REPAIR_V3
            // A queued request is not a completed operation.
            try {
                app(\App\Services\ActivityLogService::class)
                    ->record(
                        $actor,
                        'ADMIN_PAIR_RESET_REQUESTED',
                        $record,
                        'requested',
                        'Admin Pair #' . $id . ': RESET requested'
                    );
            } catch (\Throwable $auditError) {
                report($auditError);
            }

        } catch (\Throwable $e) {
            AdminEnvironmentPair::query()->find($id)?->forceFill([
                'status' => 'ERROR', 'last_error' => 'Reset dispatch failed.',
            ])->save();
            throw $e;
        }
    }
}
