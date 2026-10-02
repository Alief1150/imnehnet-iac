<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\TerminalService;
use App\Support\EnvironmentWorkspace;
use App\Support\TerminalSlotGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

/**
 * The guards that decide whether a terminal opens at all.
 *
 * These are the rules that matter most in this feature, so they are exercised
 * without spawning anything. Reaching a shell means running a process, and a
 * test that spawns a real one against the real compute plane is a test that
 * eventually takes down something it was only meant to observe.
 */
function runningEnvironment(array $attributes = []): LabEnvironment
{
    return LabEnvironment::factory()->create(array_merge([
        'container_name' => 'lab-web-smoke',
        'tofu_workspace' => 'env-lab-web-smoke',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ], $attributes));
}

function terminalService(): TerminalService
{
    return new TerminalService(
        binary: '/home/iaas/bin/ttyd',
        shellUser: 'student',
        incusRemote: 'lab-env:',
        sessionTtlMinutes: 15,
        maxSessionsPerUser: 2,
        portRange: [38100, 38150],
        logDirectory: storage_path('logs/terminal-test'),
        probeTimeoutSeconds: 3,
        grants: new TerminalSlotGrants(slotMapPath()),
    );
}

/**
 * Each test gets its own map file. Sharing one would let a grant written by one
 * test satisfy another, and the tests would pass while the behaviour is wrong.
 */
function slotMapPath(): string
{
    $path = storage_path('framework/testing/slots-'.bin2hex(random_bytes(6)).'.map');

    return $path;
}

test('the terminal is refused when the environment does not enable it', function () {
    $environment = runningEnvironment(['terminal_enabled' => false]);

    terminalService()->open(User::factory()->create(), $environment);
})->throws(RuntimeException::class, 'terminal is not enabled');

test('the terminal is refused for the protected reference container', function () {
    config()->set('infrastructure.protected_containers', ['lab-tofu-test']);

    $environment = runningEnvironment([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
    ]);

    terminalService()->open(User::factory()->create(), $environment);
})->throws(RuntimeException::class, 'protected reference environment');

test('the terminal is refused when the runtime is not running', function () {
    $environment = runningEnvironment([
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    terminalService()->open(User::factory()->create(), $environment);
})->throws(RuntimeException::class, 'not running');

test('the terminal is refused when the runtime is unknown rather than assumed stopped', function () {
    $environment = runningEnvironment([
        'runtime_status' => RuntimeStatus::Unknown,
    ]);

    terminalService()->open(User::factory()->create(), $environment);
})->throws(RuntimeException::class, 'Unknown');

test('a missing student user refuses the session instead of falling back to a root shell', function () {
    $environment = runningEnvironment();

    // The uid is looked up by running incus, so the lookup is pointed at a binary
    // that does not exist to make it fail here. It used to pass because Incus was
    // unreachable from the test suite, which is a property of the day rather than
    // of the code: as soon as a real environment was created on the compute plane
    // the lookup started succeeding, the guard was walked past, and the test failed
    // further along for a reason it never described.
    config()->set('infrastructure.incus_binary', '/nonexistent/incus-for-tests');

    // The refusal is the point: a hardcoded fallback uid would silently hand out a
    // root shell.
    expect(fn () => terminalService()->open(User::factory()->create(), $environment))
        ->toThrow(RuntimeException::class);
});

test('a session reports itself closed once closed, even while its process lingers', function () {
    // The pid here is the test runner itself, which is deliberately not a
    // terminal process. The session must still be marked closed, which is the
    // point: the row records the decision, and the decision does not depend on
    // whether the process could be found and killed.
    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => runningEnvironment()->id,
        'container_name' => 'lab-web-smoke',
        'pid' => (string) getmypid(),
        'ttyd_port' => '38101',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect($session->isUsable())->toBeTrue()
        ->and($session->processIsAlive())->toBeTrue();

    terminalService()->close($session);

    expect($session->fresh()->isClosed())->toBeTrue()
        ->and($session->fresh()->isUsable())->toBeFalse();
});

test('closing a session never signals a process the portal did not start', function () {
    // Signalling is the one irreversible thing this service does. A pid in the
    // table could be stale or altered, and the operating system will have given
    // that number to something else by then, so an unverified pid must be left
    // alone rather than killed on the strength of the record.
    $unrelated = new Process(['sleep', '30']);
    $unrelated->disableOutput();
    $unrelated->start();
    $pid = (int) $unrelated->getPid();

    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => runningEnvironment()->id,
        'container_name' => 'lab-web-smoke',
        'pid' => (string) $pid,
        'ttyd_port' => '38106',
        'expires_at' => now()->addMinutes(15),
    ]);

    terminalService()->close($session);
    usleep(400_000);

    expect(posix_kill($pid, 0))->toBeTrue('an unrelated live process must survive')
        ->and($session->fresh()->isClosed())->toBeTrue();

    posix_kill($pid, 9);
});

