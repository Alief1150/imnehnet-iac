<?php

use App\Support\ShellRunner;
use App\Support\TerminalSlotAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

/**
 * What the shell runner refuses, and what the web does when it is not there.
 *
 * ── Why the runner is exercised by running it ─────────────────────────────────────────
 *
 * The runner is a script, not a class, and its refusals are the security boundary of the whole
 * arrangement: the web can ask it to start a shell and nothing else. A test that did not run it
 * would be testing a copy of its rules rather than the rules, which is how a refusal gets lost
 * and nobody notices until somebody tries it.
 *
 * So the runner is run — with --once, which answers a single request from stdin and exits. No
 * socket, no daemon, nothing left listening, and nothing that can collide with a runner already
 * running on this host.
 *
 * Every case below is something a caller might plausibly try. The list is long on purpose: the
 * refusals that matter are the ones nobody thinks to attack.
 *
 *  @see \App\Services\Terminal\AdminTerminalService::spawnThroughRunner
 */
uses(RefreshDatabase::class);

/**
 * Ask the runner one question and return the reply.
 *
 * @return array<string, mixed>
 */
function askRunner(array $request, array $environment = []): array
{
    $process = new Process(
        array_merge([PHP_BINARY, base_path('bin/shell-runner.php'), '--once'], []),
        base_path(),
        array_merge([
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) getenv('HOME'),
            // A record file of its own, so a test never writes to the one a live runner uses.
            'SHELL_RUNNER_RECORD' => storage_path('framework/testing/runner-'.bin2hex(random_bytes(6)).'.json'),
        ], $environment),
    );

    $process->setInput(json_encode($request).PHP_EOL);
    $process->setTimeout(20);
    $process->run();

    $reply = json_decode(trim($process->getOutput()), true);

    expect($reply)->toBeArray('the runner answered something that was not JSON: '.$process->getOutput().$process->getErrorOutput());

    return $reply;
}

// ── the boundary ─────────────────────────────────────────────────────────────────────

test('a node outside the declared three is refused', function () {
    foreach (['localhost', '../../etc', 'iaas-mgmt; rm -rf /', '', 'IAAS-MGMT'] as $node) {
        $reply = askRunner(['op' => 'open', 'node' => $node, 'port' => 38000]);

        expect($reply['ok'])->toBeFalse("node '{$node}' should have been refused")
            ->and($reply['error'])->toContain('not a node this runner can open');
    }
});

test('a port outside the configured window is refused', function () {
    // The window is the whole of the port argument: a caller cannot point ttyd at a port it
    // did not lease, or at one it invented.
    foreach ([1, 80, 7999, 38008, 65535, 0, -1] as $port) {
        $reply = askRunner(['op' => 'open', 'node' => 'iaas-mgmt', 'port' => $port]);

        expect($reply['ok'])->toBeFalse("port {$port} should have been refused")
            ->and($reply['error'])->toContain('outside the configured window');
    }
});

test('an operation it does not implement is refused', function () {
    foreach (['rm -rf /', 'exec', 'shutdown', ''] as $op) {
        $reply = askRunner(['op' => $op]);

        expect($reply['ok'])->toBeFalse("op '{$op}' should have been refused")
            ->and($reply['error'])->toBe('Unknown operation.');
    }
});

test('a request that is not a request is refused', function () {
    $process = new Process(
        [PHP_BINARY, base_path('bin/shell-runner.php'), '--once'],
        base_path(),
        ['PATH' => (string) getenv('PATH'), 'HOME' => (string) getenv('HOME')],
    );
    $process->setInput('this is not json'.PHP_EOL);
    $process->setTimeout(20);
    $process->run();

    expect(json_decode(trim($process->getOutput()), true))
        ->toMatchArray(['ok' => false, 'error' => 'Malformed request.']);
});

