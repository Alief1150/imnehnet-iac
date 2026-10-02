<?php

use App\Data\ProcessResult;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\DeleteEnvironment;
use App\Jobs\ProvisionEnvironment;
use App\Jobs\ResetEnvironment;
use App\Jobs\StartEnvironment;
use App\Jobs\StopEnvironment;
use App\Models\ActivityLog;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeLifecycleDriver;

uses(RefreshDatabase::class);

function lifecycle(): EnvironmentLifecycleService
{
    return app(EnvironmentLifecycleService::class);
}

/**
 * The state guards are the whole point of the lifecycle: they are what stops
 * a second operation racing the first through a shared OpenTofu workspace, and
 * what keeps the reference environment from being operated on at all.
 */
test('actions offered from a settled state follow the specification', function () {
    $ready = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    $stopped = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    $error = LabEnvironment::factory()->error()->create();

    // Start and stop follow what the container is doing, not the lifecycle
    // status: a container that is running is offered Stop and never Start.
    expect(lifecycle()->allowedActions($ready))->toBe(['delete', 'refresh', 'reset', 'stop'])
        ->and(lifecycle()->allowedActions($stopped))->toBe(['delete', 'refresh', 'reset', 'start'])
        // A failed record is not asked to converge anywhere. Only the read-only
        // refresh and the destructive delete are offered.
        ->and(lifecycle()->allowedActions($error))->toBe(['delete', 'refresh']);
});

test('an in flight environment offers no lifecycle action at all', function () {
    foreach ([EnvironmentStatus::Provisioning, EnvironmentStatus::Configuring, EnvironmentStatus::Resetting, EnvironmentStatus::Deleting] as $status) {
        $environment = LabEnvironment::factory()->create(['status' => $status]);

        expect(lifecycle()->allowedActions($environment))->toBe(['refresh']);
    }
});

test('the reference environment is never operated on', function () {
    config(['infrastructure.protected_containers' => ['lab-tofu-test']]);

    $reference = LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    expect(lifecycle()->allowedActions($reference))->toBe(['refresh'])
        ->and(lifecycle()->isActionAllowed($reference, 'delete'))->toBeFalse()
        ->and(fn () => lifecycle()->request(null, $reference, 'delete'))
        ->toThrow(DomainException::class, 'protected reference environment');
});

test('requesting an action marks the record in flight and queues the right job', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    // A stopped container, so Start is the action that makes sense.
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    $queued = lifecycle()->request($admin, $environment, 'start');

    // The record is left showing work in progress, never success.
    expect($queued->status)->toBe(EnvironmentStatus::Provisioning)
        ->and($queued->runtime_status)->toBe(RuntimeStatus::Unknown);

    Queue::assertPushed(StartEnvironment::class, fn (StartEnvironment $job): bool => $job->environmentId === $environment->id);

    $log = ActivityLog::query()->where('action', 'START_REQUESTED')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['from_state'])->toBe('STOPPED')
        ->and($log->metadata['to_state'])->toBe('PROVISIONING')
        ->and($log->metadata['requested_action'])->toBe('start');
});

test('each action dispatches its own job class', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $cases = [
        ['reset', EnvironmentStatus::Ready, RuntimeStatus::Running, ResetEnvironment::class],
        ['delete', EnvironmentStatus::Ready, RuntimeStatus::Running, DeleteEnvironment::class],
        ['start', EnvironmentStatus::Stopped, RuntimeStatus::Stopped, StartEnvironment::class],
        ['stop', EnvironmentStatus::Ready, RuntimeStatus::Running, StopEnvironment::class],
    ];

    foreach ($cases as [$action, $status, $runtime, $job]) {
        $environment = LabEnvironment::factory()->create(['status' => $status, 'runtime_status' => $runtime]);

        lifecycle()->request($admin, $environment, $action);

        Queue::assertPushed($job);
    }

    // create is exercised through the provisioning service rather than here,
    // but the job class must exist and be queueable.
    expect(class_exists(ProvisionEnvironment::class))->toBeTrue()
        ->and(class_exists(StopEnvironment::class))->toBeTrue();
});

test('a rejected action leaves no trace and queues nothing', function () {
    Queue::fake();

    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Provisioning,
        'runtime_status' => RuntimeStatus::Unknown,
    ]);

    expect(fn () => lifecycle()->request(null, $environment, 'delete'))
        ->toThrow(DomainException::class, 'not available from that state');

    Queue::assertNothingPushed();
    expect(ActivityLog::query()->where('action', 'DELETE_REQUESTED')->count())->toBe(0);
});

