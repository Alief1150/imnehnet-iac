<?php

use App\Enums\UserRole;
use App\Filament\Pages\ComputePlane;
use App\Filament\Pages\ManagementPlane;
use App\Filament\Pages\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The sidebar the specification asks for is only useful if the entries lead
 * somewhere and say something true, so these cover both.
 */
test('an administrator can reach both plane pages and settings', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    foreach ([ManagementPlane::class, ComputePlane::class, Settings::class] as $page) {
        $this->get($page::getUrl(panel: 'admin'))->assertSuccessful();
    }
});

test('a student cannot reach the plane pages or settings', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student);

    foreach ([ManagementPlane::class, ComputePlane::class, Settings::class] as $page) {
        $this->get($page::getUrl(panel: 'admin'))->assertForbidden();
    }
});

test('the management plane reports this host and not the compute plane', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $page = Livewire::actingAs($admin)->test(ManagementPlane::class)->instance();

    $groups = array_keys($page->healthSections());

    // The distinction is the reason these are separate pages. If the compute
    // plane appeared here too, the page would not answer "which side is down".
    expect($groups)->toContain('management_plane');
    expect($groups)->not->toContain('compute_plane');
});

test('the compute plane reports the hypervisor and not this host', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $page = Livewire::actingAs($admin)->test(ComputePlane::class)->instance();

    $groups = array_keys($page->healthSections());

    expect($groups)->toContain('compute_plane');
    expect($groups)->not->toContain('management_plane');
});

test('a working probe is not reported as a failed one', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $page = Livewire::actingAs($admin)->test(ManagementPlane::class)->instance();

    // The flag exists for the case where the probe genuinely fails. Conflating the
    // two would show an operator a healthy empty grid when the truth is that
    // nothing was read at all.
    expect($page->healthProbeFailed())->toBeFalse();

    $this->actingAs($admin);

    $this->get(ManagementPlane::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertDontSee('NOT CONFIGURED');
});

test('settings reports the real configuration and offers no switches', function () {
    config()->set('infrastructure.protected_containers', ['lab-tofu-test']);
    config()->set('infrastructure.protected_workspaces', ['default']);
    config()->set('infrastructure.terminal.browser_access_enabled', false);
    config()->set('infrastructure.terminal.shell_user', 'student');
    config()->set('infrastructure.terminal.session_ttl_minutes', 15);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $sections = Livewire::actingAs($admin)->test(Settings::class)->instance()->sections();
    $byLabel = collect($sections)->keyBy('label');

    // Read from configuration, so it cannot drift from what the portal does.
    expect($byLabel['Browser access']['value'])->toBe('DISABLED');
    expect($byLabel['Shell user']['value'])->toBe('student');
    expect($byLabel['Session lifetime']['value'])->toBe('15 minutes');
    expect($byLabel['Protected containers']['value'])->toBe('lab-tofu-test');
    expect($byLabel['Protected workspaces']['value'])->toBe('default');
    expect($byLabel['Incus remote']['value'])->toBe('lab-env:');

    // Asserted on the data rather than on the markup. Filament renders a search
    // input on every page, so "the page contains an input" proves nothing about
    // this page. What matters is that what it exposes is data and nothing that
    // could be acted on.
    foreach ($sections as $entry) {
        expect(array_keys($entry))->toBe(['group', 'label', 'value', 'note']);
    }

    $this->actingAs($admin);

    $this->get(Settings::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Read only');
});
