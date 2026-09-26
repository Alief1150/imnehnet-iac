<?php

use App\Enums\LabActualState;
use App\Enums\LabOperationState;
use App\Enums\UserRole;
use App\Filament\Pages\Infrastructure;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Labs\LabResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('infrastructure and admin resources are visible only to administrators', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Infrastructure::canAccess())->toBeTrue()
        ->and(UserResource::canAccess())->toBeTrue()
        ->and(ActivityLogResource::canAccess())->toBeTrue();

    $this->actingAs($student);
    expect(Infrastructure::canAccess())->toBeFalse()
        ->and(UserResource::canAccess())->toBeFalse()
        ->and(ActivityLogResource::canAccess())->toBeFalse();
});

test('student lab resource query is ownership scoped', function () {
    $student = User::factory()->create();
    $own = Lab::factory()->for($student, 'owner')->create();
    Lab::factory()->create();

    $this->actingAs($student);

    expect(LabResource::getEloquentQuery()->pluck('id')->all())->toBe([$own->id]);
});

test('activity log resource exposes list and view routes only', function () {
    expect(array_keys(ActivityLogResource::getPages()))->toBe(['index', 'view']);
});

test('administrator can render the infrastructure page with evidence labels', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(Infrastructure::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Source', escape: false)
        ->assertSee('Checked', escape: false)
        // The management plane and the compute plane must be reported
        // separately so an operator can tell which side is failing.
        ->assertSee('IAAS-MGMT')
        ->assertSee('LAB-ENV');
});

test('administrator can render users table with enum roles', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($admin)
        ->get(UserResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('ADMIN')
        ->assertSee('STUDENT');
});

test('administrator sees the truthful queue action instead of direct lab creation', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(LabResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('Queue Lab Request');
});

test('administrator does not see invalid lifecycle actions for an absent lab', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Lab::factory()->create([
        'actual_state' => LabActualState::Absent,
        'operation_state' => LabOperationState::Failed,
    ]);

    $this->actingAs($admin)
        ->get(LabResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertDontSee('Start')
        ->assertDontSee('Stop')
        ->assertDontSee('requestreset')
        ->assertDontSee('requestdelete');
});