test('a successful provision persists the real address and runtime', function () {
    $environment = LabEnvironment::factory()->provisioning()->create();

    $result = new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 0,
        stdout: json_encode(['container_ipv4' => 'eth0=10.20.0.205', 'container_status' => 'Running']),
        stderr: '',
        durationMs: 4200.0,
    );

    $settled = lifecycle()->settle(null, $environment, 'create', $result, [
        'container_ipv4' => 'eth0=10.20.0.205',
        'container_status' => 'Running',
    ]);

    // Only the address is kept, never the interface prefix OpenTofu prefixes it
    // with, and the runtime comes from the infrastructure rather than the
    // operator's request.
    expect($settled->status)->toBe(EnvironmentStatus::Ready)
        ->and($settled->runtime_status)->toBe(RuntimeStatus::Running)
        ->and($settled->internal_ip)->toBe('10.20.0.205')
        ->and($settled->provisioned_at)->not->toBeNull()
        ->and($settled->last_error)->toBeNull();

    $log = ActivityLog::query()->where('action', 'CREATE_COMPLETED')->latest('id')->first();

    expect($log->metadata['runtime_status'])->toBe('Running')
        ->and($log->metadata['internal_ip'])->toBe('10.20.0.205');
});

test('a missing runtime observation becomes unknown, never stopped', function () {
    $environment = LabEnvironment::factory()->provisioning()->create();

    $result = new ProcessResult(['tofu', 'apply'], 0, '{}', '', 10.0);

    $settled = lifecycle()->settle(null, $environment, 'create', $result, []);

    // No observation is not the same as a stopped container, and reporting it
    // as stopped would hide a broken control plane behind a healthy looking
    // dashboard.
    expect($settled->runtime_status)->toBe(RuntimeStatus::Unknown)
        ->and($settled->status)->toBe(EnvironmentStatus::Ready);
});

test('a failure stores a sanitised message and never a secret', function () {
    $environment = LabEnvironment::factory()->provisioning()->create();

    $result = new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 1,
        stdout: '',
        stderr: 'Error: failed to authenticate with password=hunter2 at /home/iaas/.config/incus/client.key',
        durationMs: 900.0,
    );

    $settled = lifecycle()->settle(null, $environment, 'create', $result, []);

    expect($settled->status)->toBe(EnvironmentStatus::Error)
        ->and($settled->runtime_status)->toBe(RuntimeStatus::Unknown)
        ->and($settled->last_error)->not->toBeNull();

    // The whole audit trail must be free of the value that was in the output.
    expect($settled->last_error)->not->toContain('hunter2')
        ->and(json_encode(ActivityLog::query()->get()->map->metadata->toArray()))
        ->not->toContain('hunter2');
});

test('the record is only removed once the infrastructure confirms destruction', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    $id = $environment->id;

    // A failed destroy must leave the record in place. Removing it first would
    // leave a running container with nothing tracking it.
    $failed = new ProcessResult(['tofu', 'destroy'], 1, '', 'Error: cannot reach Incus', 100.0);
    $stillThere = lifecycle()->settle(null, $environment, 'delete', $failed, []);

    expect($stillThere->exists)->toBeTrue()
        ->and(LabEnvironment::query()->find($id))->not->toBeNull()
        ->and($stillThere->status)->toBe(EnvironmentStatus::Error);

    $succeeded = new ProcessResult(['tofu', 'destroy'], 0, '', '', 100.0);
    lifecycle()->settle(null, $stillThere, 'delete', $succeeded, []);

    expect(LabEnvironment::query()->find($id))->toBeNull()
        ->and(ActivityLog::query()->where('action', 'DELETE_COMPLETED')->count())->toBe(1);
});

test('reconciliation refreshes the runtime without touching the desired state', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    // The container turns out to be stopped behind the portal's back.
    $observation = new ProcessResult(
        ['tofu', 'output', '-json'],
        0,
        json_encode(['container_status' => 'Stopped', 'container_ipv4' => 'eth0=10.20.0.205']),
        '',
        30.0,
    );

    $reconciled = lifecycle()->reconcile($environment, $observation);

    // Desired state is READY and stays READY. Silently downgrading it to
    // STOPPED would erase what the operator asked for.
    expect($reconciled->status)->toBe(EnvironmentStatus::Ready)
        ->and($reconciled->runtime_status)->toBe(RuntimeStatus::Stopped)
        ->and($reconciled->hasStateMismatch())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'RECONCILIATION_DETECTED')->count())->toBe(1);
});

test('a driver that throws leaves the record in error rather than half done', function () {
    $driver = (new FakeLifecycleDriver)->willThrow(
        'provision',
        new RuntimeException('the provider exploded')
    );

    $environment = LabEnvironment::factory()->provisioning()->create();

    // handle() is what the queue worker calls. A driver that throws must still
    // end with the record in ERROR, not left in PROVISIONING for ever.
    (new ProvisionEnvironment($environment->id))->handle($driver, lifecycle());

    $settled = $environment->fresh();

    expect($settled->status)->toBe(EnvironmentStatus::Error)
        ->and($settled->last_error)->toContain('the provider exploded')
        ->and(ActivityLog::query()->where('action', 'CREATE_FAILED')->count())->toBe(1);
});
