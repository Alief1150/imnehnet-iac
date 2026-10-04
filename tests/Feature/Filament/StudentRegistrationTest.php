<?php

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\RegisterStudent;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Student self-registration at /login/regist.
 *
 * Registration is public on purpose, so what these hold down is not "the form
 * works" but the three things that make a public form safe: the role cannot be
 * chosen by whoever submits it, the endpoint cannot be used to enumerate or to
 * mass-create accounts, and every arrival is written down.
 */

function aStrongPassword(): string
{
    // Letters and digits, well over the twelve-character floor.
    return 'Koral-Mekanisme-9917';
}

/**
 * Fill the four fields. No parameter type on the component handle: Livewire's
 * testing class is an implementation detail that has moved between majors, and
 * naming it here would make this file break on an upgrade rather than on a bug.
 */
function fillRegistration($page, string $email)
{
    return $page
        ->set('name', 'Budi Santoso')
        ->set('email', $email)
        ->set('password', aStrongPassword())
        ->set('password_confirmation', aStrongPassword());
}

test('the registration page is reachable on its own address', function () {
    // Not under /admin. A student creating an account is not doing an
    // administrative task and should not appear to reach for one.
    $this->get('/login/regist')
        ->assertSuccessful()
        ->assertSee('Register a student account')
        ->assertSee('Create account');
});

test('the sign-in page offers registration', function () {
    $this->get('/admin/login')->assertSuccessful()->assertSee('Register as a student');
});

test('registering creates an active student and signs them in', function () {
    fillRegistration(Livewire::test(RegisterStudent::class), 'budi@example.com')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'budi@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Budi Santoso')
        ->and($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::Active)
        // The student chose their own password, so there is nothing to make them
        // change at first sign-in.
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check(aStrongPassword(), $user->password))->toBeTrue();

    // Signed in and sent to the panel, so a student does not land on the
    // registration form they just submitted with a success message on it.
    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->getKey())->toBe($user->getKey());
});

test('a self-registered account cannot be an administrator', function () {
    fillRegistration(Livewire::test(RegisterStudent::class), 'penyusup@example.com')
        ->call('register')
        ->assertHasNoErrors();

    // 'role' is fillable, because an administrator has to be able to create an
    // administrator from the Users page. The component assigns properties one
    // at a time instead of filling from the request, so there is nothing here
    // for a submitted value to land in. This asserts that structurally, because
    // a behavioural test would have to invent a payload the form cannot send.
    $properties = array_map(
        fn (ReflectionProperty $property): string => $property->getName(),
        (new ReflectionClass(RegisterStudent::class))->getProperties(ReflectionProperty::IS_PUBLIC),
    );

    expect($properties)->not->toContain('role')
        ->and($properties)->not->toContain('status')
        ->and(User::query()->where('email', 'penyusup@example.com')->first()->role)
        ->toBe(UserRole::Student);
});

test('an address that already has an account is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    fillRegistration(Livewire::test(RegisterStudent::class), 'ada@example.com')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    // No second account, and no partially created one.
    expect(User::query()->where('email', 'ada@example.com')->count())->toBe(1);
});

test('a short password is refused', function () {
    // This account reaches a machine holding other people's labs, so a long
    // password is the floor rather than a suggestion.
    Livewire::test(RegisterStudent::class)
        ->set('name', 'Budi')
        ->set('email', 'pendek@example.com')
        ->set('password', 'pendek')
        ->set('password_confirmation', 'pendek')
        ->call('register')
        ->assertHasErrors('password');

    expect(User::query()->where('email', 'pendek@example.com')->exists())->toBeFalse();
});

test('a mismatched confirmation is refused', function () {
    Livewire::test(RegisterStudent::class)
        ->set('name', 'Budi')
        ->set('email', 'beda@example.com')
        ->set('password', aStrongPassword())
        ->set('password_confirmation', 'lain-sekali-9917')
        ->call('register')
        ->assertHasErrors('password');

    expect(User::query()->where('email', 'beda@example.com')->exists())->toBeFalse();
});

test('registration is written to the audit trail as REGISTERED', function () {
    // Distinct from CREATE_USER, which is an administrator making an account.
    // An operator reading the trail should not have to open the record to tell
    // a student who arrived of their own accord from one somebody created.
    fillRegistration(Livewire::test(RegisterStudent::class), 'jejak@example.com')
        ->call('register')
        ->assertHasNoErrors();

    $log = ActivityLog::query()
        ->where('action', ActivityAction::Registered->value)
        ->latest('id')
        ->first();

    // The role is deliberately not asserted from the metadata: the log service
    // keeps an allowlist of metadata keys, and 'role' is not one of them. The
    // facts that matter are the action, what it was recorded against, and the
    // role on the account itself.
    expect($log)->not->toBeNull()
        ->and($log->resource_type)->toBe(User::class)
        ->and($log->description)->toContain('Self-registered')
        ->and($log->resource_id)->toBe((string) User::query()->where('email', 'jejak@example.com')->value('id'))
        ->and(User::query()->where('email', 'jejak@example.com')->value('role'))->toBe(UserRole::Student);
});

test('repeat attempts from one browser are refused, valid or not', function () {
    // Attempts that fail validation still count. If only successful attempts were
    // counted, a script could submit addresses that fail the uniqueness rule for
    // ever and never reach the limit, which is exactly the enumeration this
    // endpoint would otherwise allow.
    User::factory()->create(['email' => 'sudah@example.jp']);

    RateLimiter::clear('register:sudah@example.jp|127.0.0.1');

    $page = fn () => Livewire::test(RegisterStudent::class)
        ->set('name', 'Percobaan')
        ->set('email', 'sudah@example.jp')
        ->set('password', aStrongPassword())
        ->set('password_confirmation', aStrongPassword());

    // Five are allowed, so a mistyped address does not lock anyone out of their
    // own account creation.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $page()->call('register')->assertHasErrors('email');
    }

    $page()->call('register')->assertHasErrors('email');

    // Five is the ceiling, not six: the refusal is raised before the counter is
    // incremented, so the sixth attempt never counted itself. And the account
    // that existed all along is still the only one.
    expect(RateLimiter::attempts('register:sudah@example.jp|127.0.0.1'))->toBe(5)
        ->and(User::query()->where('email', 'sudah@example.jp')->count())->toBe(1);
});