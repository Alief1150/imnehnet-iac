<?php

use App\Enums\OperationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\ExecuteLabOperationJob;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Models\User;
use App\Services\LabProvisioningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('admin provisioning request creates one queued lab operation and activity record', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $operation = app(LabProvisioningService::class)->request($admin, $student, [
        'name' => 'Student Security Lab',
        'cpu_limit' => 1,
        'memory_limit' => 1024,
        'storage_limit' => 10,
    ]);

    expect($operation->status)->toBe(OperationStatus::Queued)
        ->and($operation->lab->user_id)->toBe($student->id)
        ->and(Lab::query()->count())->toBe(1)
        ->and(LabOperation::query()->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', 'PROVISION_LAB')->count())->toBe(1);

    Queue::assertPushed(ExecuteLabOperationJob::class, fn ($job) => $job->operationId === $operation->id);
});

test('duplicate provisioning request returns the same active operation', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();
    $service = app(LabProvisioningService::class);

    $first = $service->request($admin, $student, []);
    $second = $service->request($admin, $student, []);

    expect($second->is($first))->toBeTrue()
        ->and(Lab::query()->count())->toBe(1)
        ->and(LabOperation::query()->count())->toBe(1);
});

test('student cannot provision a lab for another student', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $student = User::factory()->create();

    expect(fn () => app(LabProvisioningService::class)->request($actor, $student, []))
        ->toThrow(AuthorizationException::class);
});

test('admin cannot provision a lab for a disabled student', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['status' => UserStatus::Disabled]);

    expect(fn () => app(LabProvisioningService::class)->request($admin, $student, []))
        ->toThrow(AuthorizationException::class);
});
