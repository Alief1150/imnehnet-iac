<?php

use App\Infrastructure\Health\CommandRunner;
use App\Services\InfrastructureStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

test('the management plane reports itself healthy without calling back over http', function () {
    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['iaas_mgmt']['status'])->toBe('healthy')
        ->and($summary['iaas_mgmt']['group'])->toBe('management_plane')
        // A loopback HTTP probe would deadlock against the single threaded
        // development server, so the source must not be an HTTP endpoint.
        ->and($summary['iaas_mgmt']['source'])->not->toContain('http');
});

test('the management plane reports offline when its database is unreachable', function () {
    DB::shouldReceive('selectOne')->andThrow(new RuntimeException('connection refused'));

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['iaas_mgmt']['status'])->toBe('offline')
        ->and($summary['database']['status'])->toBe('offline');
});

test('the compute plane is probed over the lab env api and grouped separately', function () {
    config()->set('infrastructure.lab_env_api_url', 'https://192.168.100.20:8443/1.0');

    Http::fake([
        'https://192.168.100.20:8443/*' => Http::response(['status' => 'Success'], 200),
    ]);

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['lab_env']['status'])->toBe('healthy')
        ->and($summary['lab_env']['group'])->toBe('compute_plane');
});

test('the compute plane reports offline when the lab host cannot be reached', function () {
    config()->set('infrastructure.lab_env_api_url', 'https://192.168.100.20:8443/1.0');

    Http::fake(['*' => Http::response('', 503)]);

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['lab_env']['status'])->toBe('offline');
});
