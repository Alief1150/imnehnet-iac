<?php

use App\Support\EnvironmentWorkspace;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The guard reads its protected lists from configuration, so this needs the
// application booted. It lives under Feature for that reason.
uses(RefreshDatabase::class);

test('the reference workspace is refused for portal use', function () {
    // The default workspace holds the state of the protected reference
    // container. A destroy aimed at it would take that container down.
    expect(fn () => EnvironmentWorkspace::assertUsable('default', 'lab-web-smoke'))
        ->toThrow(DomainException::class, 'reserved for the reference environment');

    expect(EnvironmentWorkspace::isProtected('default', 'lab-web-smoke'))->toBeTrue();
});

test('the reference container is refused for every operation', function () {
    expect(fn () => EnvironmentWorkspace::assertUsable('env-lab-tofu-test', 'lab-tofu-test'))
        ->toThrow(DomainException::class, 'protected reference environment');

    expect(EnvironmentWorkspace::isProtected('env-lab-tofu-test', 'lab-tofu-test'))->toBeTrue();
});

test('a disposable environment is allowed', function () {
    EnvironmentWorkspace::assertUsable('env-lab-web-smoke', 'lab-web-smoke');

    expect(EnvironmentWorkspace::isProtected('env-lab-web-smoke', 'lab-web-smoke'))->toBeFalse();
});

test('workspace names are derived from the container, never from request input', function () {
    expect(EnvironmentWorkspace::forContainer('lab-web-smoke'))->toBe('env-lab-web-smoke')
        // Reversible, so reconciliation can recover the container from a
        // workspace without a lookup table.
        ->and(EnvironmentWorkspace::forContainer('lab-demo-01'))->toBe('env-lab-demo-01');
});

test('a name that could reach a shell is refused before a workspace is derived', function () {
    // Deriving first would put the raw value into a workspace name and then
    // into a command line, so validation happens ahead of derivation.
    foreach ([
        'lab demo',
        'lab;rm -rf /',
        '$(whoami)',
        'lab_web',
        'Lab-Demo',
        'ab',
        '../etc',
    ] as $name) {
        expect(fn () => EnvironmentWorkspace::forContainer($name))->toThrow(DomainException::class);
    }
});

test('the protected lists are configuration rather than code constants', function () {
    config(['infrastructure.protected_workspaces' => ['default', 'staging']]);
    config(['infrastructure.protected_containers' => ['lab-tofu-test', 'lab-baseline']]);

    expect(EnvironmentWorkspace::isProtected('staging', 'lab-web-smoke'))->toBeTrue()
        ->and(EnvironmentWorkspace::isProtected('env-lab-baseline', 'lab-baseline'))->toBeTrue()
        ->and(EnvironmentWorkspace::isProtected('env-lab-web-smoke', 'lab-web-smoke'))->toBeFalse();
});
