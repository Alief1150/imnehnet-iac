<?php

use App\Enums\UserRole;
use App\Filament\Pages\Terminal;
use App\Models\ActivityLog;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\Terminal\AdminTerminalService;
use App\Support\TerminalSlotAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The rules that decide whether an administrative shell opens, and what it is handed.
 *
 * Same reasoning as TerminalServiceTest, for the same reason: reaching a shell means
 * running a process on the machine that also runs the portal, so everything here is
 * reachable without a listener.
 *
 * Deliberately not tested here: that a shell opens and takes a command. That was verified
 * against the running host on all three nodes — HTTP 200 with the session credential and
 * 401 with a wrong one, on IAAS-MGMT, LAB-ENV and LAB-ATK. It is not something a unit test
 * should do to production infrastructure on every run.
 */

function adminConsole(): AdminTerminalService
{
    return app(AdminTerminalService::class);
}

/**
 * A session row as the service would have written it: credential in the vhost's hex shape,
 * a pid that is alive, and a window still open.
 */
function aNodeSession(User $user, string $node, array $attributes = []): TerminalSession
{
    return TerminalSession::query()->create(array_merge([
        'user_id' => $user->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'node',
        'target_node' => $node,
        'container_name' => null,
        'pid' => (string) getmypid(),
        'ttyd_port' => '38000',
        'credential' => bin2hex(random_bytes(24)),
        'shell_user' => 'iaas',
        'expires_at' => Carbon::now()->addMinutes(30),
    ], $attributes));
}

// ── rule one: only an elevated role ─────────────────────────────────────────────────

test('a student cannot open an administrative shell', function () {
    adminConsole()->open(User::factory()->create(['role' => UserRole::Student]), 'iaas-mgmt');
})->throws(RuntimeException::class, 'may not open an administrative shell');

test('a lecturer may operate infrastructure but not administer the portal', function () {
    // The role was added for exactly this split, and the split is the thing to pin: a
    // capability nobody exercises quietly stops being granted the next time the role is
    // redefined.
    //
    // canAdminister() is asked of the enum rather than of the model because that is where it
    // lives — the model mirrors canOperateInfrastructure() because the terminal page needs it,
    // and nothing needs the other one yet.
    $lecturer = User::factory()->create(['role' => UserRole::Lecturer]);

    expect($lecturer->canOperateInfrastructure())->toBeTrue()
        ->and($lecturer->role->canAdminister())->toBeFalse();
});

test('a lecturer account can actually be stored and read back', function () {
    // The role existed in the enum while users.role was a database ENUM of only admin and
    // student, so canOperateInfrastructure() returned true for a role no account could hold:
    // the capability was unreachable, and the failure only appeared as a CHECK constraint
    // violation in whatever test happened to try to create one.
    //
    // Stored then read back, because the write succeeding and the read returning a lecturer
    // are different claims — a silent cast back to the first enum value would satisfy the
    // first and quietly fail this feature.
    $created = User::factory()->create(['role' => UserRole::Lecturer]);

    $found = User::query()->find($created->getKey());

    expect($found)->not->toBeNull()
        ->and($found->role)->toBe(UserRole::Lecturer)
        ->and($found->role->canOperateInfrastructure())->toBeTrue()
        ->and($found->role->canAdminister())->toBeFalse();
});

test('a refusal is recorded before any shell could exist', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    try {
        adminConsole()->open($student, 'iaas-mgmt');
    } catch (RuntimeException) {
        // The record is what is under test, not the exception.
    }

    $log = ActivityLog::query()
        ->where('actor_id', $student->getKey())
        ->where('action', 'ADMIN_TERMINAL_OPEN')
        ->first();

    expect($log)->not->toBeNull()
        ->and((string) $log->status)->toBe('failed')
        ->and((string) $log->description)->toContain('may not operate the infrastructure');
});

test('the three nodes are declared, not resolved from configuration', function () {
    // A shell feature whose targets come from configuration has a reach nobody can state.
    $nodes = adminConsole()->availableNodes();

    expect(array_column($nodes, 'key'))->toBe(['iaas-mgmt', 'lab-env', 'lab-atk']);

    foreach ($nodes as $node) {
        expect($node['label'])->not->toBe('')
            ->and($node['role'])->not->toBe('');
    }
});

test('an unknown target is refused rather than passed anywhere', function () {
    try {
        adminConsole()->open(
            User::factory()->create(['role' => UserRole::Admin]),
            '../../etc/passwd; rm -rf /',
        );
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('That is not a node this console can open.');

        return;
    }

    $this->fail('An unknown target must be refused.');
});

