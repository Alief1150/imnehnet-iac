<?php

namespace App\Services\Terminal;

use App\Models\TerminalSession;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * They are not the same thing with a different argument. A student shell is a shell
 * inside a container they were handed, running as a uid resolved from that container, and
 * the whole point is that it can reach nothing else. This one is a shell on the host that
 * runs the portal, as an account that can sudo — it reaches everything by construction.
 * Sharing one class would mean every rule about the first had to be stated as an exception
 * to the second, and the day someone adds a method and forgets which half it belongs to is
 * the day a student gets a root shell.
 *
 * ── The five rules, all enforced here rather than at the call sites ────────────────
 *
 * 1. Only an elevated role. Checked against the role, not against a panel the caller
 *    happened to be rendering.
 * 2. ttyd binds loopback only, and carries a per-session credential. Anyone who reaches
 *    the port still needs the credential, and anyone who reaches the page still needs the
 *    session. Two locks, neither of which depends on the other.
 * 3. One session per user per node. A second open closes the first, so a credential that
 *    leaked from an abandoned tab stops working when the tab is closed rather than living
 *    until its expiry.
 * 4. Every open and close is recorded, before the shell exists and after it is gone. An
 *    audit written on success records nothing about the failures, which are the ones worth
 *    reading.
 * 5. The session is ephemeral. The record survives; the process does not. There is no
 *    path here that leaves a shell running on the host.
 *
 * ── On the shell's privileges ──────────────────────────────────────────────────────
 *
 * The brief is that this is an administrative console: the operator may sudo, and the
 * point is being able to act on the machine rather than observe it. That is granted
 * deliberately rather than as an oversight. What limits it is not the absence of privilege
 * but the four rules above — an elevated role, a loopback listener with a credential, a
 * one-session cap, and an audit trail.
 */
class AdminTerminalService
{
    /**
     * Audit actions, named so the two cannot drift apart.
     *
     * Plain strings rather than cases on ActivityAction, which is an enum of the
     * environment lifecycle. These are administration events and do not belong to it.
     */
    private const ACTION_OPEN = 'ADMIN_TERMINAL_OPEN';

    private const ACTION_CLOSE = 'ADMIN_TERMINAL_CLOSE';

    /** Bytes of credential per session. Long enough not to be guessed, short enough not to linger. */
    private const CREDENTIAL_BYTES = 24;

    /** How long a session may live if nobody closes it. */
    private const SESSION_MINUTES = 120;

