<?php

namespace App\Jobs;

use App\Contracts\LabMutationProvider;
use App\Data\MutationResult;
use App\Enums\LabOperationState;
use App\Enums\OperationStatus;
use App\Models\LabOperation;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class LabOperationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $operationId) {}

    public function handle(LabMutationProvider $provider, ActivityLogService $activity): void
    {
        $operation = DB::transaction(function (): ?LabOperation {
            $operation = LabOperation::query()->with('lab')->lockForUpdate()->find($this->operationId);

            if (! $operation || $operation->status !== OperationStatus::Queued) {
                return null;
            }

            $operation->update([
                'status' => OperationStatus::Running,
                'attempt_count' => $operation->attempt_count + 1,
                'started_at' => now(),
            ]);

            return $operation->fresh(['lab']);
        });

        if (! $operation) {
            return;
        }

        try {
            $result = $provider->execute($operation);
        } catch (Throwable) {
            $result = new MutationResult(
                successful: false,
                errorCode: 'provider_failure',
                message: 'Infrastructure provider failed safely.',
            );
        }

        DB::transaction(function () use ($operation, $result, $activity): void {
            $locked = LabOperation::query()->with('lab')->lockForUpdate()->findOrFail($operation->id);

            if ($result->successful) {
                $locked->update([
                    'status' => OperationStatus::Completed,
                    'finished_at' => now(),
                    'error_code' => null,
                    'error_message' => null,
                ]);
                $locked->lab->update([
                    'operation_state' => LabOperationState::Idle,
                    'last_error_message' => null,
                ]);
            } else {
                $locked->update([
                    'status' => OperationStatus::Failed,
                    'finished_at' => now(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->message,
                ]);
                $locked->lab->update([
                    'operation_state' => LabOperationState::Failed,
                    'last_error_message' => $result->message,
                ]);
            }

            $activity->record(
                actor: $locked->requester,
                action: strtoupper($locked->type).'_LAB_RESULT',
                resource: $locked->lab,
                status: $result->successful ? 'completed' : 'failed',
                description: $result->successful
                    ? 'Lab operation completed.'
                    : 'Lab operation could not run because the provider is unavailable.',
                metadata: [
                    'operation_id' => $locked->id,
                    'error_code' => $result->errorCode,
                ],
            );
        });
    }
}
