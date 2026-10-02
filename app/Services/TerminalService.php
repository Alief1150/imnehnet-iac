<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Models\User;
use App\Support\EnvironmentWorkspace;
use App\Support\TerminalSlotGrants;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Short-lived web terminal sessions, backed by ttyd on the management plane.
 *
 * Four rules are enforced here rather than trusted to callers.
 *
 * The container comes from the database. A request carries an environment id;
 * everything that decides which shell opens — container name, remote, user
 * inside the container, port — is read from the inventory. Nothing the browser
 * can influence reaches the command line.
 *
 * The listener is loopback only. ttyd serves a full shell to anyone who reaches
 * it, so it is bound to 127.0.0.1 and additionally given a per-session
 * credential. Neither is the browser-facing control: the browser never gets the
 * port or the credential unless a relay decides to pass them on.
 *
 * The shell runs as the student user inside the container. Its uid is resolved
 * from the container rather than assumed, because a hardcoded uid that no longer
 * matches would either fail obscurely or, worse, resolve to root and quietly
 * hand over a root shell. When the user is absent the session is refused and
 * says so, because "no terminal" is a true answer and a root shell is not.
 *
 * The reference container is refused. It is protected from lifecycle changes
 * because it is the baseline the other environments are compared against, and a
 * shell would be a way to change it without going through that protection.
 */
class TerminalService
{
    /**
     * Length of the per-session credential, in bytes before hex encoding.
     *
     * Long enough that guessing is not a strategy, short enough that a leaked
     * value is scoped to one short-lived session.
     */
    private const CREDENTIAL_BYTES = 24;

    public function __construct(
        private readonly string $binary,
        private readonly string $shellUser,
        private readonly string $incusRemote,
        private readonly int $sessionTtlMinutes,
        private readonly int $maxSessionsPerUser,
        private readonly array $portRange,
        private readonly string $logDirectory,
        private readonly int $probeTimeoutSeconds,
        private readonly TerminalSlotGrants $grants,
        private readonly ?ActivityLogService $activity = null,
    ) {}

    public static function fromConfig(?Container $app = null): self
    {
        $app ??= app();

        return new self(
            binary: (string) config('infrastructure.terminal.binary'),
            shellUser: (string) config('infrastructure.terminal.shell_user'),
            incusRemote: (string) config('infrastructure.incus_remote'),
            sessionTtlMinutes: (int) config('infrastructure.terminal.session_ttl_minutes'),
            maxSessionsPerUser: (int) config('infrastructure.terminal.max_sessions_per_user'),
            portRange: [
                (int) config('infrastructure.terminal.port_range_min'),
                (int) config('infrastructure.terminal.port_range_max'),
            ],
            logDirectory: (string) config('infrastructure.terminal.log_directory'),
            probeTimeoutSeconds: (int) config('infrastructure.probe_timeout_seconds', 3),
            grants: new TerminalSlotGrants(
                (string) config('infrastructure.terminal.slot_map_file'),
            ),
            activity: $app->make(ActivityLogService::class),
        );
    }

    /**
     * Open a session, or hand back the one this user already holds for this
     * environment. Reconnecting to an existing session is deliberate: the user
     * who clicks Reconnect after a page refresh should get their shell back
     * rather than a second ttyd competing for the same container.
     */
    public function open(User $user, LabEnvironment $environment): TerminalSession
    {
        $this->assertOpenable($environment);

        $existing = TerminalSession::query()
            ->where('user_id', $user->id)
            ->where('lab_environment_id', $environment->id)
            ->usable()
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (TerminalSession $session): bool => $this->isConnected($session));

        if ($existing instanceof TerminalSession) {
            return $existing;
        }

        $shellUid = $this->resolveShellUid($environment->container_name);

        if ($shellUid === null) {
            throw new RuntimeException(sprintf(
                'The "%s" user does not exist in container "%s", so no shell can be opened as a student. Bootstrap the environment first.',
                $this->shellUser,
                $environment->container_name,
            ));
        }

        $this->enforceSessionLimit($user);

        $port = $this->reservePort();
        $credential = bin2hex(random_bytes(self::CREDENTIAL_BYTES));

        $pid = $this->spawn($environment->container_name, $shellUid, $port, $credential);

        $session = TerminalSession::query()->create([
            'user_id' => $user->id,
            'lab_environment_id' => $environment->id,
            'container_name' => $environment->container_name,
            'pid' => (string) $pid,
            'ttyd_port' => (string) $port,
            'credential' => $credential,
            'shell_uid' => $shellUid,
            'shell_user' => $this->shellUser,
            'expires_at' => now()->addMinutes($this->sessionTtlMinutes),
        ]);

        // The grant is written last, once the session row and the process both
        // exist. Writing it first would let a browser reach a slot whose ttyd
        // has not bound yet, which presents as a terminal that loads and then
        // sits there empty.
        $this->grants->grant($port, $credential);

