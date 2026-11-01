<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The environment inventory lives in the infrastructure database, so the
 * connection is the first thing worth pinning down: a model quietly falling
 * back to the application schema would hide an empty table behind a working
 * page.
 */
test('the environment record is read from the infrastructure connection', function () {
    $environment = LabEnvironment::query()->create([
        'container_name' => 'lab-demo-01',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'incus_remote' => 'lab-env',
        'image' => 'images:ubuntu/24.04',
        'cpu_limit' => 2,
        'memory_limit_mb' => 2048,
    ]);

    // Production reads the infrastructure database; the suite aims the same
    // setting at its own throwaway database so the schema, the factory and
    // the rollback all agree.
    expect($environment->getConnectionName())
        ->toBe(config('infrastructure.environment_connection'))
        ->and($environment->getConnectionName())->toBe(config('database.default'))
        ->and($environment->exists)->toBeTrue()
        // Present in the infrastructure database, absent from the app one.
        ->and(DB::connection($environment->getConnectionName())
            ->table('lab_environments')->where('container_name', 'lab-demo-01')->count())->toBe(1)
        ->and(DB::table('labs')->count())->toBe(0);
});

test('lifecycle status and runtime status stay separate', function () {
    $ready = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    // Ready describes the lifecycle, Running describes the container. An
    // environment that has been stopped is still a healthy environment.
    expect($ready->status)->toBe(EnvironmentStatus::Ready)
        ->and($ready->runtime_status)->toBe(RuntimeStatus::Stopped)
        ->and($ready->isRunning())->toBeFalse()
        ->and($ready->hasStateMismatch())->toBeTrue();
});

test('an unknown runtime is never treated as healthy or as a mismatch', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Unknown,
    ]);

    // Without a real observation there is nothing to disagree with.
    expect($environment->hasStateMismatch())->toBeFalse()
        ->and($environment->runtime_status->isKnown())->toBeFalse();
});

test('a running container on a stopped environment is flagged as a mismatch', function () {
    $environment = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    expect($environment->hasStateMismatch())->toBeTrue();
});

test('an in flight status reports that conflicting actions must be hidden', function () {
    foreach ([
        EnvironmentStatus::Provisioning,
        EnvironmentStatus::Configuring,
        EnvironmentStatus::Resetting,
        EnvironmentStatus::Deleting,
    ] as $status) {
        expect($status->isInFlight())->toBeTrue();
    }

    foreach ([
        EnvironmentStatus::Ready,
        EnvironmentStatus::Stopped,
        EnvironmentStatus::Error,
    ] as $status) {
        expect($status->isInFlight())->toBeFalse();
    }
});

test('container names are restricted before they reach a shell', function () {
    // The name becomes an Incus instance name, an OpenTofu workspace suffix
    // and an Ansible extra variable, so anything a shell could interpret is
    // rejected outright rather than escaped.
    expect(LabEnvironment::isValidContainerName('lab-demo-01'))->toBeTrue()
        ->and(LabEnvironment::isValidContainerName('abc'))->toBeTrue()
        ->and(LabEnvironment::isValidContainerName('ab'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('Lab-Demo'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('lab_demo'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('lab.demo'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('lab demo'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('lab;rm -rf /'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('$(whoami)'))->toBeFalse()
        ->and(LabEnvironment::isValidContainerName('lab-tofu-test'))->toBeTrue();
});

test('each environment derives its own workspace from its container name', function () {
    expect(LabEnvironment::workspaceFor('lab-demo-01'))->toBe('env-lab-demo-01')
        ->and(LabEnvironment::workspaceFor('lab-web-smoke'))->toBe('env-lab-web-smoke');
});

test('the terminal is only offered when the environment can actually serve one', function () {
    $ready = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'terminal_enabled' => true,
    ]);

    $stopped = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
        'terminal_enabled' => true,
    ]);

    $disabled = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'terminal_enabled' => false,
    ]);

    expect($ready->isTerminalAvailable())->toBeTrue()
        ->and($stopped->isTerminalAvailable())->toBeFalse()
        ->and($disabled->isTerminalAvailable())->toBeFalse();
});
