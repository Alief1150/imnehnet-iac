<?php

/**
 * The shell runner: the only process that starts administrative shells.
 *
 * ── Why this exists at all ───────────────────────────────────────────────────────────
 *
 * An administrative shell on IAAS-MGMT runs as the account that owns the host — it has to,
 * because that is what "administrative" means here, and that account can sudo. An
 * administrative shell on LAB-ENV or LAB-ATK is an ssh as a key only iaas can read.
 *
 * The web runs as www-data. So the web cannot start those shells itself, and the two ways to
 * make it able are both wrong:
 *
 *   - run php-fpm as iaas. Then every web vulnerability on the host becomes root-adjacent,
 *     because the process reading untrusted input can already sudo. A portal that renders
 *     environment names and container output does not get to be the web server's owner.
 *   - relax the check and let www-data spawn the shell. It would open, and it would be an
 *     unprivileged shell on a host the operator was told was privileged — a control that
 *     reports success while granting less than it claims.
 *
 * So the shells are started here, by a process that runs as iaas, and the web asks for them
 * over a unix socket. The web can request a shell on one of three named nodes; it cannot name a
 * command, a path, a binary or a port outside the configured window.
 *
 * ── What the runner refuses, and why each refusal is here ─────────────────────────────
 *
 *   node not in the list     The list is repeated here rather than imported from the
 *                             application. The web is not trusted to decide what a shell may
 *                             target — that decision is the whole security boundary, so it is
 *                             made in the process that performs the action.
 *   port outside the window  A caller must not be able to point ttyd at an arbitrary port.
 *   port already bound       Rather than opening a second listener on a live port.
 *   a pid the runner did not start
 *                             close() only signals pids in its own record. The web cannot name
 *                             an arbitrary process. This is stricter than checking /proc: it
 *                             is a list the runner wrote, not a guess about who owns what.
 *
 * ── Liveness, across user boundaries ──────────────────────────────────────────────────
 *
 * From www-data, kill(pid, 0) on an iaas-owned process returns EPERM rather than success. A
 * liveness check written as posix_kill would therefore report a healthy shell as gone the
 * moment the web asked about it. Liveness is decided from /proc here and in the application:
 * the entry exists, and its state field is not Z.
 *
 * ── Shape ────────────────────────────────────────────────────────────────────────────
 *
 * One JSON object per line, one reply per line, one connection at a time. Single-threaded on
 * purpose: a terminal is opened by one person at a time, and a serial loop cannot interleave
 * two opens for the same port. The cost is that two simultaneous requests queue, which for
 * this load is not a cost worth a lock.
 */

declare(strict_types=1);

const USERNAME = 'imnehnet';

/** The nodes that may be opened, and the command that reaches each. */
const NODES = [
    'iaas-mgmt' => ['label' => 'IAAS-MGMT', 'login' => 'iaas', 'command' => ['bash', '-l']],
    'lab-env' => [
        'label' => 'LAB-ENV',
        'login' => 'lab@192.168.100.20',
        'command' => [
            'ssh',
            '-i', '/home/iaas/.ssh/imnehnet_ansible',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            'lab@192.168.100.20',
        ],
    ],
    'lab-atk' => [
        'label' => 'LAB-ATK',
        'login' => 'labatk@192.168.100.30',
        'command' => [
            'ssh',
            '-i', '/home/iaas/.ssh/imnehnet_ansible',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            'labatk@192.168.100.30',
        ],
    ],
];

const PORT_MIN = 38000;
const PORT_MAX = 38007;

/** Seconds to wait for the child to stop after a signal before escalating. */
const SIGNAL_GRACE = 10;

$config = [
    'socket' => getenv('SHELL_RUNNER_SOCKET') ?: '/var/lib/imnehnet/terminal/runner.sock',
    'record' => getenv('SHELL_RUNNER_RECORD') ?: '/var/lib/imnehnet/terminal/runner-pids.json',
    'log' => getenv('SHELL_RUNNER_LOG') ?: '/home/iaas/projects/webportal-iaas/storage/logs/shell-runner.log',
];

// ── logging ──────────────────────────────────────────────────────────────────────────

