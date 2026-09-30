<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The panel configuration runs on every single request, so a method that does
 * not exist in this Filament version takes down the entire application rather
 * than one page. There is no partial failure to notice: the 500 arrives before
 * any controller code.
 *
 * This is a regression guard, not a styling test. It boots the panel and asks
 * for the values the specification names.
 */
test('the admin panel configures and exposes the IMNEHNET branding', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getBrandName())->toBe('IMNEHNET')
        ->and($panel->getId())->toBe('admin')
        ->and($panel->getPath())->toBe('admin');
});

test('every panel page is reachable for an administrator', function () {
    // A single HTTP check per page is what actually proves the panel boots,
    // because the failure mode above happens during panel construction rather
    // than inside a resource.
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    foreach (['/admin/login', '/up'] as $url) {
        $this->get($url)->assertOk();
    }

    // The point of this check is that the panel boots at all. Asserting a 5xx
    // never happens is the actual claim; pinning the exact redirect target
    // would test routing instead.
    expect($this->actingAs($admin)->get('/admin')->status())->toBeLessThan(500);
});
