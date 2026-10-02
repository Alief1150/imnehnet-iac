<?php

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Infrastructure;
use App\Filament\Pages\Monitoring;
use App\Filament\Pages\Security;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Rendering is asserted per widget rather than once for the whole dashboard.
 *
 * A dashboard that fails reports one opaque error and names nothing. Testing
 * each widget on its own means a failure says which one broke and why, which
 * is the difference between a five minute fix and an afternoon.
 *
 * This exists because a dashboard widget referenced a method the status enums
 * do not have, and the suite was green the whole time: nothing rendered the
 * widget for a signed-in administrator.
 */
test('every dashboard widget renders on its own', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $widgets = (new Dashboard)->getWidgets();

    expect($widgets)->not->toBeEmpty();

    foreach ($widgets as $widget) {
        Livewire::actingAs($admin)
            ->test($widget)
            ->assertOk();
    }
});

test('every panel page and resource index answers without a server error', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $urls = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $urls[$resource::getUrl(panel: 'admin')] = $resource;
    }

    foreach ([
        Dashboard::class,
        Infrastructure::class,
        Monitoring::class,
        Security::class,
    ] as $page) {
        $urls[$page::getUrl(panel: 'admin')] = $page;
    }

    $failures = [];

    foreach ($urls as $url => $label) {
        $status = $this->actingAs($admin)->get($url)->status();

        if ($status >= 500) {
            $failures[] = sprintf('%s (%s) -> %d', class_basename($label), $url, $status);
        }
    }

    expect($failures)->toBe([]);
});
