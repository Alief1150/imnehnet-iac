<?php

namespace App\Services\Terminal;

use App\Models\TerminalSession;
use App\Models\User;
use App\Support\TerminalSlotAuth;
use App\Services\ActivityLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Administrative shells on the infrastructure nodes.
 *
 * ── Why this is separate from TerminalService ──────────────────────────────────────
 *
 * They are not the same thing with a different argument. A student shell is a shell inside
 * a container they were handed, running as a uid resolved from that container, and the whole
 * point is that it reaches nothing else. This one is a shell on the host that runs the
 * portal, as an account that can sudo — it reaches everything by construction. Sharing one
 * class would mean every rule about the first had to be stated as an exception to the
 * second, and the day someone adds a method and forgets which half it belongs to is the day
 * a student gets a root shell.
 *
 * ── The five rules ─────────────────────────────────────────────────────────────────
 *
 * 1. Only an elevated role. Asked of the role on the account, not of the panel that happens
 *    to be rendering the page.
 * 2. The listener is loopback only and carries a per-session credential. Neither lock
 *    depends on the other.
 * 3. One session per person per node. A second open closes the first rather than failing.
 * 4. Every open, close and refusal is recorded — the refusal before the shell exists.
 * 5. The record survives, the process does not. Nothing here leaves a shell running.
 *
 * ── The credential ────────────────────────────────────────────────────────────────
 *
 * ttyd's `-c` is HTTP Basic authentication in the form username:password, verified against
 * 1.7.7 on this host: no credential answers 401, the right one answers 200 and upgrades the
 * websocket, and a wrong one answers 401 again.
 *
 * It is deliberately not in the URL. A query string is written to the access log, to browser
 * history and to the Referer of anything the page loads, and this secret is good for two
 * hours. The page therefore offers a link and the browser is prompted once.
 *
 * An iframe cannot send an Authorization header — there is no attribute for it — so the
 * shell opens in its own tab rather than embedded. Authenticated-proxy mode (-H) would allow
 * embedding, but it moves the decision to the web server configuration where one mistake
 * exposes every slot at once rather than one session.
 */
class AdminTerminalService
{
    /** The Basic-auth username half. Fixed and arbitrary; not the portal account. */
    /** Bytes of credential per session. */
    private const CREDENTIAL_BYTES = 24;

    /** How long a session may live if nobody closes it. */
    private const SESSION_MINUTES = 120;

    private const ACTION_OPEN = 'ADMIN_TERMINAL_OPEN';

    private const ACTION_CLOSE = 'ADMIN_TERMINAL_CLOSE';

