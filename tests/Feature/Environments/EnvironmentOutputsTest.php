<?php

use App\Data\ProcessResult;
use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use App\Services\OpenTofuService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The address OpenTofu reports arrives wrapped, and settle() is where it has to
 * be stored.
 *
 * Reading the descriptor as the value made the address an array, and the cast to
 * string threw inside settle(), which is the only place a result becomes state.
 * The record stayed in PROVISIONING for ever, the portal showed "Not observed
 * yet", and the container had an address the whole time.
 *
 * The envelope itself is covered in tests/Unit; these cover the consumption.
 */

test('settling a create stores the real address from a wrapped output', function () {
    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
    ]);

    $outputs = OpenTofuService::flattenOutputs([
        'container_ipv4' => ['sensitive' => false, 'type' => 'string', 'value' => 'eth0=10.20.0.205'],
        'container_status' => ['sensitive' => false, 'type' => 'string', 'value' => 'Running'],
    ]);

    app(App\Services\EnvironmentLifecycleService::class)->settle(
        User::factory()->create(),
        $environment,
        'create',
        new App\Data\ProcessResult(['tofu', 'apply'], 0, json_encode($outputs), '', 1200.0),
        $outputs,
    );

    $settled = $environment->fresh();

    expect($settled->status)->toBe(EnvironmentStatus::Ready)
        ->and($settled->runtime_status)->toBe(RuntimeStatus::Running)
        // The interface prefix OpenTofu adds is stripped; the address is stored,
        // not the raw output line.
        ->and($settled->internal_ip)->toBe('10.20.0.205')
        ->and($settled->provisioned_at)->not->toBeNull();
});

test('an address of an unexpected shape clears the stale one instead of throwing', function () {
    $environment = LabEnvironment::factory()->provisioning()->create([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'internal_ip' => '10.20.0.99',
    ]);

    // Read before the settle, because the settle is the thing under test and it
    // clears the column.
    expect($environment->fresh()->internal_ip)->toBe('10.20.0.99');

    // Deliberately not flattened, as if some other path produced this.
    $settled = app(App\Services\EnvironmentLifecycleService::class)->settle(
        User::factory()->create(),
        $environment,
        'create',
        new App\Data\ProcessResult(['tofu', 'apply'], 0, '{}', '', 1200.0),
        ['container_ipv4' => ['unexpected' => 'shape']],
    );

    // A missing address is visible and recoverable. Throwing here is what left a
    // real environment stuck in PROVISIONING with nothing to explain it.
    // The stale address is cleared rather than kept. Create and reset can hand
    // the container a different address, so an address left over from before
    // looks like a current one, which is worse than showing none.
    expect($settled->fresh()->status)->toBe(EnvironmentStatus::Ready)
        ->and($settled->fresh()->internal_ip)->toBeNull()
        ->and($settled->fresh()->runtime_status)->toBe(RuntimeStatus::Unknown);
});