<?php

namespace App\Services;

use App\Jobs\DeleteAdminEnvironmentPair;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\User;
use DomainException;

final class AdminEnvironmentPairDeleter
{
    public function request(User $actor, LabEnvironment $environment): void
    {
        if (! $actor->isAdmin() || ! $actor->isActive() || ! $actor->canOperateInfrastructure()) {
            throw new DomainException('Active admin is required.');
        }
        $pair = AdminEnvironmentPair::forEnvironment((int) $environment->getKey());
        if (!$pair || !in_array($pair->status, ['READY', 'ERROR'], true)) {
            throw new DomainException('Pair must be READY before delete can be queued.');
        }
        $attacker = LabEnvironment::query()->find($pair->attacker_environment_id);
        $victim = LabEnvironment::query()->find($pair->victim_environment_id);
        if (!$attacker || !$victim || $attacker->isProtected() || $victim->isProtected()
            || (int) $attacker->user_id !== (int) $pair->user_id
            || (int) $victim->user_id !== (int) $pair->user_id) {
            throw new DomainException('Paired inventory ownership validation failed.');
        }
        if (!$attacker->isSettled() || !$victim->isSettled()) {
            throw new DomainException('Wait until both environments finish their current operations.');
        }
        $previous = [
            [$attacker, $attacker->status, $attacker->runtime_status],
            [$victim, $victim->status, $victim->runtime_status],
        ];
        $pair->forceFill(['status' => 'DELETING'])->save();
        foreach ([$attacker, $victim] as $env) {
            $env->forceFill([
                'status' => EnvironmentStatus::Deleting,
                'runtime_status' => RuntimeStatus::Unknown,
            ])->save();
        }
        try {
            DeleteAdminEnvironmentPair::dispatch((int) $pair->getKey());
            // IMNEHNET_AUDIT_REPAIR_V3
            // A queued request is not a completed operation.
            try {
                app(\App\Services\ActivityLogService::class)
                    ->record(
                        $actor,
                        'ADMIN_PAIR_DELETE_REQUESTED',
                        $environment,
                        'requested',
                        'Admin Pair #' . $pair->getKey() . ': DELETE requested'
                    );
            } catch (\Throwable $auditError) {
                report($auditError);
            }

        } catch (\Throwable $error) {
            foreach ($previous as [$env, $status, $runtime]) {
                $env->forceFill(['status' => $status, 'runtime_status' => $runtime])->save();
            }
            $pair->forceFill(['status' => 'ERROR', 'last_error' => 'Delete queue dispatch failed.'])->save();
            throw $error;
        }
    }
}
