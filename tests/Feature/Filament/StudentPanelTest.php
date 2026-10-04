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
/**
 * The two failures that a behavioural test cannot see.
 *
 * Both of these were found by opening a browser, not by running the suite, and
 * both took down every page of a panel at once. They are asserted structurally
 * here because actingAs() puts the user straight on the guard and so can never
 * reproduce them: a test that signs a user in does not go through the session
 * start that the real request depends on.
 */
test('the redirect middleware runs after the session has been started', function () {
    $route = Route::getRoutes()->getByName('filament.admin.pages.dashboard');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    $session = array_search(Illuminate\Session\Middleware\StartSession::class, $middleware, true);
    $redirect = array_search(\App\Http\Middleware\RedirectToOwnPanel::class, $middleware, true);

    // Global middleware runs before StartSession, so the signed-in user is not
    // readable yet, the redirect concludes there is nobody to redirect, and
    // Filament's own 403 goes out a moment later. This was the first version and
    // it looked correct in every test.
    expect($session)->toBeInt()
        ->and($redirect)->toBeInt()
        ->and($redirect)->toBeGreaterThan($session);

    // Registering it globally as well as here would put a copy in front of
    // StartSession, where it reads a signed-in user that does not exist yet and
    // does nothing, while the panel copy still works and hides the fact. The
    // global stack is not part of the route's middleware, so the assertion above
    // cannot see it and it has to be checked here.
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $global = (new ReflectionMethod($kernel, 'getGlobalMiddleware'))->invoke($kernel);

    foreach ((array) $global as $name) {
        expect($name)->not->toBe(\App\Http\Middleware\RedirectToOwnPanel::class);
    }
});

test('the redirect middleware replaces, and does not sit beside, the one that refuses', function () {
    $auth = Filament\Facades\Filament::getPanel('admin')->getAuthMiddleware();

    // Panel middleware runs after Filament injects its own Authenticate, so a
    // redirect added there always runs after the refusal it exists to prevent.
    // That was the second version. Extending Authenticate is what puts the check
    // in the only position where the user is both known and not yet refused.
    expect($auth)->toContain(\App\Http\Middleware\RedirectToOwnPanel::class)
        ->and($auth)->not->toContain(Filament\Http\Middleware\Authenticate::class);
});

test('the redirect middleware keeps the parent handle signature', function () {
    // Reproducing this exactly is not optional. An incompatible declaration is a
    // fatal error rather than a warning, and it takes every page of both panels
    // down with it.
    $parent = new ReflectionMethod(Illuminate\Auth\Middleware\Authenticate::class, 'handle');
    $child = new ReflectionMethod(\App\Http\Middleware\RedirectToOwnPanel::class, 'handle');

    expect($child->getNumberOfParameters())->toBe($parent->getNumberOfParameters())
        ->and($child->getParameters()[2]->isVariadic())->toBeTrue();
});
