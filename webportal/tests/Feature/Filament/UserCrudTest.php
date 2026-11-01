<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\LabEnvironment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The user table had a View action and nothing else, which reads as a CRUD that
 * is missing the rest of it. These cover the two refusals the delete action makes,
 * because both of them are irreversible if they are wrong: one locks the operator
 * out mid-session, the other leaves nobody able to manage the compute plane.
 */
test('the user resource exposes the full set of pages', function () {
    expect(array_keys(UserResource::getPages()))
        ->toBe(['index', 'edit', 'view']);
});

test('an administrator may delete another account', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($admin);

    expect(ListUsers::mayDelete($student->id))->toBeTrue();
});

test('the account in use is never offered a delete', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $other = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    // Confirming this away would sign the operator out mid-session with no way
    // back in but another administrator.
    expect(ListUsers::mayDelete($admin->id))->toBeFalse()
        ->and(ListUsers::mayDelete($other->id))->toBeTrue();
});

test('the last active administrator is never offered a delete', function () {
    $onlyAdmin = User::factory()->create(['role' => UserRole::Admin]);
    $disabledAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);

    $this->actingAs($onlyAdmin);

    // A disabled administrator does not count as a way back in, so deleting the
    // only active one would still leave nobody able to sign in.
    expect(ListUsers::mayDelete($onlyAdmin->id))->toBeFalse()
        ->and(ListUsers::mayDelete($disabledAdmin->id))->toBeTrue();
});

test('a second administrator makes the first deletable', function () {
    $first = User::factory()->create(['role' => UserRole::Admin]);
    $second = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($first);

    expect(ListUsers::mayDelete($second->id))->toBeTrue();
});

test('an unauthenticated caller is offered nothing', function () {
    $account = User::factory()->create();

    expect(ListUsers::mayDelete($account->id))->toBeFalse();
});

test('deleting an account leaves its environments alone', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => UserRole::Student]);

    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'user_id' => $student->id,
    ]);

    $this->actingAs($admin);
    $student->delete();

    // Removing a container goes through the environment, which runs OpenTofu and
    // logs the result. Taking infrastructure down as a side effect of deleting a
    // login would be a quieter path to the same outcome, with no verification and
    // nothing in the activity log.
    expect($environment->fresh())->not->toBeNull()
        ->and($environment->fresh()->user_id)->toBeNull();
});

test('the users table renders without a server error for an administrator', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($admin)
        ->get(UserResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('STUDENT');
});