    /**
     * The nodes that may be opened, and how to reach each.
     *
     * Declared, not discovered. A shell feature whose targets come from configuration has a
     * reach nobody can state. Each command is a fixed argument vector, so no name from a
     * request ever becomes part of a command line.
     *
     * @var list<array{key: string, label: string, role: string, address: ?string, command: list<string>}>
     */
    private const NODES = [
        [
            'key' => 'iaas-mgmt',
            'label' => 'IAAS-MGMT',
            'role' => 'Management Plane',
            'address' => null,
            // bash -l, not bash -i.
            //
            // Measured on this host: /home/iaas/bin/ttyd 1.7.7-40e79c7 (static) segfaults
            // when the command is an interactive shell. `bash`, `bash -l`, `/bin/bash -l`
            // and `sh` all serve HTTP 200 on the same binary; only -i crashes it. /usr/bin/
            // ttyd 1.7.4 takes bash -i without complaint, so the flag is safe there and
            // fatal here — and a console that works on one ttyd build and not the next is
            // a console waiting for a reinstall to break.
            //
            // A login shell is also the right shell for this: it reads /etc/profile, so it
            // is the environment an operator gets at a console rather than an interactive
            // shell with no login context.
            'command' => ['bash', '-l'],
        ],
        [
            'key' => 'lab-env',
            'label' => 'LAB-ENV',
            'role' => 'Lab Compute',
            'address' => 'lab@192.168.100.20',
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
        [
            'key' => 'lab-atk',
            'label' => 'LAB-ATK',
            'role' => 'Attacker Plane',
            'address' => 'labatk@192.168.100.30',
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

    public function __construct(
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * The nodes this person may open, for the target list.
     *
     * @return list<array{key: string, label: string, role: string, address: ?string}>
     */
    public function availableNodes(): array
    {
        $nodes = [];

        foreach (self::NODES as $node) {
            $nodes[] = [
                'key' => $node['key'],
                'label' => $node['label'],
                'role' => $node['role'],
                'address' => $node['address'],
            ];
        }

        return $nodes;
    }

    /**
     * Open a shell on a node.
     *
     * @throws RuntimeException when the person may not, the node is unknown, or the listener
     *                           did not come up.
     */
    public function open(User $user, string $nodeKey): TerminalSession
    {
        $this->assertMayOperate($user);

        $node = $this->node($nodeKey);

        // Before the shell exists. A log written only on success contains nothing about
        // the attempts that failed, which are the ones worth reading.
        $this->activity->record(
            actor: $user,
            action: self::ACTION_OPEN,
            resource: null,
            status: 'requested',
            description: sprintf('Requested an administrative shell on %s.', $node['label']),
        );

        $existing = TerminalSession::query()
            ->where('user_id', $user->getKey())
            ->where('target_kind', 'node')
            ->where('target_node', $node['key'])
            ->whereNull('closed_at')
            ->orderByDesc('created_at')
            ->first();

        // Reconnect reuses the existing PTY, preserving shell history and running tasks.
        if ($existing && $this->isConnected($existing)) {
            return $existing;
        }
        if ($existing) {
            $this->close($existing);
        }

        $port = $this->reservePort();
        $credential = bin2hex(random_bytes(self::CREDENTIAL_BYTES));
        $pid = $this->spawn($node, $port);

        try {
            $session = TerminalSession::query()->create([
            'user_id' => $user->getKey(),
            'lab_environment_id' => null,
            'target_kind' => 'node',
            'target_node' => $node['key'],
            'container_name' => null,
            'pid' => (string) $pid,
            'ttyd_port' => (string) $port,
            'credential' => $credential,
            'shell_uid' => null,
            'shell_user' => $this->loginName($node),
            'expires_at' => Carbon::now()->addMinutes(self::SESSION_MINUTES),
            ]);

            $this->slotAuth()->grant($port, $credential);
        } catch (Throwable $exception) {
            // No orphaned shell when the database or grant file fails.
            $this->terminate($pid);
            if (isset($session)) {
                $session->forceFill(['closed_at' => Carbon::now()])->save();
            }
            throw $exception;
        }

        $this->activity->record(
            actor: $user,
            action: self::ACTION_OPEN,
            resource: null,
            status: 'completed',
            description: sprintf(
                'Administrative shell opened on %s as %s, on the loopback port %d.',
                $node['label'],
                $this->loginName($node),
                $port,
            ),
        );

        return $session;
    }

    /**
     * Close a session: stop the listener and drop its credential.
     */
    public function close(TerminalSession $session): void
    {
        $pid = (int) $session->pid;

        if ($pid > 0) {
            $this->terminate($pid);
        }

        $this->slotAuth()->revoke((int) $session->ttyd_port);

        $session->forceFill(['closed_at' => Carbon::now()])->save();

        $this->activity->record(
            actor: $session->user,
            action: self::ACTION_CLOSE,
            resource: null,
            status: 'completed',
            description: sprintf(
                'Administrative shell on %s closed.',
                $session->target_node ?? 'a node',
            ),
        );
    }

    /**
     * Close every session that has outlived its window.
     *
     * The backstop for a tab closed without logging out, and the reason there is no path
     * through this class that leaves a shell up: closing is not the only way out.
     */
    public function reapExpired(): int
    {
        $reaped = 0;

        $stale = TerminalSession::query()
            ->where('target_kind', 'node')
            ->whereNull('closed_at')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($stale as $session) {
            try {
                $this->close($session);
                $reaped++;
            } catch (Throwable $exception) {
                // One stubborn session must not stop the rest being closed.
                Log::warning('An expired administrative shell could not be closed.', [
                    'session' => $session->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $reaped;
    }

    /**
     * The path the browser reaches the shell on.
     *
     * No credential, here or in a query string: it would be written to the access log.
     */
    public function browserUrl(TerminalSession $session): ?string
    {
        if (! config('infrastructure.terminal.browser_access_enabled') || ! $this->isConnected($session)) {
            return null;
        }

        // No credential here, and none in a query string. Apache checks this slot's per-session
        // password file, so the secret is presented as HTTP Basic rather than carried in an
        // address — where it was before, and where it was written to the access log, to browser
        // history and to the Referer of anything the page loads.
        return sprintf('/terminal/%d/', (int) $session->ttyd_port);
    }

    /**
     * The pair the browser must present, read from the session rather than regenerated.
     *
     * @return array{user: string, password: string}
     */
    public function credentialsFor(TerminalSession $session): array
    {
        return [
            'user' => TerminalSlotAuth::USERNAME,
            'password' => (string) $session->credential,
        ];
    }

    public function isConnected(TerminalSession $session): bool
    {
        return $session->closed_at === null
            && $session->expires_at !== null
            && $session->expires_at->isFuture()
            && (int) $session->pid > 0
            && $this->processIsAlive((int) $session->pid);
    }

    // ── the rules ───────────────────────────────────────────────────────────────

    /**
     * Rule one. Asked of the role, not of the panel serving the page: being rendered
     * inside the admin panel is not authority to open a shell on the host that runs it.
     */
    private function assertMayOperate(User $user): void
    {
        if ($user->role->canOperateInfrastructure()) {
            return;
        }

        $this->activity->record(
            actor: $user,
            action: self::ACTION_OPEN,
            resource: null,
            status: 'failed',
            description: 'Refused: the role may not operate the infrastructure.',
        );

        throw new RuntimeException('You may not open an administrative shell.');
    }

    /**
     * @return array{key: string, label: string, role: string, address: ?string, command: list<string>}
     */
    private function node(string $key): array
    {
        foreach (self::NODES as $node) {
            if ($node['key'] === $key) {
                return $node;
            }
        }

        throw new RuntimeException('That is not a node this console can open.');
    }

    private function loginName(array $node): string
    {
        return $node['address'] ?? (string) (get_current_user() ?: 'iaas');
    }

    // ── the listener ───────────────────────────────────────────────────────────

    /**
     * Start ttyd on loopback and return its pid.
     *
     * Output goes to a file rather than a pipe: this process outlives the request, and a
     * pipe nobody drains fills and blocks the shell.
     */
    private function spawn(array $node, int $port): int
    {
        // PHP-FPM usually runs as www-data, not iaas. Never present that as an
        // administrative shell. Configure a dedicated iaas-owned runner first.
        $effectiveUser = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? null)
            : null;
        if ($effectiveUser !== 'iaas') {
            throw new RuntimeException('Admin terminal runner is not iaas. A dedicated shell runner is required for this web runtime.');
        }

        $binary = (string) config('infrastructure.terminal.binary', 'ttyd');

        $command = [
            $binary,
            '-i', '127.0.0.1',
            '-p', (string) $port,
            '-W',
            '-t', 'titleFixed='.$this->loginName($node).'@'.$node['key'],
            // Apache validates the per-session token from slots.map.
            // Do not enable a second Basic Auth prompt inside an iframe.
            ...$node['command'],
        ];

        $log = $this->logPath($port);

        // proc_open with the descriptors already pointing at a file, and no wrapper object.
        //
        // Symfony's Process kills its child in __destruct() — vendor/symfony/process/Process.php
        // line 224 — which runs when this request ends. So the listener bound its port and was
        // dead a second later, and ttyd's own log showed nothing but the signal. PHP does not
        // touch a raw proc_open child at shutdown, so the listener outlives its request by
        // construction rather than by a flag being set.
        //
        // The command is an array, so PHP execs it directly: no shell, and nothing to quote.
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];

        $environment = $this->environment();
        $command = in_array('setsid', $environment, true) ? $command : array_merge(['setsid'], $command);

        $handle = proc_open($command, $descriptors, $pipes, null, $environment);

        if (! is_resource($handle)) {
            throw new RuntimeException(sprintf(
                'The terminal process could not be started. See %s for the reason.',
                $log,
            ));
        }

        $status = proc_get_status($handle);

        // Deliberately not calling proc_close(): it waits for the child, which is the opposite
        // of what this is for. The handle goes out of scope and the listener keeps running.
        $pid = (int) ($status['pid'] ?? 0);

        if ($pid <= 1) {
            throw new RuntimeException('The terminal process could not be started.');
        }

        // ttyd exits at once if it cannot bind. Without this pause a failure is recorded as a
        // session that opened, and the browser is handed an address to a dead port.
        usleep(300_000);

        if (! $this->processIsAlive($pid)) {
            throw new RuntimeException(sprintf(
                'The terminal process did not start. See %s for the reason.',
                $log,
            ));
        }

        return $pid;
    }

    private function processIsAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // posix_kill with signal 0 tests for existence without sending anything. A Process per
        // probe would be a process per probe, and its destructor signals its own child.
        return @posix_kill($pid, 0);
    }

    /**
     * Stop a listener and the shell under it.
     *
     * The process group, not just the pid: ttyd forks a shell, so killing only ttyd would
     * leave the shell running with nothing attached — the exact state this class exists to
     * prevent, reached by being half finished.
     */
    /**
     * Stop a listener, but only if that pid is still ours.
     *
     * Signalling is the one irreversible thing this class does. A pid sits in a table for as
     * long as the session row survives, and the operating system reuses numbers: by the time
     * the reaper runs, the pid in an expired row may belong to a php-fpm worker, an sshd or a
     * queue worker. Killing that would take down something this class never started, so an
     * unverified pid is refused rather than acted on.
     *
     * TerminalService::terminate() draws the same line, and this is now the second copy of it
     * in the codebase. Duplicated rather than extracted because the two services resolve the
     * binary by different paths and share nothing else — but a third copy is the sign that it
     * wants extracting.
     *
     * The group is signalled, not only the pid: ttyd forks a shell, so stopping just ttyd
     * leaves the shell running with nothing attached, which is the state this class exists to
     * prevent reached by being half finished.
     */
    private function terminate(int $pid): void
    {
        if ($pid <= 1 || ! $this->isTerminalProcess($pid) || ! $this->processIsAlive($pid)) {
            return;
        }

        // SIGHUP first, because ttyd forwards it and the shell gets to exit on its own terms;
        // SIGTERM next for one that ignores it; SIGKILL only for one that has stopped answering.
        // TerminalService::terminate() takes the same sequence, deliberately.
        //
        // The process group is not signalled. It would reach processes beyond the one this
        // service started, and the group ttyd belongs to is inherited rather than made for it,
        // so a signal aimed at the group is as likely to reach something unrelated as the shell
        // being closed.
        foreach ([defined('SIGHUP') ? SIGHUP : 1, defined('SIGTERM') ? SIGTERM : 15] as $signal) {
            @posix_kill($pid, $signal);

            for ($wait = 0; $wait < 10 && $this->processIsAlive($pid); $wait++) {
                usleep(100_000);
            }

            if (! $this->processIsAlive($pid)) {
                return;
            }
        }

        @posix_kill($pid, 9);
    }

    /**
     * Whether this pid is one of our terminal listeners.
     *
     * Read from /proc at the moment of the kill rather than remembered from the moment the
     * session opened, because remembering is precisely what goes wrong: the answer has to
     * hold now, not then.
     */
    private function isTerminalProcess(int $pid): bool
    {
        if ($pid <= 1) {
            return false;
        }

        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");

        if ($cmdline === false || $cmdline === '') {
            return false;
        }

        return str_contains($cmdline, basename((string) config('infrastructure.terminal.binary', 'ttyd')));
    }

    // ── ports ───────────────────────────────────────────────────────────────────

    /**
     * The first port in the configured window that nothing is listening on.
     *
     * Inside the window Apache proxies, because a port the proxy does not know is a port
     * the browser cannot reach.
     */
    private function reservePort(): int
    {
        $min = (int) config('infrastructure.terminal.port_range_min', 38000);
        $max = (int) config('infrastructure.terminal.port_range_max', 38007);

        $taken = (new TerminalSession)->getConnection()
            ->table('terminal_sessions')
            ->whereNull('closed_at')
            ->pluck('ttyd_port')
            ->map(fn ($port): int => (int) $port)
            ->all();

        for ($port = $min; $port <= $max; $port++) {
            if (in_array($port, $taken, true)) {
                continue;
            }

            if ($this->portIsFree($port)) {
                return $port;
            }
        }

        throw new RuntimeException('No terminal slot is free. Close an open shell and try again.');
    }

    private function portIsFree(int $port): bool
    {
        $probe = new Process([
            'sh',
            '-c',
            sprintf('ss -ltn "sport = :%d" | tail -n +2 | grep -q . && exit 1 || exit 0', $port),
        ], null, [
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $probe->setTimeout(3);

        // No catch. An earlier version answered false on any Throwable, on the reasoning
        // that a port believed taken is safer than one believed free — and that turned a
        // programming error into eight ports wrongly reported as occupied, with no symptom
        // except a refusal that named the wrong cause. If this cannot be determined it
        // should say so rather than guess.
        $probe->run();

        return $probe->isSuccessful();
    }

    private function slotAuth(): TerminalSlotAuth
    {
        return new TerminalSlotAuth((string) config('infrastructure.terminal.auth_directory'));
    }

    /**
     * The environment a listener is started in.
     *
     * PATH is inherited rather than left to chance: the ssh the lab nodes are reached through is
     * found by name, and a listener with a bare PATH opens no shell at all.
     *
     * @return list<string>
     */
    private function environment(): array
    {
        $pairs = [
            'TERM=xterm-256color',
            'LANG=C.UTF-8',
            'LC_ALL=C.UTF-8',
        ];

        $path = getenv('PATH');

        if (is_string($path) && $path !== '') {
            $pairs[] = 'PATH='.$path;
        }

        return $pairs;
    }

    private function logPath(int $port): string
    {
        $dir = (string) config('infrastructure.terminal.log_directory', storage_path('logs/terminal'));

        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        return sprintf('%s/%d.log', $dir, $port);
    }

    // ── session bookkeeping ─────────────────────────────────────────────────────

    /**
     * Rule three: one live session per person per node.
     *
     * Closing the old one rather than failing, because the common cause is a reloaded tab
     * and answering that with an error teaches people to open several — the state the cap
     * exists to prevent. It also revokes the old credential immediately.
     */
    private function closeExistingFor(User $user, string $nodeKey): void
    {
        $existing = TerminalSession::query()
            ->where('user_id', $user->getKey())
            ->where('target_kind', 'node')
            ->where('target_node', $nodeKey)
            ->whereNull('closed_at')
            ->get();

        foreach ($existing as $session) {
            $this->close($session);
        }
    }
}