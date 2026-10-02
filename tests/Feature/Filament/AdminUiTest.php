<?php

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the activity log table renders its header cells', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'PROVISION_LAB_RESULT',
        'status' => 'failed',
        'description' => 'The container was created and verified.',
        'resource_type' => User::class,
        'resource_id' => '1',
    ]);

    $response = $this->actingAs($admin)->get(ActivityLogResource::getUrl('index', panel: 'admin'));

    $response->assertOk()
        // A column hidden by default renders no header, which is exactly how
        // the list looked empty while still reporting its row count.
        ->assertSee('When', escape: false)
        ->assertSee('Action', escape: false)
        ->assertSee('Actor', escape: false)
        ->assertSee('Result', escape: false)
        ->assertSee('PROVISION_LAB_RESULT')
        ->assertSee('The container was created and verified.')
        ->assertSee('failed');
});

test('the activity log table is not rendered with every column hidden', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'PROVISION_LAB',
        'status' => 'queued',
        'description' => 'Provisioning requested.',
    ]);

    $html = $this->actingAs($admin)->get(ActivityLogResource::getUrl('index', panel: 'admin'))->getContent();

    $headers = substr_count($html, 'fi-ta-header-cell');

    expect($headers)->toBeGreaterThan(2);
});

test('an activity log detail page shows who, what and the request context', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $log = ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'DELETE_LAB_RESULT',
        'status' => 'completed',
        'description' => 'The container was removed from the compute plane.',
        'ip_address' => '10.20.0.5',
        'metadata' => ['operation_id' => 'abc-123'],
    ]);

    $this->actingAs($admin)
        ->get(ActivityLogResource::getUrl('view', ['record' => $log], 'admin'))
        ->assertOk()
        ->assertSee('DELETE_LAB_RESULT')
        ->assertSee('The container was removed from the compute plane.')
        ->assertSee('10.20.0.5')
        ->assertSee('abc-123');
});
test('the activity log infolist never renders an updated timestamp', function () {
    // ActivityLog sets UPDATED_AT to null, so a column for it would always be
    // empty. Guard against it being reintroduced.
    $source = file_get_contents(app_path('Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php'));

    expect($source)->not->toContain("TextEntry::make('updated_at')");
});
