<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Two panels, one per role.
 *
 * This exists because a student who registered was landed in /admin, which said
 * two things at once: that student accounts are administrators, and that the
 * administrative navigation is theirs. canAccessPanel had only ever asked whether
 * the account was active, so nothing stopped either.
 *
 * The important property is not that a student is refused /admin. It is that the
 * refusal is a redirect to the panel that is theirs rather than a 403, and that
 * the student's panel does not contain the administrative pages at all.
 */

test('a student who opens the administrative address is sent to their own panel', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    // Redirected rather than refused. A 403 answers a typing mistake with an
    // accusation, and somebody who followed a stale bookmark deserves to be
    // brought to where they were trying to go.
    $this->actingAs($student)
        ->get('/admin')
        ->assertRedirect(route('filament.student.pages.dashboard'));
});

test('an administrator who lands in the student panel is sent to their own', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Symmetric. The mistake goes both ways and neither is a security problem,
    // because the page that answers it is not in the panel it was asked for.
    $this->actingAs($admin)
        ->get('/student')
        ->assertRedirect(route('filament.admin.pages.dashboard'));
});

test("a student's own panel is where they work", function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student)
        ->get(route('filament.student.pages.dashboard'))
        ->assertSuccessful();
});

test('the student panel carries no administrative pages at all', function () {
    // Not guarded, absent. A page that exists and refuses is one refactor away
    // from existing and allowing, whereas a route that was never registered
    // cannot be reached at any permission setting.
    expect(Route::getRoutes()->hasNamedRoute('filament.student.resources.environments.index'))->toBeTrue()
        ->and(Route::getRoutes()->hasNamedRoute('filament.student.pages.terminal'))->toBeTrue();

    foreach ([
        'filament.student.resources.users.index',
        'filament.student.resources.activity-logs.index',
        'filament.student.pages.infrastructure',
        'filament.student.pages.monitoring',
        'filament.student.pages.security',
    ] as $route) {
        expect(Route::getRoutes()->hasNamedRoute($route))->toBeFalse();
    }
});

test('a disabled account is not redirected between panels', function () {
    $disabled = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);

    // A disabled account belongs to neither panel. Redirecting it to "its own"
    // would bounce it here and there for ever, because the redirect is only taken
    // when the destination is reachable. Filament refuses it instead, once, with
    // the same answer whatever path it was asked for.
    $this->actingAs($disabled)
        ->get('/student')
        ->assertForbidden();

    $this->actingAs($disabled)
        ->get('/admin')
        ->assertForbidden();
});

test('an anonymous visitor is sent to the sign-in page of the panel they asked for', function () {
    $this->get('/student')->assertRedirect(route('filament.student.auth.login'));
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

test('a correct password is never reported as wrong because the panel differs', function () {
    // The failure this guards against: Filament's login refuses an account that
    // may not enter the current panel and reports it as "these credentials do not
    // match our records". For a student who typed a correct password into
    // /admin/login that is untrue, and it sends them off to reset a password that
    // does not need resetting.
    $student = User::factory()->create(['role' => UserRole::Student]);

    $component = Livewire::test(\App\Filament\Auth\Pages\Login::class)
        ->fillForm(['email' => $student->email, 'password' => 'password'])
        ->call('authenticate');

    $component->assertHasNoFormErrors();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->getKey())->toBe($student->getKey());

    auth()->logout();
});