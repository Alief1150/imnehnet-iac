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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class LabLifecycleService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function request(User $actor, Lab $lab, string $action): LabOperation
    {
        if (! in_array($action, ['start', 'stop', 'reset', 'delete'], true)) {
            throw new \InvalidArgumentException('Unsupported lab action.');
        }

        Gate::forUser($actor)->authorize($action, $lab);
        $this->assertTransition($lab, $action);

        [$operation, $created] = DB::transaction(function () use ($actor, $lab, $action): array {
            $locked = Lab::query()->lockForUpdate()->findOrFail($lab->id);
            $this->assertTransition($locked, $action);

            $active = $locked->operations()
                ->whereIn('status', [OperationStatus::Queued->value, OperationStatus::Running->value])
                ->oldest()
                ->first();

            if ($active) {
                if ($active->type === $action) {
                    return [$active->load('lab'), false];
                }

                throw new \DomainException('Another lab operation is already active.');
            }

            $locked->update([
                'desired_state' => match ($action) {
                    'start', 'reset' => LabDesiredState::Running,
                    'stop' => LabDesiredState::Stopped,
                    'delete' => LabDesiredState::Deleted,
                },
                'operation_state' => LabOperationState::Queued,
            ]);

            $operation = $locked->operations()->create([
                'id' => (string) Str::uuid(),
                'requested_by' => $actor->id,
                'type' => $action,
                'status' => OperationStatus::Queued,
                'idempotency_key' => (string) Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    "imnehnet:{$action}:{$locked->external_id}:{$locked->updated_at?->getTimestamp()}",
                ),
            ]);

            $this->activity->record(
                actor: $actor,
                action: strtoupper($action).'_LAB',
                resource: $locked,
                status: 'queued',
                description: ucfirst($action).' request queued; live mutation is disabled in Milestone 1.',
                metadata: ['operation_id' => $operation->id],
            );

            return [$operation->load('lab'), true];
        });

        if ($created) {
            $this->dispatch($action, $operation->id);
        }

        return $operation;
    }

    public function isAvailable(Lab $lab, string $action): bool
    {
        return match ($action) {
            'start' => $lab->actual_state === LabActualState::Stopped,
            'stop' => $lab->actual_state === LabActualState::Running,
            'reset' => in_array($lab->actual_state, [LabActualState::Running, LabActualState::Stopped], true),
            'delete' => ! in_array($lab->actual_state, [LabActualState::Absent, LabActualState::Missing], true),
            default => false,
        };
    }

    private function assertTransition(Lab $lab, string $action): void
    {
        if (! $this->isAvailable($lab, $action)) {
            throw new \DomainException('Invalid lab state transition.');
        }
    }

    private function dispatch(string $action, string $operationId): void
    {
        ExecuteLabOperationJob::dispatch($operationId);
    }
}
