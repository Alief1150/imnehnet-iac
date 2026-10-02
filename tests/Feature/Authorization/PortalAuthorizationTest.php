<?php

use App\Enums\EnvironmentStatus;
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

test('an administrator may manage every environment and a student may manage none', function () {
    $policy = new LabEnvironmentPolicy;
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();
    $environment = LabEnvironment::factory()->create();

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $environment))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $environment))->toBeTrue()
        ->and($policy->delete($admin, $environment))->toBeTrue()
        ->and($policy->reconcile($admin, $environment))->toBeTrue();

    // The sprint is administrator only. Owning an environment does not grant
    // anything yet, which is the rule rather than an omission.
    expect($policy->viewAny($student))->toBeFalse()
        ->and($policy->view($student, $environment))->toBeFalse()
        ->and($policy->create($student))->toBeFalse()
        ->and($policy->update($student, $environment))->toBeFalse()
        ->and($policy->delete($student, $environment))->toBeFalse();
});

test('a disabled account cannot manage an environment even with the admin role', function () {
    // The panel already refuses these accounts, so this only matters for the
    // callers that reach the policy without going through the panel: table
    // action closures, queued jobs, and the lifecycle service itself.
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
        'status' => EnvironmentStatus::Ready,
    ]);

    // Reading the actual state is the point of reconciliation existing, so it
    // stays allowed. Changing it is what the protection is for.
    expect($policy->view($admin, $protected))->toBeTrue()
        ->and($policy->reconcile($admin, $protected))->toBeTrue()
        ->and($policy->update($admin, $protected))->toBeFalse()
        ->and($policy->delete($admin, $protected))->toBeFalse()
        ->and($policy->update($admin, $disposable))->toBeTrue();
});
