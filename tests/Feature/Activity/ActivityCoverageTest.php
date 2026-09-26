<?php

use App\Contracts\LabProvisioner;
use App\Data\ProvisionedLab;
use App\Enums\LabActualState;
use App\Enums\LabOperationState;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Jobs\ExecuteLabOperationJob;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\LabLifecycleService;
use Filament\Auth\Pages\Login;
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
    $student = User::factory()->create(['role' => 'student', 'last_login_at' => null]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $student->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect($student->fresh()->last_login_at)->not->toBeNull();

    $log = ActivityLog::query()->where('action', 'LOGIN')->sole();

    expect($log->actor_id)->toBe($student->id)
        ->and($log->description)->toContain($student->email);
});

test('a wrong password is not recorded as a sign in', function () {
    $student = User::factory()->create(['role' => 'student', 'last_login_at' => null]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $student->email,
            'password' => 'definitely-wrong',
        ])
        ->call('authenticate');

    expect(ActivityLog::query()->where('action', 'LOGIN')->count())->toBe(0)
        ->and($student->fresh()->last_login_at)->toBeNull();
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

test('a lifecycle result records the state transition and the container', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();

    // Delete is only valid for a container the compute plane still reports.
    $lab = Lab::factory()->for($student, 'owner')->create([
        'actual_state' => LabActualState::Running,
        'operation_state' => LabOperationState::Idle,
    ]);

    $queued = app(LabLifecycleService::class)->request($admin, $lab, 'delete');

    $provisioner = new class implements LabProvisioner
    {
        public function provision(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function start(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function stop(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function reset(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function destroy(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(
                successful: true,
                containerName: $lab->container_name,
                status: 'deleted',
            );
        }

        public function observe(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }
    };

    (new ExecuteLabOperationJob($queued->id))->handle(
        $provisioner,
        app(ActivityLogService::class),
    );

    $log = ActivityLog::query()->where('action', 'DELETE_LAB_RESULT')->sole();

    expect($log->metadata['from_state'])->toBe(LabActualState::Running->value)
        ->and($log->metadata['container_name'])->toBe($lab->container_name)
        ->and($log->description)->toContain($lab->container_name);
});

test('unknown metadata keys are dropped rather than persisted', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $log = app(ActivityLogService::class)->record(
        actor: $admin,
        action: 'TEST',
        resource: $admin,
        status: 'completed',
        description: 'metadata filtering',
        metadata: [
            'container_name' => 'lab-test',
            'password' => 'hunter2',
            'api_token' => 'secret',
        ],
    );

    expect($log->metadata)->toBe(['container_name' => 'lab-test']);
});

test('the events the specification lists are all recorded somewhere', function () {
    $expectations = [
        'CREATE_USER' => app_path('Filament/Resources/Users/Pages/CreateUser.php'),
        'UPDATE_USER' => app_path('Observers/UserObserver.php'),
        'LOGIN' => app_path('Listeners/RecordPortalLogin.php'),
    ];

    foreach ($expectations as $action => $file) {
        expect(file_get_contents($file))->toContain($action);
    }

    foreach (['provision', 'start', 'stop', 'reset', 'delete'] as $type) {
        expect(file_get_contents(app_path('Jobs/ExecuteLabOperationJob.php')))->toContain("'{$type}' =>");
    }
});
