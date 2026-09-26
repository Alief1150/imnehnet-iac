<?php

use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Enums\OperationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a student account starts active without a lab and hashes its password', function () {
    $user = User::factory()->create([
        'password' => 'temporary-secret',
    ]);

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->lab)->toBeNull()
        ->and(Hash::check('temporary-secret', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('temporary-secret');
});

test('a lab belongs to one student and casts infrastructure states', function () {
    $student = User::factory()->create();
    $lab = Lab::factory()->for($student, 'owner')->create([
        'desired_state' => LabDesiredState::Running,
        'actual_state' => LabActualState::Absent,
        'operation_state' => LabOperationState::Idle,
    ]);

    expect($student->fresh()->lab->is($lab))->toBeTrue()
        ->and($lab->owner->is($student))->toBeTrue()
        ->and($lab->desired_state)->toBe(LabDesiredState::Running)
        ->and($lab->actual_state)->toBe(LabActualState::Absent)
        ->and($lab->operation_state)->toBe(LabOperationState::Idle);
});

test('unverified access never produces an ssh command', function () {
    $lab = Lab::factory()->make([
        'access_host' => null,
        'ssh_port' => null,
        'access_username' => null,
        'access_verified_at' => null,
    ]);

    expect($lab->sshCommand())->toBeNull();
});

test('a lab operation keeps its requester lab and asynchronous status', function () {
    $requester = User::factory()->create(['role' => UserRole::Admin]);
    $lab = Lab::factory()->create();
    $operation = LabOperation::factory()->for($lab)->for($requester, 'requester')->create([
        'type' => 'provision',
        'status' => OperationStatus::Queued,
    ]);

    expect($operation->lab->is($lab))->toBeTrue()
        ->and($operation->requester->is($requester))->toBeTrue()
        ->and($operation->status)->toBe(OperationStatus::Queued)
        ->and($lab->fresh()->operations)->toHaveCount(1);
});