function note(array $config, string $message, array $context = []): void
{
    $line = sprintf(
        "[%s] %s%s",
        date('Y-m-d H:i:s'),
        $message,
        $context === [] ? '' : ' '.json_encode($context, JSON_UNESCAPED_SLASHES),
    );

    $directory = dirname($config['log']);

    if (! is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    @file_put_contents($config['log'], $line.PHP_EOL, FILE_APPEND);
}

// ── the record of what this runner started ───────────────────────────────────────────
//
// Kept on disk rather than in memory so a runner that is restarted — by the supervisor, or by
// a crash — still knows which pids are its own and which are not. A runner that has forgotten
// could not close anything, and a runner that has guessed could close something it did not
// start.

function loadRecord(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function saveRecord(string $path, array $record): void
{
    $temporary = $path.'.'.getmypid().'.tmp';

    if (@file_put_contents($temporary, json_encode($record, JSON_PRETTY_PRINT)) === false) {
        return;
    }

    @chmod($temporary, 0660);
    @rename($temporary, $path);
}

// ── liveness and ownership ──────────────────────────────────────────────────────────

/**
 * Whether this pid is a running ttyd, read from /proc.
 *
 * Not posix_kill: from a different user it answers EPERM for a process that is very much
 * alive, which would make a healthy shell look gone.
 */
function isAlive(int $pid): bool
{
    if ($pid <= 1) {
        return false;
    }

    $stat = @file_get_contents("/proc/{$pid}/stat");

    if ($stat === false) {
        return false;
    }

    // The comm field is parenthesised and may contain spaces, so the state is the first field
    // after its closing bracket. Z is a process that has exited and is not yet reaped, which
    // is not the same as one that is serving a shell.
    $tail = explode(') ', $stat, 2)[1] ?? '';
    $state = explode(' ', $tail)[0] ?? '';

    return $state !== 'Z' && $state !== 'X';
}

function isTtyd(int $pid): bool
{
    $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

    return is_string($cmdline) && $cmdline !== '' && str_contains($cmdline, 'ttyd');
}

/**
 * Collect anything this runner has already lost.
 *
 * Listeners are started with proc_open and this process never waits for them, so a closed
 * terminal leaves an exited-but-unwaited child with an entry in /proc that only goes away when
 * the runner itself does. Nothing reads that entry as alive — a zombie is not a shell — but one
 * per session is a leak that only announces itself as a process table filling up.
 *
 * WNOHANG throughout, because this loop serves one request at a time and a blocking wait would
 * hang it on a child that has not exited yet, or on one that is not ours.
 */
function reapExited(): int
{
    $collected = 0;

    while (true) {
        $result = pcntl_waitpid(-1, $status, WNOHANG);

        if ($result <= 0) {
            return $collected;
        }

        $collected++;
    }
}

function portIsFree(int $port): bool
{
    $handle = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);

    if (is_resource($handle)) {
        fclose($handle);

        return false;
    }

    return true;
}

// ── operations ───────────────────────────────────────────────────────────────────────

function opPing(array $config): array
{
    return [
        'ok' => true,
        'user' => function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
            : 'unknown',
        'pid' => getmypid(),
        'started' => $config['started'] ?? null,
    ];
}

function opOpen(array $request, array $config): array
{
    $node = (string) ($request['node'] ?? '');

    if (! array_key_exists($node, NODES)) {
        return ['ok' => false, 'error' => 'That is not a node this runner can open.'];
    }

    $port = (int) ($request['port'] ?? 0);

    if ($port < PORT_MIN || $port > PORT_MAX) {
        return ['ok' => false, 'error' => 'That port is outside the configured window.'];
    }

    if (! portIsFree($port)) {
        return ['ok' => false, 'error' => 'That slot already has a listener on it.'];
    }

    $target = NODES[$node];

    // Collect anything a previous request left behind, before this one adds to it.
    reapExited();

    $command = array_merge([
        '/home/iaas/bin/ttyd',
        '-i', '127.0.0.1',
        '-p', (string) $port,
        '-W',
        '-t', 'titleFixed='.$target['login'].'@'.$node,
    ], $target['command']);

    $environment = ['TERM=xterm-256color', 'LANG=C.UTF-8', 'LC_ALL=C.UTF-8'];

    $path = getenv('PATH');
    if (is_string($path) && $path !== '') {
        $environment[] = 'PATH='.$path;
    }

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $request['log'] ?? '/dev/null', 'a'],
        2 => ['file', $request['log'] ?? '/dev/null', 'a'],
    ];

    // setsid so the listener leads its own session. Without it the listener shares this
    // runner's session, and stopping the runner takes every open terminal with it.
    $handle = proc_open(
        array_merge(['setsid'], $command),
        $descriptors,
        $pipes,
        null,
        $environment,
    );

    if (! is_resource($handle)) {
        return ['ok' => false, 'error' => 'The terminal process could not be started.'];
    }

    $status = proc_get_status($handle);
    $pid = (int) ($status['pid'] ?? 0);

    // proc_close is deliberately not called: it waits for the child, which is the opposite of
    // what this is for.
    unset($handle);

    if ($pid <= 1) {
        return ['ok' => false, 'error' => 'The terminal process could not be started.'];
    }

    // ttyd exits at once if it cannot bind, so a refusal has to be caught here rather than
    // reported later as a terminal that connects and does nothing.
    usleep(300_000);

    if (! isAlive($pid) || ! isTtyd($pid)) {
        return ['ok' => false, 'error' => 'The terminal process did not start.'];
    }

    $record = loadRecord($config['record']);
    $record[(string) $pid] = ['node' => $node, 'port' => $port, 'started' => time()];
    saveRecord($config['record'], $record);

    note($config, 'opened', ['node' => $node, 'port' => $port, 'pid' => $pid]);

    return ['ok' => true, 'pid' => $pid, 'port' => $port];
}

