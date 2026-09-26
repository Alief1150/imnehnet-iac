<?php

use App\Enums\LabActualState;
use App\Enums\OperationStatus;
use App\Jobs\ExecuteLabOperationJob;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Models\User;
use App\Services\LabLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('owner can request stop for a running lab', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $lab = Lab::factory()->for($owner, 'owner')->create(['actual_state' => LabActualState::Running]);

    $operation = app(LabLifecycleService::class)->request($owner, $lab, 'stop');

    expect($operation->status)->toBe(OperationStatus::Queued)
        ->and($operation->type)->toBe('stop')
        ->and(ActivityLog::query()->where('action', 'STOP_LAB')->count())->toBe(1);

    Queue::assertPushed(ExecuteLabOperationJob::class, fn ($job) => $job->operationId === $operation->id);
});

test('student cannot request lifecycle action for another students lab', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $lab = Lab::factory()->create(['actual_state' => LabActualState::Running]);

    expect(fn () => app(LabLifecycleService::class)->request($actor, $lab, 'stop'))
        ->toThrow(AuthorizationException::class);
});

test('start request rejects a lab that is already running', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $lab = Lab::factory()->for($owner, 'owner')->create(['actual_state' => LabActualState::Running]);

    expect(fn () => app(LabLifecycleService::class)->request($owner, $lab, 'start'))
        ->toThrow(DomainException::class, 'Invalid lab state transition');
});

test('lifecycle availability follows the actual infrastructure state', function () {
    $service = app(LabLifecycleService::class);
    $lab = Lab::factory()->make(['actual_state' => LabActualState::Absent]);

    expect($service->isAvailable($lab, 'start'))->toBeFalse()
        ->and($service->isAvailable($lab, 'stop'))->toBeFalse()
        ->and($service->isAvailable($lab, 'reset'))->toBeFalse()
        ->and($service->isAvailable($lab, 'delete'))->toBeFalse();

    $lab->actual_state = LabActualState::Stopped;

    expect($service->isAvailable($lab, 'start'))->toBeTrue()
        ->and($service->isAvailable($lab, 'stop'))->toBeFalse()
        ->and($service->isAvailable($lab, 'reset'))->toBeTrue()
        ->and($service->isAvailable($lab, 'delete'))->toBeTrue();

    $lab->actual_state = LabActualState::Running;

    expect($service->isAvailable($lab, 'start'))->toBeFalse()
        ->and($service->isAvailable($lab, 'stop'))->toBeTrue()
        ->and($service->isAvailable($lab, 'reset'))->toBeTrue()
        ->and($service->isAvailable($lab, 'delete'))->toBeTrue();
});

test('different lifecycle action conflicts with an active operation', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $lab = Lab::factory()->for($owner, 'owner')->create(['actual_state' => LabActualState::Running]);
    LabOperation::factory()->for($lab)->for($owner, 'requester')->create(['type' => 'reset']);

    expect(fn () => app(LabLifecycleService::class)->request($owner, $lab, 'stop'))
        ->toThrow(DomainException::class, 'Another lab operation is already active');
});
