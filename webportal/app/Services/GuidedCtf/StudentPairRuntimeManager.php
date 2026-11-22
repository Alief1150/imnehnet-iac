<?php

namespace App\Services\GuidedCtf;

// IMNEHNET_STUDENT_PAIR_RUNTIME_V2

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\OperateStudentPairRuntime;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class StudentPairRuntimeManager
{
    public function request(
        User $actor,
        LabEnvironment $attacker,
        string $operation
    ): void {
        if (
            !$actor->isAdmin()
            || !$actor->isActive()
            || !in_array($operation, ['start', 'stop'], true)
        ) {
            throw new DomainException(
                'Unauthorized Student Pair operation.'
            );
        }

        $sessions = LabSession::query()
            ->where('attacker_environment_id', $attacker->id)
            ->where('status', '!=', 'DELETED')
            ->limit(2)
            ->get();

        if ($sessions->count() !== 1) {
            throw new DomainException(
                'Expected exactly one active Student LabSession.'
            );
        }

        $sessionId = (int) $sessions->first()->id;

        DB::connection($attacker->getConnectionName())
            ->transaction(function () use (
                $sessionId,
                $attacker,
                $operation
            ): void {
                $session = LabSession::query()
                    ->lockForUpdate()
                    ->findOrFail($sessionId);

                if (!in_array(
                    $this->status($session->status),
                    ['READY', 'RUNNING', 'STOPPED'],
                    true
                )) {
                    throw new DomainException(
                        'Student LabSession is not operational.'
                    );
                }

                [$a, $v] = $this->members($session);

                if ((int) $a->id !== (int) $attacker->id) {
                    throw new DomainException(
                        'Operation must originate from Attacker.'
                    );
                }

                $locked = LabEnvironment::query()
                    ->whereIn('id', [$a->id, $v->id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($locked->count() !== 2) {
                    throw new DomainException(
                        'Pair inventory changed.'
                    );
                }

                $a = $locked->get($a->id);
                $v = $locked->get($v->id);

                $this->validateMember($session, $a, 'attacker');
                $this->validateMember($session, $v, 'victim');

                foreach ([$a, $v] as $env) {
                    if (
                        !in_array($env->status, [
                            EnvironmentStatus::Ready,
                            EnvironmentStatus::Stopped,
                        ], true)
                        || !in_array($env->runtime_status, [
                            RuntimeStatus::Running,
                            RuntimeStatus::Stopped,
                        ], true)
                    ) {
                        throw new DomainException(
                            'Pair is busy or requires reconciliation.'
                        );
                    }
                }

                $desired = $operation === 'start'
                    ? RuntimeStatus::Running
                    : RuntimeStatus::Stopped;

                if (
                    $a->runtime_status === $desired
                    && $v->runtime_status === $desired
                ) {
                    throw new DomainException(
                        'Both containers already have the requested state.'
                    );
                }

                foreach ([$a, $v] as $env) {
                    $env->forceFill([
                        'status' => EnvironmentStatus::Provisioning,
                        'runtime_status' => RuntimeStatus::Unknown,
                        'last_error' => null,
                    ])->save();
                }
            });

        try {
            OperateStudentPairRuntime::dispatch(
                $sessionId,
                $operation
            );
        } catch (\Throwable $error) {
            $session = LabSession::find($sessionId);

            if ($session) {
                foreach ([
                    $session->attacker_environment_id,
                    $session->victim_environment_id,
                ] as $id) {
                    $env = LabEnvironment::find($id);

                    $env?->forceFill([
                        'status' => EnvironmentStatus::Error,
                        'runtime_status' => RuntimeStatus::Unknown,
                        'last_error' => 'Pair queue dispatch failed.',
                    ])->save();
                }
            }

            throw $error;
        }
    }

    public function members(LabSession $session): array
    {
        if (
            !$session->attacker_environment_id
            || !$session->victim_environment_id
            || (int) $session->attacker_environment_id
                === (int) $session->victim_environment_id
        ) {
            throw new DomainException(
                'Invalid Student Pair references.'
            );
        }

        $attacker = LabEnvironment::findOrFail(
            $session->attacker_environment_id
        );

        $victim = LabEnvironment::findOrFail(
            $session->victim_environment_id
        );

        $this->validateMember($session, $attacker, 'attacker');
        $this->validateMember($session, $victim, 'victim');

        return [$attacker, $victim];
    }

    public function validateMember(
        LabSession $session,
        LabEnvironment $env,
        string $role
    ): void {
        if (!in_array($role, ['attacker', 'victim'], true)) {
            throw new DomainException('Invalid pair role.');
        }

        $userId = (int) $session->user_id;

        $remote = $role === 'attacker'
            ? 'lab-atk'
            : 'lab-env';

        $expected = $role.'-user-'.$userId;

        if (
            $userId <= 0
            || $env->isProtected()
            || (int) $env->user_id !== $userId
            || (string) $env->container_name !== $expected
            || rtrim((string) $env->incus_remote, ':')
                !== $remote
            || ($env->incus_project ?: 'default') !== 'default'
            || !preg_match(
                '/^ctf-'.$role.'-[0-9]+$/D',
                (string) $env->tofu_workspace
            )
        ) {
            throw new DomainException(
                'Student Pair ownership or resource identity mismatch.'
            );
        }
    }

    private function status(mixed $value): string
    {
        return $value instanceof \BackedEnum
            ? (string) $value->value
            : (string) $value;
    }
}
