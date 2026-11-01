<?php

use App\Enums\ActivityAction;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('activity service records actor resource request context and sanitized metadata', function () {
    $actor = User::factory()->create();
    $environment = LabEnvironment::factory()->create();
    $request = Request::create('/', 'POST', server: [
        'REMOTE_ADDR' => '100.64.0.10',
        'HTTP_USER_AGENT' => 'IMNEHNET-Test-Agent',
    ]);

    $log = app(ActivityLogService::class)->record(
        actor: $actor,
        action: ActivityAction::CreateRequested,
        resource: $environment,
        status: 'requested',
        description: 'Create requested',
        metadata: [
            'container_name' => $environment->container_name,
            'password' => 'must-not-be-stored',
            'note' => 'generic-key-must-not-be-stored',
            'nested' => ['api_token' => 'must-not-be-stored', 'phase' => 'queued'],
        ],
        request: $request,
    );

    expect($log->actor->is($actor))->toBeTrue()
        // An enum rather than free text, so a typo in an action cannot reach the
        // audit trail as a string nobody filters by.
        ->and($log->action)->toBe('CREATE_REQUESTED')
        ->and($log->resource_type)->toBe(LabEnvironment::class)
        ->and($log->resource_id)->toBe((string) $environment->getKey())
        ->and($log->metadata)->toBe([
            'container_name' => $environment->container_name,
            'nested' => ['phase' => 'queued'],
        ])
        ->and($log->ip_address)->toBe('100.64.0.10')
        ->and($log->user_agent)->toBe('IMNEHNET-Test-Agent');
});

test('activity log model rejects updates and deletes', function () {
    $log = app(ActivityLogService::class)->record(
        actor: User::factory()->create(),
        action: ActivityAction::Login,
        resource: null,
        status: 'success',
        description: 'User logged in',
    );

    expect(fn () => $log->update(['description' => 'tampered']))
        ->toThrow(LogicException::class, 'Activity logs are immutable');

    expect(fn () => $log->delete())
        ->toThrow(LogicException::class, 'Activity logs are immutable');

    expect(DB::table('activity_logs')->count())->toBe(1);
});
