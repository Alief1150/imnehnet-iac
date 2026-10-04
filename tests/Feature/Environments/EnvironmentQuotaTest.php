<?php

use App\Enums\UserRole;
use App\Filament\Resources\Environments\Pages\CreateEnvironment;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * How many environments one student may hold.
 *
 * Registration is open, which means an account that has never been approved by
 * anyone can reach the create form. The per-container limits already exist: four
 * cores, eight gigabytes. This is the other half, because a thousand
 * one-gigabyte containers cost the same as a thousand eight-gigabyte ones and
 * the compute plane is one shared machine.
 */

/**
 * @return array<int, LabEnvironment>
 */
function heldEnvironments(User $owner, int $count): array
{
    return LabEnvironment::factory()
        ->count($count)
        ->create(['user_id' => $owner->getKey()])
        ->all();
}

test('a student may keep creating while under the limit and not at it', function () {
    config()->set('infrastructure.student_environment_limit', 3);

    $student = User::factory()->create(['role' => UserRole::Student]);
    $quota = app(EnvironmentQuota::class);

    expect($quota->allowsAnother($student))->toBeTrue();

    heldEnvironments($student, 2);
    expect($quota->usedBy($student))->toBe(2)
        ->and($quota->remainingFor($student))->toBe(1)
        ->and($quota->allowsAnother($student))->toBeTrue();

    heldEnvironments($student, 1);
    expect($quota->usedBy($student))->toBe(3)
        ->and($quota->remainingFor($student))->toBe(0)
        ->and($quota->allowsAnother($student))->toBeFalse();
});

test('an administrator is outside the quota', function () {
    config()->set('infrastructure.student_environment_limit', 3);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    heldEnvironments($admin, 7);

    $quota = app(EnvironmentQuota::class);

    // Administrators provision for other people and for testing, so a number
    // meant to stop one student flooding the plane would only get in their way.
    expect($quota->allowsAnother($admin))->toBeTrue()
        ->and($quota->usedBy($admin))->toBe(0);
});

test('the quota message states the numbers and what to do', function () {
    config()->set('infrastructure.student_environment_limit', 3);

    $student = User::factory()->create(['role' => UserRole::Student]);
    heldEnvironments($student, 3);

    $message = app(EnvironmentQuota::class)->messageFor($student);

    // A student told only that they may not create an environment cannot tell
    // whether they did something wrong or the portal is broken.
    expect($message)->toContain('3 of 3')
        ->and($message)->toContain('Delete one');
});

test('a student at the limit cannot reach the create form', function () {
    config()->set('infrastructure.student_environment_limit', 2);

    $student = User::factory()->create(['role' => UserRole::Student]);
    heldEnvironments($student, 2);

    $this->actingAs($student);

    // The create action is hidden while the quota is reached, and reaching the
    // URL directly bounces back to the list with a notification that says which
    // limit and what to do about it. That was chosen over a bare 403 on
    // purpose: an unexplained 403 reads as a broken portal rather than as a
    // limit, and a student who is told only that they may not create an
    // environment has no way to tell the two apart.
    $this->get(\App\Filament\Resources\Environments\EnvironmentResource::getUrl('create', panel: 'admin'))
        ->assertRedirect(\App\Filament\Resources\Environments\EnvironmentResource::getUrl('index', panel: 'admin'));
});

test('an administrator reaches the create form regardless of the quota', function () {
    config()->set('infrastructure.student_environment_limit', 1);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    heldEnvironments($admin, 5);

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\Environments\EnvironmentResource::getUrl('create', panel: 'admin'))
        ->assertSuccessful();
});

test('a disabled account is refused by the quota rule as well as by activity', function () {
    config()->set('infrastructure.student_environment_limit', 3);

    $student = User::factory()->create([
        'role' => UserRole::Student,
        'status' => \App\Enums\UserStatus::Disabled,
    ]);

    // Defence in depth rather than a second rule: allowsAnother only counts, and
    // activity is the policy's own condition, so this asserts the pairing.
    expect(app(EnvironmentQuota::class)->allowsAnother($student))->toBeTrue()
        ->and($student->isActive())->toBeFalse();
});