<?php

use App\Data\ProcessResult;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\ProvisionEnvironment;
use App\Jobs\ResetEnvironment;
use App\Enums\UserRole;
use App\Jobs\StartEnvironment;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeLifecycleDriver;

uses(RefreshDatabase::class);

/**
 * Creating an environment is two commands, not one.
 *
 * OpenTofu creates the container. Ansible then makes it usable: the student
 * account the terminal logs in as, the packages an exercise needs, the shell.
 * That second phase was declared on the driver contract, implemented on both
 * drivers, given its own in-flight status and rendered in the table, and then
 * called by nothing. Environments were recorded READY with no student account
 * inside them, so the portal offered a shell that did not exist, and the reason
 * was invisible because nothing was recorded about the phase either.
 */

function wrapOutputs(array $outputs): string
{
    // The shape "tofu output -json" actually produces.
    $descriptors = [];

    foreach ($outputs as $name => $value) {
        $descriptors[$name] = ['sensitive' => false, 'type' => 'string', 'value' => $value];
    }

    return json_encode($descriptors, JSON_UNESCAPED_SLASHES) ?: '{}';
}

test('a create runs the bootstrap after the apply and settles with the address', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willReturnFor('provision', new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 0,
        stdout: wrapOutputs(['container_ipv4' => 'eth0=10.20.0.118', 'container_status' => 'Running']),
        stderr: '',
        durationMs: 40_000.0,
    ));

    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    (new ProvisionEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    // Order matters as much as presence: bootstrapping a container that was never
    // applied would be guessing, and applying after the bootstrap would overwrite
    // what the bootstrap made.
    expect($driver->calls)->toBe(['provision:lab-web-smoke', 'configure:lab-web-smoke']);

    $settled = $environment->fresh();

    expect($settled->status)->toBe(EnvironmentStatus::Ready)
        ->and($settled->runtime_status)->toBe(RuntimeStatus::Running)
        // The address is read from the apply phase, because the Ansible phase
        // reports no outputs at all. Reading it from the wrong phase is how the
        // record ends up READY with no address.
        ->and($settled->internal_ip)->toBe('10.20.0.118')
        ->and($settled->last_error)->toBeNull();
});

test('a create whose apply fails never reaches the bootstrap', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willReturnFor('provision', new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 1,
        stdout: '',
        stderr: 'Error: creating instance',
        durationMs: 8_000.0,
    ));

    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        // The factory seeds an address on every record. A record mid-creation has
        // none, so it is written explicitly here: that is what "no address was
        // ever observed" looks like, and the factory's value would hide it.
        'internal_ip' => null,
    ]);

    (new ProvisionEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    expect($driver->calls)->toBe(['provision:lab-web-smoke']);

    expect($environment->fresh()->status)->toBe(EnvironmentStatus::Error)
        // The apply failed, so nothing was ever observed and there is nothing to
        // show. Inventing an address here is the one thing this must not do.
        ->and($environment->fresh()->internal_ip)->toBeNull();
});

test('a create whose bootstrap fails is a failure, keeping the real address', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willReturnFor('provision', new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 0,
        stdout: wrapOutputs(['container_ipv4' => 'eth0=10.20.0.118', 'container_status' => 'Running']),
        stderr: '',
        durationMs: 40_000.0,
    ));
    $driver->willReturnFor('configure', new ProcessResult(
        command: ['ansible-playbook', 'playbooks/student-bootstrap.yml'],
        exitCode: 2,
        stdout: 'fatal: [lab-web-smoke]: UNREACHABLE',
        stderr: '',
        durationMs: 20_000.0,
    ));

    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    (new ProvisionEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    $settled = $environment->fresh();

    // A container with no student account is not a usable lab environment, so it
    // must not be recorded READY. Recording it READY is how the portal came to
    // promise a shell that was never there.
    expect($settled->status)->toBe(EnvironmentStatus::Error)
        // The address is real and is what an operator needs in order to look at
        // the half-built container, so it is kept rather than discarded.
        ->and($settled->internal_ip)->toBe('10.20.0.118')
        ->and($settled->last_error)->toContain('UNREACHABLE');
});

test('a create whose bootstrap throws is still recorded as a failure', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willThrow('configure', new RuntimeException('ansible-playbook is not installed'));

    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    (new ProvisionEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    $settled = $environment->fresh();

    // An exception escaping the job would leave the record in CONFIGURING for
    // ever, with no explanation anywhere on screen.
    expect($settled->status)->toBe(EnvironmentStatus::Error)
        ->and($settled->last_error)->toContain('ansible-playbook is not installed');
});

test('a reset bootstraps the container it just recreated', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willReturnFor('reset', new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 0,
        stdout: wrapOutputs(['container_ipv4' => 'eth0=10.20.0.140', 'container_status' => 'Running']),
        stderr: '',
        durationMs: 55_000.0,
    ));

    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Resetting,
        'internal_ip' => '10.20.0.118',
    ]);

    (new ResetEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    expect($driver->calls)->toBe(['reset:lab-web-smoke', 'configure:lab-web-smoke']);

    $settled = $environment->fresh();

    expect($settled->status)->toBe(EnvironmentStatus::Ready)
        // A reset destroys and recreates, so the address is expected to change and
        // is re-read rather than kept from before.
        ->and($settled->internal_ip)->toBe('10.20.0.140');
});

test('a reset whose bootstrap fails keeps the new address and says so', function () {
    $driver = new FakeLifecycleDriver();
    $driver->willReturnFor('reset', new ProcessResult(
        command: ['tofu', 'apply'],
        exitCode: 0,
        stdout: wrapOutputs(['container_ipv4' => 'eth0=10.20.0.140', 'container_status' => 'Running']),
        stderr: '',
        durationMs: 55_000.0,
    ));
    $driver->willReturnFor('configure', new ProcessResult(
        command: ['ansible-playbook', 'playbooks/student-bootstrap.yml'],
        exitCode: 2,
        stdout: 'TASK [bootstrap] FAILED',
        stderr: '',
        durationMs: 15_000.0,
    ));

    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Resetting,
        'internal_ip' => '10.20.0.118',
    ]);

    (new ResetEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    $settled = $environment->fresh();

    expect($settled->status)->toBe(EnvironmentStatus::Error)
        ->and($settled->internal_ip)->toBe('10.20.0.140')
        ->and($settled->last_error)->toContain('TASK [bootstrap] FAILED');
});

test('a start and a stop never re-run the bootstrap', function () {
    // The container already exists and its bootstrap is already inside it.
    // Re-running it on every start would be slow work with no effect, and the
    // reason the phase belongs to create and reset only.
    $driver = new FakeLifecycleDriver();
    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Stopped,
        // Start and stop follow what the container is actually doing rather than
        // the lifecycle status, so the runtime is what makes Start the offered
        // action here. Leaving the factory default would be a state mismatch, and
        // a mismatch offers neither.
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    app(EnvironmentLifecycleService::class)->request(
        User::factory()->create(['role' => UserRole::Admin]),
        $environment,
        'start',
    );

    (new StartEnvironment($environment->id))->handle($driver, app(EnvironmentLifecycleService::class));

    expect($driver->calls)->toBe(['start:lab-web-smoke']);
});