test('a live process that is not a terminal does not count as connected', function () {
    $unrelated = new Process(['sleep', '30']);
    $unrelated->disableOutput();
    $unrelated->start();
    $pid = (int) $unrelated->getPid();

    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => runningEnvironment()->id,
        'container_name' => 'lab-web-smoke',
        'pid' => (string) $pid,
        'ttyd_port' => '38107',
        'expires_at' => now()->addMinutes(15),
    ]);

    // Alive is not the same as connected. Reporting this as connected would be
    // the invented health the portal is supposed to refuse.
    expect($session->processIsAlive())->toBeTrue()
        ->and(terminalService()->isConnected($session))->toBeFalse();

    posix_kill($pid, 9);
});

test('an expired session is no longer usable even before the reaper runs', function () {
    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => runningEnvironment()->id,
        'container_name' => 'lab-web-smoke',
        'pid' => null,
        'ttyd_port' => '38102',
        'expires_at' => now()->subMinute(),
    ]);

    expect($session->isUsable())->toBeFalse()
        ->and($session->hasExpired())->toBeTrue();
});

test('the reaper ends expired sessions and reports how many it ended', function () {
    $environment = runningEnvironment();

    $expired = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => $environment->id,
        'container_name' => 'lab-web-smoke',
        'pid' => (string) getmypid(),
        'ttyd_port' => '38103',
        'expires_at' => now()->subMinute(),
    ]);

    $live = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => $environment->id,
        'container_name' => 'lab-web-smoke',
        'pid' => null,
        'ttyd_port' => '38104',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect(terminalService()->reapExpired())->toBe(1)
        ->and($expired->fresh()->isClosed())->toBeTrue()
        ->and($live->fresh()->isClosed())->toBeFalse();
});

test('a stranded session is reaped even before it expires', function () {
    // The pid is recorded but no process answers to it. Reporting this as
    // connected would be the exact kind of invented health the portal is
    // supposed to refuse.
    $environment = runningEnvironment();

    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => $environment->id,
        'container_name' => 'lab-web-smoke',
        'pid' => '999999',
        'ttyd_port' => '38105',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect($session->processIsAlive())->toBeFalse()
        ->and(terminalService()->reapExpired())->toBe(1)
        ->and($session->fresh()->isClosed())->toBeTrue();
});

test('the credential is stored encrypted rather than in the clear', function () {
    $session = TerminalSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'lab_environment_id' => runningEnvironment()->id,
        'container_name' => 'lab-web-smoke',
        'credential' => 'plaintext-secret-value',
        'expires_at' => now()->addMinutes(15),
    ]);

    $stored = DB::connection(config('infrastructure.environment_connection'))
        ->table('terminal_sessions')
        ->where('id', $session->id)
        ->value('credential');

    expect($stored)->not->toBe('plaintext-secret-value')
        ->and($session->fresh()->credential)->toBe('plaintext-secret-value');
});

test('a container name that could break out of the command line is rejected', function () {
    // The terminal builds a command line that includes the container name, so
    // this is the check standing between a name and a shell.
    $thrown = null;

    try {
        EnvironmentWorkspace::assertValidContainerName('lab-smoke; rm -rf /');
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(Throwable::class)
        ->and($thrown->getMessage())->toContain('not acceptable');
});
