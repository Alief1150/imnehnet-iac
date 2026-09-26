<?php

namespace App\Services;

use App\Contracts\LabProvisioner;
use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Enums\OperationStatus;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Removes a lab from the infrastructure first and from the portal second.
 *
 * The order matters: the container is the thing a student can actually reach,
 * so it must be gone before the record claims it is gone. If the compute plane
 * refuses, nothing is deleted in the portal and the operator is told why.
 */
final class LabDeletionService
{
    public function __construct(
        private readonly LabProvisioner $provisioner,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * @return array{deleted: bool, container_removed: bool, message: string}
     */
    public function delete(User $actor, Lab $lab): array
    {
        $result = $this->provisioner->destroy($lab);

        if (! $result->successful) {
            Log::warning('Lab deletion aborted: the compute plane refused.', [
                'lab' => $lab->container_name,
                'error_code' => $result->errorCode,
            ]);

            $lab->forceFill([
                'operation_state' => LabOperationState::Failed,
                'last_error_message' => 'The container could not be removed: '.($result->message ?? 'unknown error'),
            ])->save();

            $this->activity->record(
                actor: $actor,
                action: 'DELETE_LAB_RESULT',
                resource: $lab,
                status: 'failed',
                description: 'Deletion aborted because the container is still present on the compute plane.',
                metadata: ['error_code' => $result->errorCode],
            );

            return [
                'deleted' => false,
                'container_removed' => false,
                'message' => 'The container could not be removed, so the record was kept. '.$result->message,
            ];
        }

        DB::transaction(function () use ($lab, $actor): void {
            // Record the removal as an operation so the audit trail matches the
            // lifecycle actions an operator can already see.
            $lab->operations()->create([
                'id' => (string) Str::uuid(),
                'requested_by' => $actor->id,
                'type' => 'delete',
                'status' => OperationStatus::Completed,
                'attempt_count' => 1,
                'idempotency_key' => (string) Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    "imnehnet:delete:{$lab->external_id}:".$lab->updated_at?->getTimestamp(),
                ),
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $lab->update([
                'desired_state' => LabDesiredState::Deleted,
                'actual_state' => LabActualState::Absent,
                'operation_state' => LabOperationState::Idle,
                'ip_address' => null,
                'access_host' => null,
                'access_username' => null,
                'access_verified_at' => null,
                'ssh_port' => null,
                'web_port' => null,
                'last_error_message' => null,
            ]);

            $lab->delete();
        });

        $this->activity->record(
            actor: $actor,
            action: 'DELETE_LAB_RESULT',
            resource: $lab,
            status: 'completed',
            description: 'The container was removed from the compute plane and the portal record was archived.',
            metadata: ['container_name' => $result->containerName],
        );

        return [
            'deleted' => true,
            'container_removed' => $result->status !== 'absent' || $result->containerName !== null,
            'message' => 'The container was removed and the record archived.',
        ];
    }
}
