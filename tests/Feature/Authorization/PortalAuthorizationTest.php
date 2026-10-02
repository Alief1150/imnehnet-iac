<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Policies\LabEnvironmentPolicy;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * These rules decide who can drive the compute plane, so they are asserted on the
 * policy directly rather than through the interface. A hidden button is
 * presentation; the policy is the control.
 */
test('only active accounts can access the filament panel', function () {
    $panel = Panel::make()->id('admin');
    $activeStudent = User::factory()->create();
    $disabledAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);

    expect($activeStudent->canAccessPanel($panel))->toBeTrue()
        ->and($disabledAdmin->canAccessPanel($panel))->toBeFalse();
});

test('a student may create their own environment', function () {
    $policy = new LabEnvironmentPolicy;
    $student = User::factory()->create(['role' => UserRole::Student]);

    // Provisioning your own lab is the point of a student's account. An
    // administrator role is not required for it, and no environment has to exist
    // first: the record is created and becomes the student's at the same moment.
    expect($policy->viewAny($student))->toBeTrue()
        ->and($policy->create($student))->toBeTrue();
});

test('a student may manage only the environments they own', function () {
    $policy = new LabEnvironmentPolicy;
    $student = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create(['role' => UserRole::Student]);

    $own = LabEnvironment::factory()->create(['user_id' => $student->id]);
    $someoneElses = LabEnvironment::factory()->create(['user_id' => $other->id]);
    $unassigned = LabEnvironment::factory()->create(['user_id' => null]);

    expect($policy->view($student, $own))->toBeTrue()
        ->and($policy->update($student, $own))->toBeTrue()
        ->and($policy->delete($student, $own))->toBeTrue();

    expect($policy->view($student, $someoneElses))->toBeFalse()
        ->and($policy->update($student, $someoneElses))->toBeFalse()
        ->and($policy->delete($student, $someoneElses))->toBeFalse();

    // An environment nobody owns is not anybody's. Leaving it reachable would
    // make "mine" depend on who is asking.
    expect($policy->view($student, $unassigned))->toBeFalse()
        ->and($policy->delete($student, $unassigned))->toBeFalse();
});

test('an administrator may manage every environment', function () {
    $policy = new LabEnvironmentPolicy;
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $owned = LabEnvironment::factory()->create(['user_id' => null]);
    $someoneElses = LabEnvironment::factory()->create(['user_id' => User::factory()->create()->id]);

    foreach ([$owned, $someoneElses] as $environment) {
        expect($policy->view($admin, $environment))->toBeTrue()
            ->and($policy->update($admin, $environment))->toBeTrue()
            ->and($policy->delete($admin, $environment))->toBeTrue();
    }
});

test('a disabled account cannot manage anything even with the admin role', function () {
    // The panel already refuses these accounts, so this only matters for the
    // callers that reach the policy without going through the panel: table action
    // closures, queued jobs, and the lifecycle service itself.
    $policy = new LabEnvironmentPolicy;
    $disabled = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);
    $environment = LabEnvironment::factory()->create();

    expect($policy->viewAny($disabled))->toBeFalse()
        ->and($policy->view($disabled, $environment))->toBeFalse()
        ->and($policy->create($disabled))->toBeFalse()
        ->and($policy->update($disabled, $environment))->toBeFalse()
        ->and($policy->delete($disabled, $environment))->toBeFalse()
        ->and($policy->reconcile($disabled, $environment))->toBeFalse();
});

test('the protected reference environment can be read but never changed', function () {
    config()->set('infrastructure.protected_containers', ['lab-tofu-test']);
    config()->set('infrastructure.protected_workspaces', ['default']);

    $policy = new LabEnvironmentPolicy;
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $protected = LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
    ]);
    $disposable = LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    // Reading the actual state is the point of reconciliation existing, so it
    // stays allowed. Changing it is what the protection is for.
    expect($policy->view($admin, $protected))->toBeTrue()
        ->and($policy->reconcile($admin, $protected))->toBeTrue()
        ->and($policy->update($admin, $protected))->toBeFalse()
        ->and($policy->delete($admin, $protected))->toBeFalse()
        ->and($policy->update($admin, $disposable))->toBeTrue();
});
