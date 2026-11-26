<?php

use App\Drivers\LiveLifecycleDriver;
use App\Models\LabEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctfTofuVariables(LabEnvironment $environment, ?bool $running = null): array
{
    // Read the pure variable-building method without contacting Incus or
    // starting OpenTofu. Dependencies are deliberately not constructed.
    $driver = (new ReflectionClass(LiveLifecycleDriver::class))
        ->newInstanceWithoutConstructor();

    $method = new ReflectionMethod(LiveLifecycleDriver::class, 'variables');

    return $method->invoke($driver, $environment, $running);
}

it('retains the Btrfs root and P3 image during stop and resume', function (): void {
    config()->set('infrastructure.btrfs_benchmark_user_id', 0);
    $env = new LabEnvironment([
        'user_id' => 180,
        'container_name' => 'attacker-user-180',
        'image' => 'imnehnet-gi-attacker-p3',
        'storage_pool' => 'imnehnet-btrfs-prod',
        'cpu_limit' => 2,
        'memory_limit_mb' => 1024,
    ]);

    $stop = ctfTofuVariables($env, false);
    $resume = ctfTofuVariables($env, true);

    expect($stop['storage_pool'])->toBe('imnehnet-btrfs-prod')
        ->and($stop['container_image'])->toBe('imnehnet-gi-attacker-p3')
        ->and($stop['desired_running'])->toBeFalse()
        ->and($resume['storage_pool'])->toBe('imnehnet-btrfs-prod')
        ->and($resume['container_image'])->toBe('imnehnet-gi-attacker-p3')
        ->and($resume['desired_running'])->toBeTrue();
});

it('does not override an existing DIR environment after rollout is enabled', function (): void {
    config()->set('infrastructure.ctf_btrfs_rollout_enabled', true);
    config()->set('infrastructure.btrfs_benchmark_user_id', 0);
    $env = new LabEnvironment([
        'user_id' => 181,
        'container_name' => 'victim-user-181',
        'image' => 'imnehnet-gi-victim-v1',
        'storage_pool' => 'default',
        'cpu_limit' => 1,
        'memory_limit_mb' => 1024,
    ]);

    $variables = ctfTofuVariables($env, false);

    expect($variables)->not->toHaveKey('storage_pool')
        ->and($variables['container_image'])->toBe('imnehnet-gi-victim-v1');
});

it('preserves the legacy Kaneki pilot pool and P3 attacker during lifecycle', function (): void {
    config()->set('infrastructure.btrfs_benchmark_user_id', 29);
    $env = new LabEnvironment([
        'user_id' => 29,
        'container_name' => 'attacker-user-29',
        'image' => 'imnehnet-gi-attacker-v1',
        'storage_pool' => null,
        'cpu_limit' => 2,
        'memory_limit_mb' => 1024,
    ]);

    $variables = ctfTofuVariables($env, false);

    expect($variables['storage_pool'])->toBe('imnehnet-btrfs-test')
        ->and($variables['container_image'])->toBe('imnehnet-gi-attacker-p3');
});
