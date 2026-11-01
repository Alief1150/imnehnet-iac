<?php

use App\Data\ProcessResult;
use App\Enums\ActivityAction;
use App\Enums\EnvironmentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
// The form both panels use, rather than Filament's base class. The one
// installed here answers yes to the panel check and lets
// RedirectToOwnPanel decide where the account belongs, and a test of the
// wrong class would pass while the real form behaved differently.
use App\Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Activity logs are immutable, so a test must scope its assertions rather
// than clear the table first.

test('creating a student account is audited without storing the password', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Student One',
            'email' => 'student01@example.com',
            'role' => 'student',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $log = ActivityLog::query()
        ->where('action', 'CREATE_USER')
        ->where('resource_id', (string) User::query()->where('email', 'student01@example.com')->sole()->id)
        ->latest('id')
        ->first();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->description)->toContain('student01@example.com')
        ->and($log->resource_type)->toBe(User::class)
        // The one-time password is shown to the operator, never persisted.
        ->and(json_encode($log->metadata))->not->toContain('password');

    $user = User::query()->where('email', 'student01@example.com')->sole();

    expect($user->must_change_password)->toBeTrue();
});

test('an account created with a chosen password does not demand a change', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Explicit',
            'email' => 'explicit@example.com',
            'role' => 'student',
            'status' => 'active',
            'password' => 'Chosen#Secret99',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'explicit@example.com')->sole();

    expect($user->must_change_password)->toBeFalse();
});

test('changing a role or status is audited with the previous value', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

    $this->actingAs($admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $student->getKey()])
        ->fillForm(['status' => 'disabled'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($student->fresh()->status->value)->toBe('disabled');

    $log = ActivityLog::query()->where('action', 'UPDATE_USER')->sole();

    expect($log->metadata['changed_fields'])->toBe(['status'])
        ->and($log->metadata['previous_values']['status'])->toBe(['from' => 'active', 'to' => 'disabled']);
});

test('an edit that changes no audited field writes no log', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

    $this->actingAs($admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $student->getKey()])
        ->fillForm(['name' => 'Renamed Student'])
        ->call('save');

    expect(ActivityLog::query()->where('action', 'UPDATE_USER')->count())->toBe(0);
});

test('a sign in is audited and stamps the last login time', function () {
    // An administrator, because this is the administrative sign-in form.
    // A student's own form is the same class on the student panel.
    $admin = User::factory()->create(['role' => UserRole::Admin, 'last_login_at' => null]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->last_login_at)->not->toBeNull();

    $log = ActivityLog::query()->where('action', 'LOGIN')->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->description)->toContain($admin->email);
});

test('a wrong password is not recorded as a sign in', function () {
    // An administrator, because this is the administrative sign-in form.
    // A student's own form is the same class on the student panel.
    $admin = User::factory()->create(['role' => UserRole::Admin, 'last_login_at' => null]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $admin->email,
            'password' => 'definitely-wrong',
        ])
        ->call('authenticate');

    expect(ActivityLog::query()->where('action', 'LOGIN')->count())->toBe(0)
        ->and($admin->fresh()->last_login_at)->toBeNull();
});

test('the request context is recorded so an operator can see where it came from', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Context Student',
            'email' => 'context@example.com',
            'role' => 'student',
            'status' => 'active',
        ])
        ->call('create');

    $log = ActivityLog::query()->where('action', 'CREATE_USER')->sole();

    expect($log->ip_address)->not->toBeNull()
        ->and($log->user_agent)->not->toBeNull();
});

test('a failed operation is audited as a failure, against the environment', function () {
    // The successful path is asserted in EnvironmentLifecycleTest. What matters
    // here is that a failure reaches the audit trail at all: an operation that
    // quietly stopped would leave the record claiming progress that never
    // finished, which is the failure mode an audit log exists to prevent.
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    $result = new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 1,
        stdout: '',
        stderr: 'Error: creating container: unable to bind address',
        durationMs: 900.0,
    );

    app(EnvironmentLifecycleService::class)->settle($admin, $environment, 'create', $result);

    $log = ActivityLog::query()
        ->where('action', ActivityAction::CreateFailed->value)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->resource_type)->toBe(LabEnvironment::class)
        ->and($log->resource_id)->toBe((string) $environment->getKey())
        ->and($log->status)->toBe('failed')
        ->and($log->metadata['container_name'] ?? null)->toBe('lab-web-smoke')
        // The record agrees with the log. A log saying failed next to a record
        // still saying PROVISIONING is the disagreement this catches.
        ->and($environment->fresh()->status)->toBe(EnvironmentStatus::Error);
});
test('every action the specification lists exists and can be named', function () {
    // The list is asserted rather than grepped out of the source. A test that
    // searches files for strings passes as long as the strings are somewhere in
    // the file, including inside a comment, and fails when an action is built by
    // concatenation. Naming them makes the set exhaustive by construction.
    $required = [
        'CREATE_REQUESTED', 'CREATE_COMPLETED', 'CREATE_FAILED',
        'START_REQUESTED', 'START_COMPLETED', 'START_FAILED',
        'STOP_REQUESTED', 'STOP_COMPLETED', 'STOP_FAILED',
        'RESET_REQUESTED', 'RESET_COMPLETED', 'RESET_FAILED',
        'DELETE_REQUESTED', 'DELETE_COMPLETED', 'DELETE_FAILED',
        'TERMINAL_OPENED',
        'RECONCILIATION_DETECTED', 'RECONCILIATION_COMPLETED',
    ];

    $have = array_map(fn (ActivityAction $a): string => $a->value, ActivityAction::cases());

    expect($have)->toContain(...$required);

    foreach (ActivityAction::operations() as $operation) {
        expect(ActivityAction::requested($operation)->value)->toBe(strtoupper($operation).'_REQUESTED')
            ->and(ActivityAction::completed($operation)->value)->toBe(strtoupper($operation).'_COMPLETED')
            ->and(ActivityAction::failed($operation)->value)->toBe(strtoupper($operation).'_FAILED');
    }
});

test('an unknown lifecycle operation is refused rather than logged as something generic', function () {
    // Falling back would produce an audit entry that cannot be filtered by
    // operation, which is worse than refusing: it would be discovered by whoever
    // later needs to know what happened.
    expect(fn () => ActivityAction::requested('reboot'))
        ->toThrow(RuntimeException::class, 'Unknown lifecycle operation');
});