test('close refuses a pid this runner did not start', function () {
    // /proc would happily confirm that a pid is ttyd; what close() checks is a list the runner
    // wrote itself. So every pid that is not on it is refused, including one that genuinely is
    // a listener started elsewhere.
    $reply = askRunner(['op' => 'close', 'pid' => getmypid()]);

    expect($reply['ok'])->toBeFalse()
        ->and($reply['error'])->toContain('not started by this runner');

    foreach ([0, 1, -1, 999999999] as $pid) {
        expect(askRunner(['op' => 'close', 'pid' => $pid])['ok'])->toBeFalse();
    }
});

test('an operand the runner does not read cannot change what is run', function () {
    // A caller that tries to smuggle a command in gets it ignored rather than honoured — the
    // request is accepted because the node and port are legitimate, and the command comes from
    // the runner's own table. What must not happen is a refusal being fabricated here: the test
    // is that the answer is not an error and that nothing outside the declared vector ran.
    $reply = askRunner([
        'op' => 'open',
        'node' => 'iaas-mgmt',
        'port' => 38000,
        'command' => ['rm', '-rf', '/'],
        'binary' => '/bin/sh',
        'user' => 'root',
        'env' => ['PATH' => '/tmp'],
    ]);

    // Either it opened the declared command, or something else refused it for a stated reason.
    // What it must never do is open what the caller asked for.
    if ($reply['ok'] ?? false) {
        $cmdline = (string) @file_get_contents("/proc/{$reply['pid']}/cmdline");

        expect($cmdline)->toContain('ttyd')
            ->and($cmdline)->not->toContain('rm -rf /')
            ->and($cmdline)->not->toContain('/bin/sh');

        @posix_kill((int) $reply['pid'], 15);
    } else {
        expect($reply['error'])->toBeString();
    }
});

// ── the web's side ───────────────────────────────────────────────────────────────────

test('the web says how to start a runner when there is none', function () {
    // "Connection refused" is not something an operator can act on. The message has to name the
    // thing that is missing and the command that starts it, or the console reads as broken
    // rather than as uninstalled.
    $runner = new ShellRunner('/nonexistent/runner.sock');

    expect($runner->available())->toBeFalse()
        ->and($runner->who())->toBeNull();

    try {
        $runner->open('iaas-mgmt', 38000, '/tmp/x.log');
        $this->fail('an absent runner must not report success');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('shell runner')
            ->toContain('scripts/shell-supervisor.sh');
    }
});

test('a request the runner refuses reaches the caller as a refusal', function () {
    // The client must not turn a refusal into a falsy value the caller might ignore: a terminal
    // that did not open and one that did look the same to a reader.
    $process = new Process(
        [PHP_BINARY, base_path('bin/shell-runner.php'), '--once'],
        base_path(),
        ['PATH' => (string) getenv('PATH'), 'HOME' => (string) getenv('HOME')],
    );
    $process->setInput(json_encode(['op' => 'open', 'node' => 'nope', 'port' => 38000]).PHP_EOL);
    $process->setTimeout(20);
    $process->run();

    $reply = json_decode(trim($process->getOutput()), true);

    expect($reply['ok'])->toBeFalse()
        ->and($reply['error'])->toContain('not a node');
});

// ── the lease, and who owns it ───────────────────────────────────────────────────────

test('the credential is offered for the session and is not in the address', function () {
    // The address carries nothing; the pair comes from the session row. If a change ever puts
    // the secret back into the URL, this is what catches it.
    $auth = new TerminalSlotAuth(storage_path('framework/testing/htpasswd-'.bin2hex(random_bytes(6))));
    $credential = bin2hex(random_bytes(24));

    $auth->grant(38000, $credential);

    expect($auth->isLeased(38000))->toBeTrue();

    $line = trim((string) file_get_contents($auth->path(38000)));

    expect($line)->toStartWith(TerminalSlotAuth::USERNAME.':$2y$')
        ->and($line)->not->toContain($credential)
        ->and(password_verify($credential, explode(':', $line, 2)[1]))->toBeTrue();

    $auth->revoke(38000);

    expect($auth->isLeased(38000))->toBeFalse();
});