    /**
     * The nodes that may be opened, and how to reach each one.
     *
     * The management host is the only one reached directly; the two compute planes are
     * reached over SSH as a named account on each. Stated here rather than discovered, so
     * that what this feature can possibly reach is readable in one place — a shell feature
     * whose targets are resolved at runtime from configuration is one whose reach nobody
     * can state.
     *
     * `command` is what ttyd runs. For the local node it is a login shell; for the others
     * it is ssh into a fixed account on a fixed address, so a name from a request never
     * becomes part of the command line.
     *
     * @var list<array{key: string, label: string, role: string, address: ?string, command: list<string>}>
     */
    private const NODES = [
        [
            'key' => 'iaas-mgmt',
            'label' => 'IAAS-MGMT',
            'role' => 'Management Plane',
            'address' => null,
            'command' => ['bash', '-i'],
        ],
        [
            'key' => 'lab-env',
            'label' => 'LAB-ENV',
            'role' => 'Lab Compute',
            'address' => 'lab@192.168.100.20',
            'command' => [
                'ssh',
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
     * @return list<array<string, mixed>>
     */
    public function availableNodes(): array
    {
        return array_map(fn (array $node): array => [
            'key' => $node['key'],
            'label' => $node['label'],
            'role' => $node['role'],
            'address' => $node['address'],
        ], self::NODES);
    }

    /**
     * Open a shell on a node.
     *
     * @throws RuntimeException when the person may not, or the node is unknown, or the
     *                           listener did not come up. Every one of those is a refusal
     *                           rather than a silent empty terminal.
     */
    public function open(User $user, string $nodeKey): TerminalSession
    {
        $this->assertMayOperate($user);

        $node = $this->node($nodeKey);

        // Before the shell exists, not after. A refused attempt is the one a reader of the
        // log needs, and a log written on success would not contain it.
        $this->activity->record(
            actor: $user,
            action: self::ACTION_OPEN,
            resource: null,
            status: 'requested',
            description: sprintf('Requested an administrative shell on %s.', $node['label']),
        );

        $this->closeExistingFor($user, $node['key']);

        $port = $this->reservePort();
        $credential = Str::random(self::CREDENTIAL_BYTES);

        $pid = $this->spawn($node, $port, $credential);

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

        $this->grant($port, $credential);

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
     * Close a session: kill the listener and drop its credential.
     */
    public function close(TerminalSession $session): void
    {
        $pid = (int) $session->pid;
        $port = (int) $session->ttyd_port;

        if ($pid > 0) {
            $this->terminate($pid);
        }

        $this->revoke($port);

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
     * A tab that is closed without logging out leaves the listener running. This is the
     * backstop for that, and it is the reason there is no code path in this class that
     * leaves a shell up: closing is not the only way out.
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
                // One stubborn session must not stop the rest from being closed.
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
     * Loopback port plus credential, never the port alone. A reader who has the URL and
     * not the credential gets a refusal from ttyd, which is the second lock.
     */
    public function browserUrl(TerminalSession $session): ?string
    {
        if ($session->closed_at !== null) {
            return null;
        }

        return sprintf('/terminal/%d/', (int) $session->ttyd_port);
    }

    public function isConnected(TerminalSession $session): bool
    {
        return $session->closed_at === null
            && (int) $session->pid > 0
            && $this->processIsAlive((int) $session->pid);
    }

    // ── the rules ────────────────────────────────────────────────────────────────

    /**
     * Rule one.
     *
     * Asked of the role on the account rather than of the panel serving the page. A panel
     * is a rendering concern; the authority to open a shell on the portal's own host does
     * not follow from being rendered inside one.
     *
     * @throws RuntimeException
     */
    private function assertMayOperate(User $user): void
    {
        if (! $user->role->canOperateInfrastructure()) {
            $this->activity->record(
                actor: $user,
                action: self::ACTION_OPEN,
                resource: null,
                status: 'failed',
                description: 'Refused: the role may not operate the infrastructure.',
            );

            throw new RuntimeException('You may not open an administrative shell.');
        }
    }

    /**
     * @return array{key: string, label: string, role: string, address: ?string, command: list<string>}
     *
     * @throws RuntimeException on a key that is not one of the three.
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

    /**
     * The account a shell lands in, for the record.
     */
    private function loginName(array $node): string
    {
        return $node['address'] ?? $this->localUser();
    }

    private function localUser(): string
    {
        return (string) (get_current_user() ?: 'iaas');
    }

    // ── the listener ─────────────────────────────────────────────────────────────

    /**
     * Start ttyd on loopback and return its pid.
     *
     * Rule two, in one line: `-i 127.0.0.1` and a per-session credential. Loopback means
     * nothing on the lab network can reach the port at all; the credential means that even
     * something already on this machine, if it reached it, still gets nothing.
     *
     * Output goes to a file and not a pipe. This process outlives the request, and a pipe
     * nobody drains fills and blocks the shell.
     */
    private function spawn(array $node, int $port, string $credential): int
    {
        $binary = (string) config('infrastructure.ttyd_binary', 'ttyd');

        $command = [
            $binary,
            '-i', '127.0.0.1',
            '-p', (string) $port,
            '-W',
            '-t', 'titleFixed='.$this->loginName($node).'@'.$node['key'],
            '-c', 'imnehnet:'.$credential,
            ...$node['command'],
        ];

        $process = new Process($command, null, [
            'TERM' => 'xterm-256color',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $process->setTimeout(null);
        $process->setOutputFile($this->logPath($port));
        $process->start();

        $pid = $process->getPid();

        if (! is_int($pid)) {
            throw new RuntimeException('The terminal process could not be started.');
        }

        // ttyd exits immediately when it cannot bind, or when the binary is missing.
        // Without this pause a missing ttyd would be recorded as a session that opened,
        // and the browser would be handed a URL to nothing.
        usleep(300_000);

        if (! $this->processIsAlive($pid)) {
            throw new RuntimeException(sprintf(
                'The terminal process did not start. See %s for the reason.',
                $this->logPath($port),
            ));
        }

        return $pid;
    }

    private function processIsAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        try {
            $result = Process::run(['kill', '-0', (string) $pid], 2);

            return $result->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Close a listener.
     *
     * The process group, not just the pid. ttyd forks a shell, so killing only ttyd would
     * leave the shell running with nothing attached to it — which is the exact state this
     * class exists to prevent, reached by being half-finished.
     */
    private function terminate(int $pid): void
    {
        foreach (['-' . $pid, (string) $pid] as $target) {
            try {
                Process::run(['kill', $target], 3);
            } catch (Throwable) {
                // Already gone, or never existed. Either is the state we wanted.
            }
        }
    }

    // ── ports ───────────────────────────────────────────────────────────────────

    /**
     * The first port in the configured window that nothing is listening on.
     *
     * Read from the same window Apache proxies, because a port the proxy does not know is
     * a port the browser cannot reach. Reserving one outside that window would produce a
     * session that opened and could not be displayed.
     */
    private function reservePort(): int
    {
        $min = (int) config('infrastructure.port_range_min', 38000);
        $max = (int) config('infrastructure.port_range_max', 38007);

        $taken = DB::connection('imnehnet')
            ->table('terminal_sessions')
            ->whereNull('closed_at')
            ->pluck('ttyd_port')
            ->map(fn ($p): int => (int) $p)
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
        try {
            $result = Process::run(
                ['sh', '-c', sprintf('ss -ltn "sport = :%d" | tail -n +2 | grep -q . && exit 1 || exit 0', $port)],
                3,
            );

            return $result->isSuccessful();
        } catch (Throwable) {
            // If it cannot be told, assume taken. A port believed free and already bound
            // fails to bind and the session is refused; a port believed taken is merely
            // unavailable, which is the safer of the two mistakes.
            return false;
        }
    }

    // ── credential storage ──────────────────────────────────────────────────────

    /**
     * Where the credential is kept, and the one thing that must be true of it.
     *
     * Apache reads this directory to proxy /terminal/<port>/ and ttyd checks the
     * credential against it. It is loopback-only and readable by the web user, which is why
     * the port is not the secret.
     */
    private function grantsPath(): string
    {
        return (string) config('infrastructure.terminal_grants_path', '/var/lib/imnehnet/terminal');
    }

    private function grant(int $port, string $credential): void
    {
        $path = $this->grantPath($port);

        @mkdir(dirname($path), 0770, true);

        file_put_contents($path, $credential);
        @chmod($path, 0640);
    }

    private function revoke(int $port): void
    {
        $path = $this->grantPath($port);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function grantPath(int $port): string
    {
        return $this->grantsPath().'/'.$port.'.grant';
    }

    private function logPath(int $port): string
    {
        $dir = (string) config('infrastructure.terminal_log_path', '/var/log/imnehnet/terminal');

        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        return sprintf('%s/%d.log', $dir, $port);
    }

    // ── session bookkeeping ─────────────────────────────────────────────────────

    /**
     * Rule three: one live session per person per node.
     *
     * A second open closes the first rather than failing. The reason to prefer that over
     * a refusal is that the common cause is a tab that was reloaded, and answering that
     * with an error teaches people to open several — which is the state the cap exists to
     * prevent. Closing the old one also revokes its credential immediately.
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