// ── what the browser is handed ──────────────────────────────────────────────────────

test('the address carries no credential at all', function () {
    // The credential used to travel as ?t=, which put a per-session secret in the access log,
    // in browser history and in the Referer of anything the page loads. Apache checks the
    // per-session password file for the slot instead, so nothing has to be in the address.
    config()->set('infrastructure.terminal.browser_access_enabled', true);

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');

    $url = adminConsole()->browserUrl($session);

    expect($url)->toBe('/terminal/38000/')
        ->and((string) $url)->not->toContain((string) $session->credential)
        ->and((string) $url)->not->toContain('?')
        ->and((string) $url)->not->toContain('t=');
});

test('the credential handed to the operator is the one the session was opened with', function () {
    $session = aNodeSession(
        User::factory()->create(['role' => UserRole::Admin]),
        'lab-atk',
        ['credential' => bin2hex(random_bytes(24))],
    );

    expect(adminConsole()->credentialsFor($session))->toBe([
        'user' => 'imnehnet',
        'password' => (string) $session->credential,
    ]);
});

test('a session nobody can reach is given no address', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', true);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect(adminConsole()->browserUrl(aNodeSession($user, 'iaas-mgmt', ['closed_at' => Carbon::now()])))->toBeNull();
    expect(adminConsole()->browserUrl(aNodeSession($user, 'iaas-mgmt', ['expires_at' => Carbon::now()->subMinute()])))->toBeNull();
    expect(adminConsole()->browserUrl(aNodeSession($user, 'iaas-mgmt', ['pid' => '999999'])))->toBeNull();
});

test('no address at all while browser access is switched off', function () {
    // A deployment that has decided not to hand shells to browsers has not decided that
    // administrators are the exception. The shell may well be running; the page must still
    // say there is nothing a browser can open.
    config()->set('infrastructure.terminal.browser_access_enabled', false);

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');

    expect(adminConsole()->browserUrl($session))->toBeNull();
});

// ── rule five: closing is not the only way out ──────────────────────────────────────

test('the reaper closes a shell nobody closed', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $stale = aNodeSession($user, 'iaas-mgmt', ['expires_at' => Carbon::now()->subMinute()]);
    $live = aNodeSession($user, 'lab-atk', ['expires_at' => Carbon::now()->addMinute()]);

    expect(adminConsole()->reapExpired())->toBe(1)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($live->fresh()->closed_at)->toBeNull();
});

test('the reaper ignores student sessions', function () {
    // The student terminal has its own reaper and its own lifetime. One reaper that did not
    // distinguish would end a student's shell on the administrator's schedule.
    $stale = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->getKey(),
        'lab_environment_id' => null,
        'target_kind' => 'container',
        'container_name' => 'lab-web-smoke',
        'pid' => (string) getmypid(),
        'ttyd_port' => '38001',
        'credential' => bin2hex(random_bytes(24)),
        'expires_at' => Carbon::now()->subMinute(),
    ]);

    expect(adminConsole()->reapExpired())->toBe(0)
        ->and($stale->fresh()->closed_at)->toBeNull();
});

test('reaping twice closes nothing the second time', function () {
    aNodeSession(
        User::factory()->create(['role' => UserRole::Admin]),
        'iaas-mgmt',
        ['expires_at' => Carbon::now()->subMinute()],
    );

    expect(adminConsole()->reapExpired())->toBe(1)
        ->and(adminConsole()->reapExpired())->toBe(0);
});

// ── rule three: one live session per person, and nobody else's ──────────────────────

test('an administrator is never handed another administrators shell', function () {
    $mine = User::factory()->create(['role' => UserRole::Admin]);
    $theirs = User::factory()->create(['role' => UserRole::Admin]);

    aNodeSession($theirs, 'iaas-mgmt');

    // Scoped to the signed-in person rather than to "the newest row for this node".
    $page = Livewire::actingAs($mine)->test(Terminal::class)->instance();

    expect($page->adminSession())->toBeNull();
});

test('a node session is identified by asking, not by container_name being null', function () {
    // target_kind is what says "this is a node session". Reading container_name for that
    // would make every student session without a container name look like an admin shell.
    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'lab-env');

    expect($session->target_kind)->toBe('node')
        ->and($session->container_name)->toBeNull()
        ->and($session->lab_environment_id)->toBeNull();
});

// ── the lease file ──────────────────────────────────────────────────────────────────

// ── the lease file ───────────────────────────────────────────────────────────────────

