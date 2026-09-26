<?php

use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Enums\UserRole;
use App\Filament\Resources\Labs\LabResource;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a failed lab record explains itself instead of looking blank', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $lab = Lab::factory()->create([
        'status' => 'PENDING',
        'desired_state' => LabDesiredState::Running,
        'actual_state' => LabActualState::Absent,
        'operation_state' => LabOperationState::Failed,
        'ip_address' => null,
        'ssh_port' => null,
        'access_host' => null,
        'last_error_message' => 'Live infrastructure mutation is disabled in Milestone 1.',
    ]);

    $response = $this->actingAs($admin)->get(LabResource::getUrl('view', ['record' => $lab], 'admin'));

    $response->assertOk()
        // The failure must be visible to the operator, not silently blank.
        ->assertSee('Live infrastructure mutation is disabled in Milestone 1.')
        // Control plane state is shown, not inferred.
        ->assertSee('Desired state')
        ->assertSee('Actual state')
        ->assertSee('Last operation')
        // Unassigned values say so explicitly rather than rendering as empty.
        ->assertSee('Not assigned')
        ->assertSee('Not published')
        ->assertSee('Not created');
});

test('the lab table reports container state with its address when one exists', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Lab::factory()->create([
        'actual_state' => LabActualState::Running,
        'ip_address' => '10.20.0.31',
        'ssh_port' => 22001,
    ]);

    $this->actingAs($admin)
        ->get(LabResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('Container')
        ->assertSee('RUNNING')
        ->assertSee('10.20.0.31:22001');
});

test('a lab with verified access shows the ssh command', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $lab = Lab::factory()->create([
        'access_host' => '100.77.79.18',
        'access_username' => 'alip',
        'ssh_port' => 22001,
        'access_verified_at' => now(),
    ]);

    expect($lab->sshCommand())->toBe('ssh alip@100.77.79.18 -p 22001');

    $this->actingAs($admin)
        ->get(LabResource::getUrl('view', ['record' => $lab], 'admin'))
        ->assertOk()
        ->assertSee('ssh alip@100.77.79.18 -p 22001');
});
