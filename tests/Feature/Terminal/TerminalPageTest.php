<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Terminal;
use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The terminal page exists to report the truth about terminals, so these tests
 * are about what it refuses to claim rather than about what it draws.
 *
 * The recurring failure this guards against is a page that renders a terminal
 * frame because a session row exists, while nothing is listening behind it. That
 * looks like a broken terminal to the reader and like a working one to anyone
 * glancing at a screenshot.
 */
function runningEnv(array $attributes = []): LabEnvironment
{
    return LabEnvironment::factory()->create(array_merge([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'terminal_enabled' => true,
    ], $attributes));
}

function anAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/**
 * Ask the page what it thinks of an environment, as the signed-in user.
 *
 * @return array{code: string, label: string, tone: string, detail: string}
 */
function statusOf(LabEnvironment $environment, ?User $as = null): array
{
    return Livewire::actingAs($as ?? anAdmin())
        ->test(Terminal::class)
        ->instance()
        ->statusFor($environment);
}

test('an administrator can reach the terminal page', function () {
    $this->actingAs(anAdmin());

    $this->get(Terminal::getUrl(panel: 'admin'))->assertSuccessful();
});

test('a signed out visitor cannot reach the terminal page', function () {
    $this->get(Terminal::getUrl(panel: 'admin'))->assertRedirect();
});

test('a stopped container reports not running rather than offering a shell', function () {
    $status = statusOf(runningEnv(['runtime_status' => RuntimeStatus::Stopped]));

    expect($status['code'])->toBe('not_running')
        ->and($status['label'])->toBe('NOT RUNNING');
});

test('an unknown runtime is reported as not running rather than assumed running', function () {
    // Unknown is not running. Treating it as running would offer a shell that
    // cannot open; treating it as stopped would hide a container that is fine.
    expect(statusOf(runningEnv(['runtime_status' => RuntimeStatus::Unknown]))['code'])
        ->toBe('not_running');
});

test('an environment without the terminal says so instead of offering one', function () {
    $status = statusOf(runningEnv(['terminal_enabled' => false]));

    expect($status['code'])->toBe('terminal_disabled')
        ->and($status['label'])->toBe('NOT CONFIGURED');
});

test('no session is reported as not connected', function () {
    $status = statusOf(runningEnv());

    expect($status['code'])->toBe('no_session')
        ->and($status['label'])->toBe('NOT CONNECTED');
});

test('a session whose process is gone is not reported as connected', function () {
    $environment = runningEnv();

    TerminalSession::query()->create([
        'user_id' => anAdmin()->id,
        'lab_environment_id' => $environment->id,
        'container_name' => $environment->container_name,
        // A pid no process answers to. The row exists, so a page that only
        // looked at the row would call this connected.
        'pid' => '999999',
        'ttyd_port' => '38000',
        'credential' => str_repeat('a1', 24),
        'expires_at' => now()->addMinutes(15),
    ]);

    $status = statusOf($environment);

    expect($status['code'])->toBe('process_gone')
        ->and($status['label'])->toBe('NOT CONNECTED');
});

test('an expired session is not reported as connected', function () {
    $environment = runningEnv();

    TerminalSession::query()->create([
        'user_id' => anAdmin()->id,
        'lab_environment_id' => $environment->id,
        'container_name' => $environment->container_name,
        'pid' => (string) getmypid(),
        'ttyd_port' => '38000',
        'credential' => str_repeat('a1', 24),
        'expires_at' => now()->subMinute(),
    ]);

    expect(statusOf($environment)['code'])->toBe('no_session');
});

test('the page hands the browser no address while browser access is off', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', false);

    $page = Livewire::actingAs(anAdmin())->test(Terminal::class)->instance();

    expect($page->terminalUrl(runningEnv()))->toBeNull();
});

test('the admin page does not mount a terminal iframe without an authorized session', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', false);

    $this->actingAs(anAdmin());
    runningEnv();

    $this->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Disconnected')
        ->assertDontSee('<iframe', false);
});

test('a non administrator sees only the environments they own', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $names = Livewire::actingAs($student)->test(Terminal::class)
        ->instance()
        ->environments()
        ->pluck('container_name');

    expect($names)->not->toContain('lab-someone-else');
});

test('a non administrator does not see environments owned by someone else', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    runningEnv(['container_name' => 'lab-owned', 'user_id' => $student->id]);
    runningEnv(['container_name' => 'lab-someone-else', 'user_id' => anAdmin()->id]);

    $names = Livewire::actingAs($student)->test(Terminal::class)
        ->instance()
        ->environments()
        ->pluck('container_name');

    expect($names)->toContain('lab-owned')
        ->and($names)->not->toContain('lab-someone-else');
});

test('an administrator sees every environment', function () {
    runningEnv(['container_name' => 'lab-one']);
    runningEnv(['container_name' => 'lab-two']);

    expect(Livewire::actingAs(anAdmin())->test(Terminal::class)
        ->instance()
        ->environments()
        ->pluck('container_name'))
        ->toContain('lab-one', 'lab-two');
});

test('the admin page shows the three infrastructure nodes and no student containers', function () {
    $this->actingAs(anAdmin());
    runningEnv(['container_name' => 'lab-web-smoke']);

    $content = $this->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()->getContent();

    expect($content)
        ->toContain('IAAS-MGMT')
        ->toContain('LAB-ENV')
        ->toContain('LAB-ATK')
        ->not->toContain('lab-web-smoke')
        ->not->toContain('lab-tofu-test')
        ->not->toContain('victim-demo');
});
