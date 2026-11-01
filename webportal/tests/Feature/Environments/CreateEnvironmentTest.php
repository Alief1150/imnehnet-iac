<?php

use App\Enums\ActivityAction;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\ProvisionEnvironment;
use App\Models\ActivityLog;
use App\Models\LabEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * The create path had never worked.
 *
 * 'create' was listed in JOB and IN_FLIGHT but in no ALLOWED list, so
 * allowedActions never returned it and request('create') was refused for every
 * record in every state. The form recorded an environment and the refusal left a
 * row claiming PROVISIONING with no job, no container and no address.
 *
 * These cover the rule that replaced it: create is available exactly while
 * nothing has ever been provisioned for the record.
 */

test('a record that has never been provisioned may be created', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
        'provisioned_at' => null,
    ]);

    expect(lifecycle()->isActionAllowed($environment, 'create'))->toBeTrue();
});

test('an environment that already exists may not be created again', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'provisioned_at' => now()->subHour(),
    ]);

    // Once provisioned_at is set the container exists, and its lifecycle is start,
    // stop, reset or delete. Creating it again would be a second container
    // wearing the same record.
    expect(lifecycle()->isActionAllowed($environment, 'create'))->toBeFalse();
});

test('a record already being created cannot be created a second time', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Provisioning,
        'runtime_status' => RuntimeStatus::Unknown,
        'provisioned_at' => null,
    ]);

    expect(lifecycle()->isActionAllowed($environment, 'create'))->toBeFalse();
});

test('the protected reference environment is never created by the portal', function () {
    config()->set('infrastructure.protected_containers', ['lab-tofu-test']);
    config()->set('infrastructure.protected_workspaces', ['default']);

    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
        'provisioned_at' => null,
    ]);

    // The isActionAllowed rule above is about history; the protected guard runs
    // before it in request() and has to refuse regardless.
    expect(lifecycle()->isActionAllowed($environment, 'create'))->toBeTrue();

    expect(fn () => lifecycle()->request(anAdmin(), $environment, 'create'))
        ->toThrow(DomainException::class, 'protected reference environment');
});

test('requesting a create moves the record in flight and queues the job', function () {
    Queue::fake();

    $admin = anAdmin();
    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
        'provisioned_at' => null,
    ]);

    $queued = lifecycle()->request($admin, $environment, 'create');

    // The record must never report success while the work is still running.
    expect($queued->status)->toBe(EnvironmentStatus::Provisioning)
        ->and($queued->runtime_status)->toBe(RuntimeStatus::Unknown);

    Queue::assertPushed(ProvisionEnvironment::class, fn (ProvisionEnvironment $job): bool =>
        $job->environmentId === $environment->id);

    $log = ActivityLog::query()->where('action', ActivityAction::CreateRequested->value)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['from_state'])->toBe('STOPPED')
        ->and($log->metadata['to_state'])->toBe('PROVISIONING')
        ->and($log->metadata['requested_action'])->toBe('create');
});

test('a create is refused twice over while one is already in flight', function () {
    Queue::fake();

    $admin = anAdmin();
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
        'provisioned_at' => null,
    ]);

    lifecycle()->request($admin, $environment, 'create');

    // The second request has to be refused rather than queueing a second apply
    // against the same OpenTofu working directory.
    expect(fn () => lifecycle()->request($admin, $environment->refresh(), 'create'))
        ->toThrow(DomainException::class, 'not available from that state');

    Queue::assertPushed(ProvisionEnvironment::class, 1);
});