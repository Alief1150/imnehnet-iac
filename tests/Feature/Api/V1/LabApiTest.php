<?php

use App\Enums\LabActualState;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('api requires an authenticated same-origin session', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('student can read self and own lab but not another students lab', function () {
    $student = User::factory()->create();
    $ownLab = Lab::factory()->for($student, 'owner')->create();
    $foreignLab = Lab::factory()->create();

    $this->actingAs($student)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $student->id);

    $this->actingAs($student)
        ->getJson("/api/v1/labs/{$ownLab->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.access.ssh_command');

    $this->actingAs($student)
        ->getJson("/api/v1/labs/{$foreignLab->id}")
        ->assertNotFound();
});

test('disabled accounts cannot use the api', function () {
    $disabledAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);

    $this->actingAs($disabledAdmin)->getJson('/api/v1/me')->assertForbidden();
    $this->actingAs($disabledAdmin)->getJson('/api/v1/infrastructure')->assertForbidden();
});

test('only admin can read infrastructure summary and request provisioning', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $this->actingAs($student)
        ->getJson('/api/v1/infrastructure')
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/api/v1/infrastructure')
        ->assertOk()
        ->assertJsonStructure(['data' => ['incus' => ['status', 'source', 'checked_at']]]);

    $this->actingAs($admin)
        ->postJson('/api/v1/labs/provision', [
            'user_id' => $student->id,
            'name' => 'API Lab',
            'cpu_limit' => 1,
            'memory_limit' => 1024,
            'storage_limit' => 10,
        ])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued');
});

test('owner lifecycle request returns an asynchronous operation', function () {
    Queue::fake();
    $student = User::factory()->create();
    $lab = Lab::factory()->for($student, 'owner')->create([
        'actual_state' => LabActualState::Running,
    ]);

    $this->actingAs($student)
        ->postJson("/api/v1/labs/{$lab->id}/stop")
        ->assertAccepted()
        ->assertJsonPath('data.type', 'stop')
        ->assertJsonPath('data.status', 'queued');
});

test('invalid lifecycle request returns a safe conflict response', function () {
    Queue::fake();
    $student = User::factory()->create();
    $lab = Lab::factory()->for($student, 'owner')->create([
        'actual_state' => LabActualState::Absent,
    ]);

    $this->actingAs($student)
        ->postJson("/api/v1/labs/{$lab->id}/start")
        ->assertConflict()
        ->assertJsonPath('error.code', 'invalid_state_transition');
});

test('provisioning validation rejects unsafe resource values', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $this->actingAs($admin)
        ->postJson('/api/v1/labs/provision', [
            'user_id' => $student->id,
            'cpu_limit' => 64,
            'memory_limit' => 999999,
            'storage_limit' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cpu_limit', 'memory_limit', 'storage_limit']);
});
