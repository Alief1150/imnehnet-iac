<?php

use App\Contracts\LabProvisioner;
use App\Data\ProvisionedLab;
use App\Enums\LabActualState;
use App\Enums\LabOperationState;
use App\Enums\OperationStatus;
use App\Enums\UserRole;
use App\Jobs\ExecuteLabOperationJob;
use App\Models\Lab;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\LabLifecycleService;
use App\Services\LabProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * A provisioner double so the control plane flow can be asserted without
 * touching the real compute plane.
 */
function fakeProvisioner(?callable $onExecute = null): LabProvisioner
{
    return new class($onExecute) implements LabProvisioner
    {
        public function __construct(private $onExecute) {}

        public function provision(Lab $lab): ProvisionedLab
        {
            return $this->call('provision', $lab);
        }

        public function start(Lab $lab): ProvisionedLab
        {
            return $this->call('start', $lab);
        }

        public function stop(Lab $lab): ProvisionedLab
        {
            return $this->call('stop', $lab);
        }

        public function reset(Lab $lab): ProvisionedLab
        {
            return $this->call('reset', $lab);
        }

        public function destroy(Lab $lab): ProvisionedLab
        {
            return $this->call('destroy', $lab);
        }

        public function observe(Lab $lab): ProvisionedLab
        {
            return $this->call('observe', $lab);
        }

        private function call(string $method, Lab $lab): ProvisionedLab
        {
            if ($this->onExecute) {
                return ($this->onExecute)($method, $lab);
            }

            return new ProvisionedLab(
                successful: true,
                containerName: $lab->container_name,
                ipAddress: '10.20.0.31',
                sshPort: 22001,
                status: 'running',
                accessUsername: 'alip',
            );
        }
    };
}

test('a queued provisioning request dispatches the operation job', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $operation = app(LabProvisioningService::class)->request($admin, $student, []);

    Queue::assertPushed(ExecuteLabOperationJob::class, fn ($job): bool => $job->operationId === $operation->id);
});

test('a lifecycle request dispatches the operation job', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $lab = Lab::factory()->for($owner, 'owner')->create([
        'actual_state' => LabActualState::Running,
        'operation_state' => LabOperationState::Idle,
    ]);

    $operation = app(LabLifecycleService::class)->request($owner, $lab, 'stop');

    Queue::assertPushed(ExecuteLabOperationJob::class, fn ($job): bool => $job->operationId === $operation->id);
});

test('a successful provision records the address the infrastructure reported', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $operation = app(LabProvisioningService::class)->request($admin, $student, []);
    $lab = $operation->lab;

    $lab->forceFill([
        'access_host' => '100.77.79.18',
        'last_verified_at' => now(),
    ])->save();

    (new ExecuteLabOperationJob($operation->id))->handle(fakeProvisioner(), app(ActivityLogService::class));

    $lab->refresh();
    $operation->refresh();

    expect($operation->status)->toBe(OperationStatus::Completed)
        ->and($lab->actual_state)->toBe(LabActualState::Running)
        ->and($lab->ip_address)->toBe('10.20.0.31')
        ->and($lab->ssh_port)->toBe(22001)
        ->and($lab->status)->toBe('RUNNING')
        ->and($lab->operation_state)->toBe(LabOperationState::Idle)
        ->and($lab->last_error_message)->toBeNull();
});

test('a provider failure records the error and leaves the lab failed', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $operation = app(LabProvisioningService::class)->request($admin, $student, []);

    $provisioner = fakeProvisioner(fn (): ProvisionedLab => new ProvisionedLab(
        successful: false,
        errorCode: 'launch_failed',
        message: 'Incus refused to create the container.',
    ));

    (new ExecuteLabOperationJob($operation->id))->handle($provisioner, app(ActivityLogService::class));

    expect($operation->refresh()->status)->toBe(OperationStatus::Failed)
        ->and($operation->error_code)->toBe('launch_failed')
        ->and($operation->lab->refresh()->operation_state)->toBe(LabOperationState::Failed)
        ->and($operation->lab->last_error_message)->toBe('Incus refused to create the container.');
});

test('a delete removes the infrastructure resource and soft deletes the record', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lab = Lab::factory()->create(['actual_state' => LabActualState::Running]);

    $operation = app(LabLifecycleService::class)->request($admin, $lab, 'delete');

    (new ExecuteLabOperationJob($operation->id))->handle(fakeProvisioner(), app(ActivityLogService::class));

    expect($operation->refresh()->status)->toBe(OperationStatus::Completed)
        // Soft deleted so the audit trail survives.
        ->and(Lab::withTrashed()->find($lab->id)->trashed())->toBeTrue();
});
