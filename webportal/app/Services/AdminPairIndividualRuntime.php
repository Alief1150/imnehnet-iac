<?php

namespace App\Services;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\OperateAdminPairMember;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AdminPairIndividualRuntime
{
    public function request(
        User $actor,
        LabEnvironment $record,
        string $operation
    ): void {
        if (
            ! in_array($operation, ['start', 'stop'], true)
            || ! $actor->isAdmin()
            || ! $actor->isActive()
        ) {
            throw new DomainException('Unauthorized runtime operation.');
        }

        $pair = AdminEnvironmentPair::forEnvironment(
            (int) $record->getKey()
        );

        if (! $pair) {
            throw new DomainException('Admin Pair not found.');
        }

        $pairId = (int) $pair->getKey();
        $envId = (int) $record->getKey();

        DB::connection($pair->getConnectionName())
            ->transaction(function () use (
                $actor,
                $pairId,
                $envId,
                $operation
            ): void {
                $pair = AdminEnvironmentPair::query()
                    ->lockForUpdate()
                    ->findOrFail($pairId);

                if ($pair->status !== 'READY') {
                    throw new DomainException(
                        'Pair is busy or unavailable.'
                    );
                }

                if ((int) $pair->user_id !== (int) $actor->getKey()) {
                    throw new DomainException('Pair ownership mismatch.');
                }

                $attacker = LabEnvironment::query()
                    ->findOrFail($pair->attacker_environment_id);

                $victim = LabEnvironment::query()
                    ->findOrFail($pair->victim_environment_id);

                OperateAdminPairMember::assertOwnership(
                    $pair,
                    $attacker,
                    $victim
                );

                if (
                    ! in_array($envId, [
                        (int) $attacker->getKey(),
                        (int) $victim->getKey(),
                    ], true)
                ) {
                    throw new DomainException('Invalid pair member.');
                }

                $target = $envId === (int) $attacker->getKey()
                    ? $attacker
                    : $victim;

                if ($target->status !== EnvironmentStatus::Ready) {
                    throw new DomainException(
                        'Environment is not ready.'
                    );
                }

                $expected = $operation === 'stop'
                    ? RuntimeStatus::Running
                    : RuntimeStatus::Stopped;

                if ($target->runtime_status !== $expected) {
                    throw new DomainException(
                        'Environment runtime status mismatch.'
                    );
                }

                if (
                    $operation === 'stop'
                    && TerminalSession::query()
                        ->where('purpose', 'admin_lab_pair')
                        ->where('lab_environment_id', $target->id)
                        ->whereNull('closed_at')
                        ->exists()
                ) {
                    throw new DomainException(
                        'Close the target terminal session first.'
                    );
                }

                $pair->forceFill([
                    'status' => 'OPERATING',
                    'last_error' => null,
                ])->save();

                $target->forceFill([
                    'status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'last_error' => null,
                ])->save();
            });

        try {
            OperateAdminPairMember::dispatch(
                $pairId,
                $envId,
                $operation
            );
        } catch (\Throwable $error) {
            AdminEnvironmentPair::query()
                ->find($pairId)
                ?->forceFill([
                    'status' => 'ERROR',
                    'last_error' => 'Individual runtime dispatch failed.',
                ])->save();

            throw $error;
        }
    }
}
