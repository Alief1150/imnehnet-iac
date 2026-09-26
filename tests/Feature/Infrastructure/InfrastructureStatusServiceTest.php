<?php

use App\Infrastructure\Health\CommandRunner;
use App\Services\InfrastructureStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

test('infrastructure summary maps real provider results without inventing health', function () {
    config()->set('infrastructure.prometheus_health_url', 'http://127.0.0.1:9090/-/healthy');
    config()->set('infrastructure.grafana_health_url', 'http://127.0.0.1:3000/api/health');
    // Wazuh is reachable but has no management API credentials, so the
    // service must report not_configured rather than inventing a healthy state.
    config()->set('infrastructure.wazuh_api_url', 'https://127.0.0.1:55000');
    config()->set('infrastructure.wazuh_api_username', null);
    config()->set('infrastructure.wazuh_api_password', null);
    Http::fake([
        'http://127.0.0.1:9090/-/healthy' => Http::response("Prometheus Server is Healthy.\n", 200),
        'http://127.0.0.1:3000/api/health' => Http::response(['database' => 'failing'], 503),
        'https://127.0.0.1:55000/*' => Http::response(['status' => 'Success'], 200),
    ]);

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')
        ->once()
        ->with(['incus', 'list', 'lab-env:', '--format=json'])
        ->andReturn(['successful' => true, 'stdout' => '[{"name":"lab-test","status":"Running"}]', 'stderr' => '']);
    $commands->shouldReceive('run')
        ->once()
        ->with(['tofu', 'version'])
        ->andReturn(['successful' => true, 'stdout' => 'OpenTofu v1.12.6', 'stderr' => '']);
    $commands->shouldReceive('run')
        ->once()
        ->with(['ansible', '--version'])
        ->andReturn(['successful' => true, 'stdout' => 'ansible [core 2.16.3]', 'stderr' => '']);

    $service = new InfrastructureStatusService($commands);
    $summary = $service->summary();
    $cached = $service->summary();

    expect($summary['incus']['status'])->toBe('healthy')
        ->and($summary['incus']['summary'])->toContain('1 container')
        ->and($summary['prometheus']['status'])->toBe('healthy')
        ->and($summary['grafana']['status'])->toBe('offline')
        ->and($summary['automation']['status'])->toBe('degraded')
        ->and($summary['wazuh']['status'])->toBe('not_configured')
        ->and($summary['iaas_mgmt']['group'])->toBe('management_plane')
        ->and($summary['lab_env']['group'])->toBe('compute_plane')
        ->and($summary['wazuh']['group'])->toBe('security')
        ->and($cached)->toBe($summary);
});

test('malformed incus output becomes unknown instead of healthy', function () {
    Http::fake([
        '*' => Http::response('', 503),
    ]);

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')
        ->with(['incus', 'list', 'lab-env:', '--format=json'])
        ->andReturn(['successful' => true, 'stdout' => 'not-json', 'stderr' => '']);
    $commands->shouldReceive('run')
        ->twice()
        ->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['incus']['status'])->toBe('unknown')
        ->and($summary['incus']['summary'])->not->toContain('healthy');
});

test('malformed successful provider payloads are not reported healthy', function () {
    config()->set('infrastructure.prometheus_health_url', 'http://127.0.0.1:9090/-/healthy');
    config()->set('infrastructure.grafana_health_url', 'http://127.0.0.1:3000/api/health');
    Http::fake([
        'http://127.0.0.1:9090/-/healthy' => Http::response('unexpected', 200),
        'http://127.0.0.1:3000/api/health' => Http::response(['database' => 'failing'], 200),
        '*' => Http::response('unexpected', 200),
    ]);

    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')
        ->with(['incus', 'list', 'lab-env:', '--format=json'])
        ->andReturn(['successful' => true, 'stdout' => '[null]', 'stderr' => '']);
    $commands->shouldReceive('run')->twice()
        ->andReturn(['successful' => false, 'stdout' => '', 'stderr' => 'unavailable']);

    $summary = (new InfrastructureStatusService($commands))->summary();

    expect($summary['incus']['status'])->toBe('unknown')
        ->and($summary['prometheus']['status'])->toBe('unknown')
        ->and($summary['grafana']['status'])->toBe('unknown');
});