function opClose(array $request, array $config): array
{
    $pid = (int) ($request['pid'] ?? 0);
    $record = loadRecord($config['record']);

    // Only a pid this runner started. Not a check against /proc and not a check against the
    // caller: the list is what the runner itself wrote, so the answer cannot be talked into.
    if (! array_key_exists((string) $pid, $record)) {
        return ['ok' => false, 'error' => 'That process was not started by this runner.'];
    }

    if (! isAlive($pid) || ! isTtyd($pid)) {
        unset($record[(string) $pid]);
        saveRecord($config['record'], $record);

        return ['ok' => true, 'note' => 'Already gone.'];
    }

    // Collect first: a previous close may have left the child unreaped, and the pid we are
    // about to signal is only meaningful because it is not itself one.
    reapExited();

    // SIGHUP first so ttyd can pass it to its shell, SIGTERM next, SIGKILL last.
    foreach ([1, 15] as $signal) {
        @posix_kill($pid, $signal);

        for ($wait = 0; $wait < SIGNAL_GRACE && isAlive($pid); $wait++) {
            usleep(100_000);
        }

        if (! isAlive($pid)) {
            unset($record[(string) $pid]);
            saveRecord($config['record'], $record);

            // Every return goes through a reap. It was put in before signalling and after
            // kill -9, and the branch that actually succeeds — SIGHUP first try — returned
            // straight past it, which is how every close was still leaving a zombie behind.
            reapExited();

            note($config, 'closed', ['pid' => $pid, 'signal' => $signal]);

            return ['ok' => true];
        }
    }

    @posix_kill($pid, 9);
    usleep(200_000);
    reapExited();

    unset($record[(string) $pid]);
    saveRecord($config['record'], $record);
    note($config, 'closed', ['pid' => $pid, 'signal' => 9]);

    return ['ok' => true];
}

function opStatus(array $request): array
{
    $pid = (int) ($request['pid'] ?? 0);

    return ['ok' => true, 'pid' => $pid, 'alive' => isAlive($pid) && isTtyd($pid)];
}

// ── the loop ─────────────────────────────────────────────────────────────────────────

function reply($connection, array $payload, array $config): void
{
    @fwrite($connection, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
    @fflush($connection);
}

$effective = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? null)
    : null;

if ($effective !== 'iaas') {
    fwrite(STDERR, "The shell runner must run as iaas. This process is: ".var_export($effective, true).PHP_EOL);
    exit(1);
}

