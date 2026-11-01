<?php

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Infrastructure;
use App\Filament\Pages\Monitoring;
use App\Filament\Pages\Security;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

test('every control plane page renders for an administrator without server errors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    foreach ([Dashboard::class, Monitoring::class, Security::class, Infrastructure::class] as $page) {
        $response = $this->actingAs($admin)->get($page::getUrl(panel: 'admin'));

        expect($response->status(), "{$page} must not return a 5xx")->toBe(200);
    }
});

test('resource pages render for an administrator', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    foreach ([UserResource::class, EnvironmentResource::class, ActivityLogResource::class] as $resource) {
        $response = $this->actingAs($admin)->get($resource::getUrl(panel: 'admin'));

        expect($response->status(), "{$resource} must not return a 5xx")->toBe(200);
    }
});

test('monitoring page reports the figure returned by the metrics backend', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    config()->set('infrastructure.prometheus_query_url', 'http://127.0.0.1:9090/api/v1/query');
    config()->set('infrastructure.prometheus_range_url', 'http://127.0.0.1:9090/api/v1/query_range');

    Http::fake([
        'http://127.0.0.1:9090/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['value' => [1, '41.5']]]],
        ], 200),
        'http://127.0.0.1:9090/api/v1/query_range*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'matrix', 'result' => [['values' => [[1, '40'], [2, '41.5'], [3, '43']]]]],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(Monitoring::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('41.5%');
});

test('monitoring page refuses to invent a figure when the metrics backend fails', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    config()->set('infrastructure.prometheus_query_url', 'http://127.0.0.1:9090/api/v1/query');
    config()->set('infrastructure.prometheus_range_url', 'http://127.0.0.1:9090/api/v1/query_range');

    Http::fake(['*' => Http::response('', 503)]);

    $response = $this->actingAs($admin)->get(Monitoring::getUrl(panel: 'admin'));

    $response->assertOk()->assertSee('n/a');
});

test('security page reports an unconnected siem as unconnected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    config()->set('infrastructure.wazuh_api_url', '');
    Http::fake(['*' => Http::response('', 503)]);

    // The page no longer renders the old summary cards; the same honesty lives
    // on in the new layout: empty telemetry renders as collecting with empty
    // states, and unreachable backends render as Unreachable.
    $this->actingAs($admin)
        ->get(Security::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('No alerts recorded yet', false)
        ->assertSee('Unreachable');
});