test('a leased slot is a password file, and it is gone when revoked', function () {
    // The file's existence is the lease. The vhost tests for it on every request, so writing
    // it opens the slot and deleting it closes it — a single shared map was tried first and
    // can do neither, because it is read once when the server starts and a slot leased after
    // that is never reachable.
    $directory = storage_path('framework/testing/htpasswd-'.bin2hex(random_bytes(6)));
    $auth = new TerminalSlotAuth($directory);
    $credential = bin2hex(random_bytes(24));

    expect($auth->isLeased(38000))->toBeFalse();

    $auth->grant(38000, $credential);

    expect($auth->isLeased(38000))->toBeTrue()
        ->and($auth->leasedPorts())->toBe([38000]);

    // The format matters more than the write: this file is read by Apache, not by the
    // portal, so "written" and "readable by the web server" are different claims.
    $line = trim((string) file_get_contents($auth->path(38000)));

    expect($line)->toStartWith(TerminalSlotAuth::USERNAME.':$2y$')
        ->and($line)->not->toContain($credential);

    // The hash is one the server can actually check, which a substring assertion would not
    // have told us.
    expect(password_verify($credential, explode(':', $line, 2)[1]))->toBeTrue()
        ->and(password_verify('something-else', explode(':', $line, 2)[1]))->toBeFalse();

    $auth->revoke(38000);

    expect($auth->isLeased(38000))->toBeFalse()
        ->and($auth->leasedPorts())->toBe([]);

    array_map('unlink', (array) glob($directory.'/*'));

// ── the embedded frame ──────────────────────────────────────────────────────

function embedServiceTicketDir(): array
{
    $directory = storage_path('framework/testing/embed-svc-'.bin2hex(random_bytes(6)));

    config()->set('infrastructure.terminal.embed_directory', $directory);

    return [$directory, new \App\Support\TerminalEmbedTicket($directory)];
}

test('the embedded address names only the slot, never any secret', function () {
    // The ticket travels in a cookie, so unlike the old ?t= address there is nothing
    // to keep out of the URL — and the test is that nothing secret is in it.
    config()->set('infrastructure.terminal.browser_access_enabled', true);
    [$directory, $tickets] = embedServiceTicketDir();

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');
    $tickets->issue(38000);

    $url = adminConsole()->embedUrl($session);

    expect($url)->toBe('/embed/38000/')
        ->and((string) $url)->not->toContain((string) $session->credential)
        ->and((string) $url)->not->toContain('?');

    $tickets->revokeAll();
    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
});

test('no embedded address without a leased ticket', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', true);
    [$directory, $tickets] = embedServiceTicketDir(); // An empty directory: nothing is leased.

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');

    // The row exists and the process is alive, but the slot was never leased for
    // embedding — a frame mounted here would show a refusal, not a shell.
    expect(adminConsole()->embedUrl($session))->toBeNull();

    @rmdir($directory);
});

test('no embedded address for a session nobody can reach', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', true);
    [$directory, $tickets] = embedServiceTicketDir();
    $tickets->issue(38000);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect(adminConsole()->embedUrl(aNodeSession($user, 'iaas-mgmt', ['closed_at' => Carbon::now()])))->toBeNull();
    expect(adminConsole()->embedUrl(aNodeSession($user, 'iaas-mgmt', ['expires_at' => Carbon::now()->subMinute()])))->toBeNull();
    expect(adminConsole()->embedUrl(aNodeSession($user, 'iaas-mgmt', ['pid' => '999999'])))->toBeNull();

    $tickets->revokeAll();
    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
});

test('no embedded address while browser access is switched off', function () {
    config()->set('infrastructure.terminal.browser_access_enabled', false);
    [$directory, $tickets] = embedServiceTicketDir();
    $tickets->issue(38000);

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');

    expect(adminConsole()->embedUrl($session))->toBeNull();

    $tickets->revokeAll();
    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
});

test('closing a session revokes its embed ticket', function () {
    // close() also terminates the pid. The fabricated pid is the test runner itself,
    // and terminate() refuses any pid that is not a terminal listener, so this ends
    // nothing but the session row.
    [$directory, $tickets] = embedServiceTicketDir();

    $session = aNodeSession(User::factory()->create(['role' => UserRole::Admin]), 'iaas-mgmt');
    $tickets->issue(38000);

    adminConsole()->close($session);

    expect($tickets->tokenFor(38000))->toBeNull();

    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
});

test('the embed cookie is named per slot', function () {
    expect(AdminTerminalService::embedCookieName(38000))->toBe('imnehnet_slot_38000')
        ->and(AdminTerminalService::embedCookieName(38001))->toBe('imnehnet_slot_38001');
});
});