/*
 * --once: answer one request from stdin and exit.
 *
 * Same code path as the loop below, without the socket and without staying up, so the refusals
 * can be tested by a suite instead of being checked once by hand. No socket is created, so a
 * test cannot collide with a runner that is already running on this host.
 */
if (in_array('--once', $argv, true)) {
    $line = fgets(STDIN, 8192);
    $request = is_string($line) ? json_decode(trim($line), true) : null;

    if (! is_array($request)) {
        fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'Malformed request.']).PHP_EOL);
        exit(1);
    }

    try {
        $payload = match ((string) ($request['op'] ?? '')) {
            'ping' => opPing($config),
            'open' => opOpen($request, $config),
            'close' => opClose($request, $config),
            'status' => opStatus($request),
            default => ['ok' => false, 'error' => 'Unknown operation.'],
        };
    } catch (Throwable $exception) {
        $payload = ['ok' => false, 'error' => 'The runner could not complete that.'];
    }

    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit((int) (($payload['ok'] ?? false) === false));
}

if (! is_dir(dirname($config['socket']))) {
    @mkdir(dirname($config['socket']), 0770, true);
}

// A socket left behind by a runner that was killed would make bind() fail, and the failure
// would read as "the runner is broken" rather than "a previous one exited without cleaning
// up". It is safe to remove: the path is inside a directory only iaas and the web can write,
// and only this runner ever creates it.
if (file_exists($config['socket'])) {
    @unlink($config['socket']);
}

$server = @stream_socket_server('unix://'.$config['socket'], $errno, $errstr);

if (! is_resource($server)) {
    fwrite(STDERR, "Could not listen on {$config['socket']}: {$errstr} ({$errno})".PHP_EOL);
    exit(1);
}

// Group-writable so the web can connect and nothing else can. The directory is what stops
// anyone else from reaching the socket at all.
@chown($config['socket'], 'iaas');
@chgrp($config['socket'], 'www-data');
@chmod($config['socket'], 0660);

// Drop anything the previous runner left behind that is no longer a listener. The record is
// deliberately on disk so a restarted runner still knows which pids are its own — which also
// means it inherits pids that died while it was not running, and those would otherwise sit in
// the file for ever and be refused by close() as processes it no longer owns.
//
// Only genuinely gone pids are removed. One that is alive right now and exits in a moment is
// exactly what close() exists for, and dropping it here would leave its listener unrecorded
// while it is still serving.
$record = loadRecord($config['record']);
$stale = 0;
foreach ($record as $recordedPid => $_) {
    if (! isAlive((int) $recordedPid) || ! isTtyd((int) $recordedPid)) {
        unset($record[(string) $recordedPid]);
        $stale++;
    }
}

if ($stale > 0) {
    saveRecord($config['record'], $record);
}

$config['started'] = date('c');
note($config, 'runner listening', [
    'socket' => $config['socket'],
    'pid' => getmypid(),
    'pruned' => $stale,
]);

pcntl_async_signals(true);

foreach ([SIGTERM, SIGINT] as $signal) {
    pcntl_signal($signal, function () use ($server, $config) {
        note($config, 'runner stopping');
        @unlink($config['socket']);
        @fclose($server);
        exit(0);
    });
}

while (true) {
    $connection = @stream_socket_accept($server, -1);

    if (! is_resource($connection)) {
        usleep(200_000);

        continue;
    }

    stream_set_timeout($connection, 5);

    $line = fgets($connection, 8192);
    $request = is_string($line) ? json_decode(trim($line), true) : null;

    if (! is_array($request)) {
        reply($connection, ['ok' => false, 'error' => 'Malformed request.'], $config);
        @fclose($connection);

        continue;
    }

    try {
        reapExited();

        $payload = match ((string) ($request['op'] ?? '')) {
            'ping' => opPing($config),
            'open' => opOpen($request, $config),
            'close' => opClose($request, $config),
            'status' => opStatus($request),
            default => ['ok' => false, 'error' => 'Unknown operation.'],
        };
    } catch (Throwable $exception) {
        note($config, 'error', ['op' => $request['op'] ?? null, 'error' => $exception->getMessage()]);

        $payload = ['ok' => false, 'error' => 'The runner could not complete that.'];
    }

    reply($connection, $payload, $config);
    @fclose($connection);
}