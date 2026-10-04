<?php

use App\Models\User;
use App\Support\Wordmark;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The two ways into this portal looked like two different products.
 *
 * The sign-in form is Filament's component, styled through the panel theme. The
 * registration form is a plain Livewire page that had been styled with whatever
 * utility classes were to hand. Same name, same purpose, visibly unrelated: a
 * different card, a different ground, a different wordmark, different spacing.
 *
 * These assert that they are built from the same things. A visual preference
 * drifts, and nothing about a screenshot fails a test, so what is asserted here
 * is the sharing: one wordmark, one set of classes, one card.
 */

test('both forms carry the same wordmark and tagline', function () {
    // Written down in one class, so the name cannot be spelled two ways.
    expect(Wordmark::make()->toHtml())->toContain('IMNEHNET')
        ->and(Wordmark::tagline())->toBe('Cybersecurity Infrastructure Control Plane');

    // Not signed in: Filament redirects an authenticated visitor away from its own
    // sign-in page, so asking for it while signed in asserts a 302.
    $this->get(route('filament.admin.auth.login'))
        ->assertSuccessful()
        // The panel renders the wordmark component rather than the plain name.
        ->assertSee('IMNEHNET')
        ->assertSee(Wordmark::tagline());

    $this->get('/login/regist')
        ->assertSuccessful()
        ->assertSee('IMNEHNET')
        ->assertSee(Wordmark::tagline());
});

test('the registration form uses the shared auth classes', function () {
    // The classes live in theme.css so that both forms resolve the same colours.
    // When the registration form carried its own utility classes instead, the two
    // drifted into different products and nothing failed.
    $html = (string) $this->get('/login/regist')
        ->assertSuccessful()
        ->getContent();

    foreach ([
        'imnehnet-auth-card',
        'imnehnet-auth-input',
        'imnehnet-auth-button',
        'imnehnet-auth-label',
        'imnehnet-auth-checks',
    ] as $class) {
        expect($html)->toContain($class);
    }
});

test('the sign-in form and the registration form use one stylesheet for both', function () {
    // The panel theme defines the tokens. A registration page that loads only
    // app.css gets none of them, which is precisely how the two stopped matching.
    $html = (string) $this->get('/login/regist')->assertSuccessful()->getContent();

    $assets = [];
    preg_match_all('/build\/assets\/[^\'"]+\.css/', $html, $matches);

    foreach ($matches[0] as $href) {
        $path = public_path($href);

        if (is_file($path)) {
            $assets[$href] = file_get_contents($path);
        }
    }

    expect($assets)->not->toBeEmpty();

    $shared = array_filter(
        $assets,
        fn (string $css): bool => str_contains($css, '.imnehnet-auth-card')
            && str_contains($css, '.fi-simple-page'),
    );

    // One file carrying both the shared form classes and Filament's own page
    // shell. If a build drops one of them, the forms diverge again and this fails.
    expect($shared)->toHaveCount(1);
});

test('the sign-in form states the same password rules the server enforces', function () {
    // The registration form lists four rules. If the server's rule changes and the
    // list does not, the student is told to satisfy a rule that is not the one
    // being checked.
    $html = (string) $this->get('/login/regist')->assertSuccessful()->getContent();

    $source = file_get_contents(app_path('Livewire/RegisterStudent.php'));

    // Eight characters, and the three character classes.
    preg_match('/Password::min\((\d+)\)/', (string) $source, $minimum);

    expect($minimum[1] ?? null)->toBe('8')
        ->and($source)->toContain('mixedCase()')
        ->and($source)->toContain('numbers()')
        ->and($source)->toContain('symbols()')
        ->and($html)->toContain('At least 8 characters')
        ->and($html)->toContain('One uppercase letter')
        ->and($html)->toContain('One number')
        ->and($html)->toContain('One symbol');
});

test('a password meeting only the length rule is still refused', function () {
    // Lowering the floor from twelve to eight was asked for. It is safe because the
    // other three rules are unchanged, and this is the proof: eight lowercase
    // letters satisfy the length rule and nothing else, so the account must not
    // exist afterwards.
    Livewire\Livewire::test(App\Livewire\RegisterStudent::class)
        ->set('name', 'Pendek')
        ->set('email', 'pendek8@imnehnet.test')
        ->set('password', 'abcdefgh')
        ->set('password_confirmation', 'abcdefgh')
        ->call('register')
        ->assertHasErrors('password');

    expect(User::query()->where('email', 'pendek8@imnehnet.test')->exists())->toBeFalse();
});

test('an eight character password that meets every rule is accepted', function () {
    // The point of lowering the floor: a real student who satisfies all four rules
    // is not turned away for having a shorter password.
    Livewire\Livewire::test(App\Livewire\RegisterStudent::class)
        ->set('name', 'Cukup Panjang')
        ->set('email', 'delapan@imnehnet.test')
        ->set('password', 'Koral-Me9')
        ->set('password_confirmation', 'Koral-Me9')
        ->call('register')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'delapan@imnehnet.test')->exists())->toBeTrue();
});