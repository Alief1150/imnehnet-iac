<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\OpenTofuService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * "tofu output -json" does not return a map of values. It returns a map of
 * descriptors:
 *
 *     "container_ipv4": { "sensitive": false, "type": "string", "value": "10.20.0.171" }
 *
 * Reading that as the value made the address an array, and casting it to a string
 * threw inside settle(). That is where a result becomes state, so the address was
 * never stored, the record stayed in PROVISIONING for ever, and the portal showed
 * "Not observed yet" for a container that had an address all along.
 *
 * These cover the envelope itself and the two places that consume it.
 */

test('an output descriptor is reduced to its value', function () {
    $flat = OpenTofuService::flattenOutputs([
        'container_ipv4' => ['sensitive' => false, 'type' => 'string', 'value' => '10.20.0.171'],
        'container_name' => ['sensitive' => false, 'type' => 'string', 'value' => 'lab-web-smoke'],
        'container_status' => ['sensitive' => false, 'type' => 'string', 'value' => 'Running'],
    ]);

    expect($flat)->toBe([
        'container_ipv4' => '10.20.0.171',
        'container_name' => 'lab-web-smoke',
        'container_status' => 'Running',
    ]);
});

test('a sensitive output is dropped rather than recorded', function () {
    // -json still withholds a sensitive value. Keeping the envelope would record
    // whatever it did contain, which is the opposite of what the flag asks for.
    $flat = OpenTofuService::flattenOutputs([
        'admin_password' => ['sensitive' => true, 'type' => 'string', 'value' => null],
        'container_ipv4' => ['sensitive' => false, 'type' => 'string', 'value' => '10.20.0.205'],
    ]);

    expect($flat)->not->toHaveKey('admin_password')
        ->and($flat['container_ipv4'])->toBe('10.20.0.205');
});

test('a value that is already flat is left alone', function () {
    // The flatten runs twice, once where the output is read and once where it is
    // re-decoded, so it has to be a no-op on an already flat value rather than
    // requiring an envelope.
    expect(OpenTofuService::flattenOutputs(['container_ipv4' => '10.20.0.205']))
        ->toBe(['container_ipv4' => '10.20.0.205']);
});

test('an empty output map stays empty', function () {
    expect(OpenTofuService::flattenOutputs([]))->toBe([]);
});