        // Recorded after the grant exists, so the log never claims a shell was
        // opened before the browser could actually have reached one.
        $this->activity?->record(
            actor: $user,
            action: ActivityAction::TerminalOpened,
            resource: $session,
            status: 'completed',
            description: sprintf(
                'Opened a %s shell on %s for session %s.',
                $this->shellUser,
                (string) $environment->container_name,
                (string) $session->id,
            ),
            metadata: [
                'container_name' => (string) $environment->container_name,
                'workspace' => (string) $environment->tofu_workspace,
                'runtime_status' => $environment->runtime_status->value,
            ],
        );

        return $session;
    }

    /**
     * End a session and kill the process behind it.
     *
     * The row is marked closed first so that a failure to kill is visible as a
     * closed session with a live process rather than as a usable session that
     * silently stopped working.
     */
    public function close(TerminalSession $session): void
    {
        if ($session->closed_at === null) {
            $session->forceFill(['closed_at' => now()])->save();
        }

        $this->terminate($session);

        // Revoked after the process is gone, so the window in which the slot is
        // granted but dead is smaller than the window in which it is withdrawn
        // but still reachable.
        if ($session->ttyd_port !== null && is_numeric($session->ttyd_port)) {
            $this->grants->revoke((int) $session->ttyd_port);
        }
    }

    /**
     * Kill every session past its expiry. Returns how many were ended, so the
     * caller can report that the reaper ran rather than that it did nothing.
     */
    public function reapExpired(): int
    {
        $reaped = 0;

        TerminalSession::query()
            ->where(fn ($query) => $query->whereNull('closed_at')->where('expires_at', '<=', now()))
            ->orWhere(fn ($query) => $query->whereNull('closed_at')->whereNotNull('pid'))
            ->get()
            ->each(function (TerminalSession $session) use (&$reaped): void {
                $expired = $session->hasExpired();
                $stranded = ! $this->isConnected($session) && ! $session->isClosed();

                if ($expired || $stranded) {
                    $this->close($session);
                    $reaped++;
                }
            });

        return $reaped;
    }

    /**
     * @throws RuntimeException when the environment cannot host a terminal
     */
    private function assertOpenable(LabEnvironment $environment): void
    {
        if (! $environment->terminal_enabled) {
            throw new RuntimeException(sprintf(
                'The terminal is not enabled for environment "%s".',
                $environment->container_name,
            ));
        }

        if (EnvironmentWorkspace::isProtected($environment->tofu_workspace, $environment->container_name)) {
            throw new RuntimeException(sprintf(
                'Container "%s" is the protected reference environment and is never opened for a terminal.',
                $environment->container_name,
            ));
        }

        // A stopped container has no shell to give. Saying so is better than
        // spawning a ttyd that fails to connect to something that is not running.
        if (! $environment->runtime_status->isRunning()) {
            throw new RuntimeException(sprintf(
                'Container "%s" is %s, not running, so it cannot accept a terminal.',
                $environment->container_name,
                $environment->runtime_status->label(),
            ));
        }
    }

    /**
     * Look up the student user's uid inside the container.
     *
     * Returns null rather than a default when the user is missing. Every caller
     * treats null as "refuse the session", because the alternative guesses.
     */
    private function resolveShellUid(string $containerName): ?int
    {
        EnvironmentWorkspace::assertValidContainerName($containerName);

        $process = new Process([
            (string) config('infrastructure.incus_binary', 'incus'),
            'exec',
            $this->incusRemote.$containerName,
            '--',
            'id',
            '-u',
            $this->shellUser,
        ]);
        $process->setTimeout($this->probeTimeoutSeconds);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $uid = trim($process->getOutput());

        return ctype_digit($uid) && (int) $uid > 0 ? (int) $uid : null;
    }

    /**
     * The address a browser uses to reach this session's terminal.
     *
     * The token travels in the query string rather than in the path so that
     * Apache's per-slot ProxyPass can forward it untouched, and rather than in
     * the URL's userinfo section because browsers refuse credentials embedded
     * in a subresource. It is a bearer value for one leased slot for one short
     * session, and it stops being accepted the moment the session closes.
     */
    public function browserUrl(TerminalSession $session): ?string
    {
        if (! $session->isUsable() || $session->ttyd_port === null || $session->credential === null) {
            return null;
        }

        if (! config('infrastructure.terminal.relay_enabled')) {
            return null;
        }

        return sprintf(
            '/terminal/%s/?t=%s',
            $session->ttyd_port,
            $session->credential,
        );
    }

    /**
     * Whether a session's terminal is genuinely connected right now.
     *
     * Two things have to hold: the process must answer, and it must be the
     * terminal we started rather than some process that later reused the pid.
     * A pid on its own is a claim, and a session row can outlive its process,
     * so a stored pid alone would let the page report a terminal that is not
     * there.
     */
    public function isConnected(TerminalSession $session): bool
    {
        if (! $session->processIsAlive()) {
            return false;
        }

        return $this->isTerminalProcess((int) $session->pid);
    }

    /**
     * Close this user's oldest sessions beyond the allowance, so a forgotten
     * tab does not accumulate shells against the same environment.
     */
    private function enforceSessionLimit(User $user): void
    {
        $live = TerminalSession::query()
            ->where('user_id', $user->id)
            ->usable()
            ->orderByDesc('created_at')
            ->get();

        $live->slice($this->maxSessionsPerUser)->each(
            fn (TerminalSession $session) => $this->close($session),
        );
    }

    /**
     * Start ttyd and return its pid.
     *
     * Output is disabled rather than piped: this process outlives the request,
     * and a pipe nobody drains would fill and block the shell.
     */
    private function spawn(string $containerName, int $shellUid, int $port, string $credential): int
    {
        $command = [
            $this->binary,
            '-i', '127.0.0.1',
            '-p', (string) $port,
            '-W',
            '-t', 'titleFixed='.$this->shellUser.'@'.$containerName,
            '-c', 'imnehnet:'.$credential,
            'incus',
            'exec',
            $this->incusRemote.$containerName,
            '--user', (string) $shellUid,
            '--',
            'bash',
            '-i',
        ];

        $process = new Process($command, null, [
            'TERM' => 'xterm-256color',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);
        $process->setTimeout(null);
        // Output goes to a file rather than a pipe. This process outlives the
        // request, and a pipe nobody drains would fill and block the shell. A
        // file also keeps the reason a session refused to open, which is the
        // difference between a diagnosable failure and a blank terminal.
        $this->prepareLogDirectory();
        $process->setOutputFile($this->logPath($port));

        $process->start();

        $pid = $process->getPid();

        if (! is_int($pid)) {
            throw new RuntimeException('The terminal process could not be started.');
        }

        // ttyd exits immediately when it cannot bind. Catching that here turns a
        // silent failure into a refused session instead of a blank terminal.
        usleep(250_000);

        if (! $this->isTerminalProcess($pid)) {
            throw new RuntimeException(sprintf(
                'The terminal process did not start. See %s for the reason.',
                $this->logPath($port),
            ));
        }

        return $pid;
    }

    private function processIsAlive(int $pid): bool
    {
        return @posix_kill($pid, 0);
    }

    /**
     * Whether a pid belongs to a terminal this portal started.
     *
     * Signalling is the one irreversible thing this service does, so the pid is
     * checked against the configured binary before it is touched. Without this
     * a stale or altered pid in the table would make the portal kill whatever
     * process the operating system has since given that number, which could be
     * the queue worker, the web server, or anything else on the host.
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

        return str_contains($cmdline, $this->binary);
    }

    private function terminate(TerminalSession $session): void
    {
        if ($session->pid === null || ! is_numeric($session->pid)) {
            return;
        }

        $pid = (int) $session->pid;

        // Refused rather than attempted: an unverifiable pid is not ours to kill.
        if (! $this->isTerminalProcess($pid)) {
            return;
        }

        if (! $this->processIsAlive($pid)) {
            return;
        }

        // SIGHUP rather than SIGKILL: ttyd forwards it, so the shell gets to
        // exit on its own terms. SIGKILL is the fallback if it does not.
        @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);

        for ($attempt = 0; $attempt < 20 && $this->processIsAlive($pid); $attempt++) {
            usleep(100_000);
        }

        if ($this->processIsAlive($pid)) {
            @posix_kill($pid, 9);
        }
    }

    /**
     * Find a free loopback port inside the configured range.
     *
     * The socket is closed before ttyd binds it, so this is advisory rather
     * than a guarantee. The alternative, letting ttyd choose, means learning the
     * port from its log, which is worse: a port nobody checked can already be in
     * use by something that does not log it.
     */
    private function reservePort(): int
    {
        [$min, $max] = $this->portRange;

        for ($port = $min; $port <= $max; $port++) {
            $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);

            if ($socket === false) {
                continue;
            }

            fclose($socket);

            $inUse = TerminalSession::query()
                ->usable()
                ->where('ttyd_port', (string) $port)
                ->exists();

            if (! $inUse) {
                return $port;
            }
        }

        throw new RuntimeException(sprintf(
            'No free terminal port between %d and %d. Sessions may need reaping.',
            $min,
            $max,
        ));
    }

    private function prepareLogDirectory(): void
    {
        if (! is_dir($this->logDirectory)) {
            @mkdir($this->logDirectory, 0o775, true);
        }
    }

    private function logPath(int $port): string
    {
        return $this->logDirectory.'/ttyd-'.$port.'.log';
    }
}
