<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Terminal;
use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
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
        ->toContain('wire:loading')
        ->toContain('target=lab-env')
        ->toContain('target=lab-atk')
        ->not->toContain('lab-web-smoke')
        ->not->toContain('lab-tofu-test')
        ->not->toContain('victim-demo');
});

test('the admin page mounts an embedded frame for a leased session', function () {    // A mounted frame is a claim that a shell is behind it. The row alone is not
    // enough for that claim — the slot must also be leased for embedding, which is
    // the ticket file the vhost tests for.
    config()->set('infrastructure.terminal.browser_access_enabled', true);

    $directory = storage_path('framework/testing/embed-page-'.bin2hex(random_bytes(6)));
    config()->set('infrastructure.terminal.embed_directory', $directory);
    $pageTickets = new \App\Support\TerminalEmbedTicket($directory);
    $pageTickets->issue(38000);

    $admin = anAdmin();

    TerminalSession::query()->create([
        'user_id' => $admin->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'node',
        'target_node' => 'iaas-mgmt',
        'container_name' => null,
        // The test runner's own pid: alive, and not a terminal listener, so nothing
        // here can end a real process.
        'pid' => (string) getmypid(),
        'ttyd_port' => '38000',
        'credential' => bin2hex(random_bytes(24)),
        'shell_user' => 'iaas',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($admin);

    $this->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('<iframe', false)
        ->assertSee('/embed/38000/', false)
        ->assertSee('fontSize=15', false)
        ->assertDontSee('Session credential');

    $pageTickets->revokeAll();
    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
});

test('opening for a node selects it and queues its ticket without spawning', function () {
    // The service is mocked so no process starts: what is under test is the
    // page's wiring — the right node is opened, the selection follows it, and
    // the ticket cookie is queued. The real spawn is proven against the running
    // host, never in a unit run.
    config()->set('infrastructure.terminal.browser_access_enabled', true);

    $user = anAdmin();

    $session = TerminalSession::query()->create([
        'user_id' => $user->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'node',
        'target_node' => 'lab-env',
        'container_name' => null,
        'pid' => (string) getmypid(),
        'ttyd_port' => '38001',
        'credential' => bin2hex(random_bytes(24)),
        'shell_user' => 'lab',
        'expires_at' => now()->addMinutes(30),
    ]);

    $service = Mockery::mock(App\Services\Terminal\AdminTerminalService::class);
    $service->shouldReceive('open')->once()->with($user, 'lab-env')->andReturn($session);
    $service->shouldReceive('embedTicketFor')->once()->andReturn('abc123ticket');
    // The call re-renders the page, which asks whether the session is alive
    // and for its direct address.
    $service->shouldReceive('isConnected')->andReturn(true);
    $service->shouldReceive('browserUrl')->andReturn('/terminal/38001/');
    $service->shouldReceive('embedUrl')->andReturn('/embed/38001/');
    app()->instance(App\Services\Terminal\AdminTerminalService::class, $service);

    Livewire::actingAs($user)->test(Terminal::class)
        ->call('openAdminSessionFor', 'lab-env')
        ->assertSet('selectedAdminTarget', 'lab-env');

    // Queued, not set: a Livewire action answers with JSON and the cookie rides
    // on that response, so the jar — not the test response — is what proves it.
    $queued = Cookie::queued('imnehnet_slot_38001');

    expect($queued)->not->toBeNull()
        ->and($queued->getValue())->toBe('abc123ticket');

    // The mock intercepted the open: no real shell was started for this.
    expect(TerminalSession::query()->count())->toBe(1);
});

test('opening for an unknown node is refused without touching the selection', function () {
    $page = Livewire::actingAs(anAdmin())->test(Terminal::class)
        ->call('openAdminSessionFor', '../../etc/passwd');

    $page->assertSet('selectedAdminTarget', 'iaas-mgmt');
});

test('closing for a node closes that nodes shell and forgets its ticket', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', true);

    $user = anAdmin();

    $session = TerminalSession::query()->create([
        'user_id' => $user->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'node',
        'target_node' => 'lab-atk',
        'container_name' => null,
        'pid' => (string) getmypid(),
        'ttyd_port' => '38002',
        'credential' => bin2hex(random_bytes(24)),
        'shell_user' => 'labatk',
        'expires_at' => now()->addMinutes(30),
    ]);

    // The mock closes the row for real instead of signalling anything: what is
    // under test is the page's wiring — the right session is closed and the
    // ticket cookie goes with it — not the process handling.
    $service = Mockery::mock(App\Services\Terminal\AdminTerminalService::class);
    $service->shouldReceive('close')->once()->with(Mockery::on(
        fn (TerminalSession $s): bool => $s->is($session)
    ))->andReturnUsing(
        fn (TerminalSession $s) => $s->forceFill(['closed_at' => now()])->save()
    );
    app()->instance(App\Services\Terminal\AdminTerminalService::class, $service);

    Livewire::actingAs($user)->test(Terminal::class)
        ->call('closeAdminSessionFor', 'lab-atk')
        ->assertSet('selectedAdminTarget', 'lab-atk');

    expect($session->fresh()->closed_at)->not->toBeNull();

    $forgotten = Cookie::queued('imnehnet_slot_38002');

    expect($forgotten)->not->toBeNull()
        ->and($forgotten->getValue())->toBeNull();
});

test('closing for an unknown node is refused without touching the selection', function () {
    Livewire::actingAs(anAdmin())->test(Terminal::class)
        ->call('closeAdminSessionFor', '../../etc/passwd')
        ->assertSet('selectedAdminTarget', 'iaas-mgmt');
});

test('the selected target survives a reload through the address', function () {
    // The panel header names the selected node's address: the management
    // plane's own address for the default, the attacker plane's for
    // ?target=lab-atk, and the default again for a value outside the declared
    // set, which is reset rather than trusted.
    $this->actingAs(anAdmin());

    $this->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('192.168.100.10', false);

    $this->get(Terminal::getUrl(panel: 'admin').'?target=lab-atk')
        ->assertSuccessful()
        ->assertSee('192.168.100.30', false);

    $this->get(Terminal::getUrl(panel: 'admin').'?target=nope')
        ->assertSuccessful()
        ->assertSee('192.168.100.10', false);
});

test('only the node holding a shell offers to close it', function () {
    $admin = anAdmin();

    TerminalSession::query()->create([
        'user_id' => $admin->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'node',
        'target_node' => 'lab-env',
        'container_name' => null,
        'pid' => (string) getmypid(),
        'ttyd_port' => '38000',
        'credential' => bin2hex(random_bytes(24)),
        'shell_user' => 'lab',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($admin);

    $content = $this->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()->getContent();

    expect($content)
        ->toContain("closeAdminSessionFor('lab-env')")
        ->not->toContain("closeAdminSessionFor('lab-atk')");
});
