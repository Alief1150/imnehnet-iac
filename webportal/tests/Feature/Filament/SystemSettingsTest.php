<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\SystemSettings;
use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('system settings page renders for an active user with two tabs', function () {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Active,
        'name' => 'Ahmad Malik',
        'email' => 'malik@imnehnet.test',
    ]);

    $this->actingAs($user)
        ->get(SystemSettings::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('System Settings')
        ->assertSee('Overview')
        ->assertSee('Account &amp; Security', false)
        ->assertSee('IMNEHNET — Lab as a Service')
        ->assertSee('Infrastructure Architecture &amp; Topology', false)
        ->assertSee('Verified Technologies &amp; Versions', false);
});

test('inactive user cannot access system settings', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Disabled,
    ]);

    $this->actingAs($user)
        ->get(SystemSettings::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('switching tabs between overview and account-security works', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->assertSet('tab', 'overview')
        ->call('selectTab', 'account-security')
        ->assertSet('tab', 'account-security')
        ->call('selectTab', 'invalid-tab')
        ->assertSet('tab', 'overview')
        ->call('selectTab', 'ringkasan')
        ->assertSet('tab', 'overview')
        ->call('selectTab', 'akun-keamanan')
        ->assertSet('tab', 'account-security');
});

test('technology groups and verified versions are returned from runtime and composer', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $component = Livewire::actingAs($user)->test(SystemSettings::class);
    $groups = $component->instance()->getTechnologyGroups();

    expect($groups)->toHaveKeys([
        'Portal',
        'Automation / Data',
        'Virtualisasi / Lab',
        'Monitoring',
        'Security / Connectivity',
    ]);

    // Check dynamic portal items
    $portalItems = collect($groups['Portal']['items'])->keyBy('name');

    expect($portalItems->has('Laravel'))->toBeTrue();
    expect($portalItems->get('Laravel')['version'])->toBe(app()->version());

    expect($portalItems->has('Filament'))->toBeTrue();
    expect($portalItems->get('Filament')['version'])->toBe(InstalledVersions::getPrettyVersion('filament/filament'));

    expect($portalItems->has('PHP'))->toBeTrue();
    expect($portalItems->get('PHP')['version'])->toBe(PHP_VERSION);
});

test('probe failure generates unknown or not_connected status rather than throwing exceptions', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $component = Livewire::actingAs($user)->test(SystemSettings::class);
    $groups = $component->instance()->getTechnologyGroups();

    $virtualizationItems = collect($groups['Virtualisasi / Lab']['items'])->keyBy('name');
    expect($virtualizationItems->has('LAB-ATK (VM)'))->toBeTrue();
    expect($virtualizationItems->get('LAB-ATK (VM)')['status'])->toBe('unknown');

    $securityItems = collect($groups['Security / Connectivity']['items'])->keyBy('name');
    expect($securityItems->has('Cloudflare WARP CLI'))->toBeTrue();
    expect($securityItems->get('Cloudflare WARP CLI')['status'])->toBe('not_connected');

    expect($securityItems->has('Wazuh'))->toBeTrue();
    expect($securityItems->get('Wazuh')['version'])->toBe('Belum diverifikasi');
});

test('user model implements HasAvatar and resolves uploaded avatar url', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'avatar_url' => null,
    ]);

    expect($user->getFilamentAvatarUrl())->toBeNull();

    // Store a fake avatar in storage
    Storage::disk('public')->put('avatars/avatar_test.png', 'fake image content');
    $user->update(['avatar_url' => 'avatars/avatar_test.png']);

    expect($user->getFilamentAvatarUrl())->toBe(asset('storage/avatars/avatar_test.png'));
});

test('profile update validates required fields and unique email', function () {
    $other = User::factory()->create([
        'email' => 'taken@imnehnet.test',
    ]);

    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'email' => 'self@imnehnet.test',
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('name', '')
        ->set('email', '')
        ->call('updateProfile')
        ->assertHasErrors(['name', 'email'])
        ->set('name', 'Valid Name')
        ->set('email', 'not-an-email')
        ->call('updateProfile')
        ->assertHasErrors(['email'])
        ->set('email', 'taken@imnehnet.test')
        ->call('updateProfile')
        ->assertHasErrors(['email']);
});

test('user can update profile name, email, and upload avatar photo', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'name' => 'Old Name',
        'email' => 'old@imnehnet.test',
    ]);

    $fakeImage = UploadedFile::fake()->image('profile.png', 200, 200);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('name', 'New Updated Name')
        ->set('email', 'new@imnehnet.test')
        ->set('photo', $fakeImage)
        ->call('updateProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('New Updated Name');
    expect($user->email)->toBe('new@imnehnet.test');
    expect($user->avatar_url)->not->toBeNull();
    expect($user->getFilamentAvatarUrl())->not->toBeNull();
});

test('user can cancel profile changes and revert state', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'name' => 'Original Name',
        'email' => 'original@imnehnet.test',
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('name', 'Modified Temporary Name')
        ->set('email', 'modified@imnehnet.test')
        ->call('cancelProfileChanges')
        ->assertSet('name', 'Original Name')
        ->assertSet('email', 'original@imnehnet.test');
});

test('password change validates current password and confirmation', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('CurrentSecret123!'),
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('current_password', 'WrongPassword!')
        ->set('new_password', 'NewSecret123!')
        ->set('new_password_confirmation', 'NewSecret123!')
        ->call('changePassword')
        ->assertHasErrors(['current_password']);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('current_password', 'CurrentSecret123!')
        ->set('new_password', 'Short')
        ->set('new_password_confirmation', 'Short')
        ->call('changePassword')
        ->assertHasErrors(['new_password']);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('current_password', 'CurrentSecret123!')
        ->set('new_password', 'NewSecret123!')
        ->set('new_password_confirmation', 'MismatchSecret123!')
        ->call('changePassword')
        ->assertHasErrors(['new_password']);
});

test('user can successfully change password', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('OldSecretPassword123!'),
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->set('current_password', 'OldSecretPassword123!')
        ->set('new_password', 'BrandNewSecretPassword123!')
        ->set('new_password_confirmation', 'BrandNewSecretPassword123!')
        ->call('changePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('new_password', '')
        ->assertSet('new_password_confirmation', '');

    $user->refresh();

    expect(Hash::check('BrandNewSecretPassword123!', $user->password))->toBeTrue();
});

test('logout action signs out user and redirects', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    Livewire::actingAs($user)
        ->test(SystemSettings::class)
        ->call('logout')
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(Auth::check())->toBeFalse();
});
