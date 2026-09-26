<?php

namespace App\Jobs;

use App\Contracts\LabProvisioner;
use App\Data\ProvisionedLab;
use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExecuteLabOperationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $operationId) {}

    public function handle(LabProvisioner $provisioner, ActivityLogService $activity): void
    {
        $operation = DB::transaction(function (): ?LabOperation {
            $operation = LabOperation::query()->with('lab.owner')->lockForUpdate()->find($this->operationId);

            if (! $operation || $operation->status->value !== 'queued') {
                return null;
            }

            $operation->update([
                'status' => 'running',
                'attempt_count' => $operation->attempt_count + 1,
                'started_at' => now(),
            ]);

            return $operation->fresh(['lab.owner']);
        });

        if (! $operation) {
            return;
        }

        try {
            $result = $this->execute($provisioner, $operation);
        } catch (Throwable $exception) {
            $result = new ProvisionedLab(
                successful: false,
                errorCode: 'provider_failure',
                message: 'The infrastructure provider failed safely.',
                detail: ['error' => $exception->getMessage()],
            );
        }

        $this->settle($operation, $result, $activity);
    }

    private function execute(LabProvisioner $provisioner, LabOperation $operation): ProvisionedLab
    {
        $lab = $operation->lab;

        return match ($operation->type) {
            'provision' => $provisioner->provision($lab),
            'start' => $provisioner->start($lab),
            'stop' => $provisioner->stop($lab),
            'reset' => $provisioner->reset($lab),
            'delete' => $provisioner->destroy($lab),
            default => new ProvisionedLab(successful: false, errorCode: 'unsupported', message: 'Unsupported operation.'),
        };
    }

    private function settle(LabOperation $operation, ProvisionedLab $result, ActivityLogService $activity): void
    {
        DB::transaction(function () use ($operation, $result, $activity): void {
            $locked = LabOperation::query()->with('lab.owner')->lockForUpdate()->findOrFail($operation->id);
            $lab = $locked->lab;

            if ($result->successful) {
                $this->applySuccess($locked, $lab, $result);
            } else {
                $locked->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->message,
                ]);
                $lab->update([
                    'operation_state' => LabOperationState::Failed,
                    'last_error_message' => $result->message,
                ]);
            }

            $activity->record(
                actor: $locked->requester,
                action: strtoupper($locked->type).'_LAB_RESULT',
                resource: $lab,
                status: $result->successful ? 'completed' : 'failed',
                description: $result->successful
                    ? sprintf('%s completed on the compute plane.', ucfirst($locked->type))
                    : ($result->message ?? 'The operation could not be completed.'),
                metadata: [
                    'operation_id' => $locked->id,
                    'error_code' => $result->errorCode,
                ],
            );
        });
    }

    private function applySuccess(LabOperation $operation, Lab $lab, ProvisionedLab $result): void
    {
        $actual = $this->mapState($result, $operation->type);

        $attributes = [
            'actual_state' => $actual,
            'operation_state' => LabOperationState::Idle,
            'last_error_message' => null,
        ];

        if ($result->ipAddress !== null) {
            $attributes['ip_address'] = $result->ipAddress;
        }

        if ($result->sshPort !== null) {
            $attributes['ssh_port'] = $result->sshPort;
        }

        if ($result->webPort !== null) {
            $attributes['web_port'] = $result->webPort;
        }

        if ($operation->type === 'delete') {
            // The operation itself is closed before the record is soft deleted,
            // otherwise the audit trail would be lost with the lab row.
            $operation->update([
                'status' => 'completed',
                'finished_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $lab->update($attributes);
            $lab->delete();

            return;
        }

        if ($operation->type === 'provision' || $operation->type === 'reset') {
            $attributes['status'] = $actual === LabActualState::Running ? 'RUNNING' : 'PENDING';
            $attributes['last_verified_at'] = now();

            if ($actual === LabActualState::Running && $lab->access_host !== null) {
                $attributes['access_verified_at'] = now();
            }
        }

        if ($actual === LabActualState::Running) {
            $attributes['desired_state'] = LabDesiredState::Running;
        } elseif ($actual === LabActualState::Stopped) {
            $attributes['desired_state'] = LabDesiredState::Stopped;
        }

        $lab->update($attributes);

        $operation->update([
            'status' => 'completed',
            'finished_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    private function mapState(ProvisionedLab $result, string $type): LabActualState
    {
        if ($type === 'delete') {
            return LabActualState::Absent;
        }

        return match ((string) $result->status) {
            'running' => LabActualState::Running,
            'stopped' => LabActualState::Stopped,
            'created' => LabActualState::Created,
            'absent', 'deleted' => LabActualState::Absent,
            'error' => LabActualState::Error,
            default => LabActualState::Unknown,
        };
    }
}
