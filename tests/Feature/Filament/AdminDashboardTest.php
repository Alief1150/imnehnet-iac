<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Infrastructure;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\LabEnvironment;
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

test('a student reaches the environments resource and sees only their own', function () {
    // Scoped rather than closed. A student provisions their own lab, so refusing
    // the resource outright would leave them with no way to start one; the query
    // is what keeps them inside their own rows.
    $student = User::factory()->create(['role' => UserRole::Student]);

    runningEnvFor($student, 'lab-mine');
    runningEnvFor(User::factory()->create(), 'lab-someone-else');
    runningEnvFor(null, 'lab-unassigned');

    $this->actingAs($student);

    $this->get(EnvironmentResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('lab-mine')
        ->assertDontSee('lab-someone-else')
        ->assertDontSee('lab-unassigned');
});

/**
 * @return LabEnvironment
 */
function runningEnvFor(?User $owner, string $name)
{
    return LabEnvironment::factory()->create([
        'container_name' => $name,
        'tofu_workspace' => 'env-'.str_replace('lab-', '', $name),
        'user_id' => $owner?->getKey(),
    ]);
}

test('activity log resource exposes list and view routes only', function () {
    // An audit trail that can be edited through the interface is not an audit
    // trail, so no create or edit route exists to be authorised.
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
test('a running and ready environment is offered the actions that make sense', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    $this->actingAs($admin)
        ->get(EnvironmentResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('lab-web-smoke')
        // Both states are shown side by side. Provisioning READY while the
        // runtime is RUNNING is the normal case, and collapsing them into one
        // column would make "ready" mean something different on each screen.
        ->assertSee('READY', escape: false)
        ->assertSee('Running', escape: false);
});
