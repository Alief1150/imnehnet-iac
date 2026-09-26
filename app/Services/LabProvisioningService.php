<?php

namespace App\Services;

use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Enums\OperationStatus;
use App\Jobs\ExecuteLabOperationJob;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class LabProvisioningService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /** @param array<string, mixed> $configuration */
    public function request(User $actor, User $student, array $configuration): LabOperation
    {
        Gate::forUser($actor)->authorize('provision', [Lab::class, $student]);

        [$operation, $created] = DB::transaction(function () use ($actor, $student, $configuration): array {
            $lockedStudent = User::query()->lockForUpdate()->findOrFail($student->id);

            if (! $lockedStudent->isActive() || $lockedStudent->isAdmin()) {
                throw new AuthorizationException;
            }

            $lab = Lab::query()->where('user_id', $lockedStudent->id)->lockForUpdate()->first();

            if ($lab) {
                $active = $lab->operations()
                    ->whereIn('status', [OperationStatus::Queued->value, OperationStatus::Running->value])
                    ->oldest()
                    ->first();

                if ($active) {
                    return [$active->load('lab'), false];
                }

                throw new \DomainException('Student already has a lab assignment.');
            }

            $lab = Lab::query()->create([
                'user_id' => $lockedStudent->id,
                'external_id' => (string) Str::uuid(),
                'name' => (string) ($configuration['name'] ?? "Lab {$lockedStudent->id}"),
                'container_name' => "imn-lab-{$lockedStudent->id}",
                'compute_host' => 'lab-env',
                'base_image' => (string) ($configuration['base_image'] ?? 'images:ubuntu/24.04'),
                'profile' => (string) ($configuration['profile'] ?? 'default'),
                'network' => 'incusbr0',
                'status' => 'PENDING',
                'desired_state' => LabDesiredState::Running,
                'actual_state' => LabActualState::Absent,
                'operation_state' => LabOperationState::Queued,
                'cpu_limit' => (int) ($configuration['cpu_limit'] ?? 1),
                'memory_limit' => (int) ($configuration['memory_limit'] ?? 1024),
                'storage_limit' => (int) ($configuration['storage_limit'] ?? 10),
                'security_policy' => 'default-student-isolation',
                'isolation_status' => 'not_configured',
            ]);

            $operation = $lab->operations()->create([
                'id' => (string) Str::uuid(),
                'requested_by' => $actor->id,
                'type' => 'provision',
                'status' => OperationStatus::Queued,
                'idempotency_key' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, "imnehnet:provision:{$lockedStudent->id}"),
            ]);

            $this->activity->record(
                actor: $actor,
                action: 'PROVISION_LAB',
                resource: $lab,
                status: 'queued',
                description: 'Lab provisioning request queued; live mutation is disabled in Milestone 1.',
                metadata: ['operation_id' => $operation->id],
            );

            return [$operation->load('lab'), true];
        });

        if ($created) {
            ExecuteLabOperationJob::dispatch($operation->id);
        }

        return $operation;
    }
}
