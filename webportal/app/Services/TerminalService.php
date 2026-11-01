<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Jobs\ChangePairedLabPower;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\GuidedCtf\CtfEvidence;
use App\Support\EnvironmentWorkspace;
use App\Support\TerminalEmbedTicket;
use App\Support\TerminalSlotAuth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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
        private readonly TerminalSlotAuth $auth,
        private readonly ?ActivityLogService $activity = null,
    ) {}

    /**
     * Twelve letters, no digits or punctuation.
     * Uses a cryptographically secure random source.
     */
    private function generateReadableCredential(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $credential = '';

        for ($i = 0; $i < 12; $i++) {
            $credential .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $credential;
    }

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
            auth: new TerminalSlotAuth(
                (string) config('infrastructure.terminal.auth_directory'),
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

    // IMNEHNET_STUDENT_EMBED_V1
    public function open(User $user, LabEnvironment $environment, string $purpose = 'sandbox'): TerminalSession
    {
        if (! in_array($purpose, ['sandbox', 'guided_ctf'], true)) {
            throw new RuntimeException('Invalid terminal purpose.');
        }
        $service = clone $this;
        $service->sessionPurpose = $purpose;

        return $service->openScoped($user, $environment);
    }

    private string $sessionPurpose = 'sandbox';

    private ?int $pairedLabId = null;

    private ?string $evidenceEpoch = null;

    private function account(): string
    {
        return $this->sessionPurpose === 'guided_ctf' ? 'ctfstudent' : 'student';
    }

    private function openScoped(User $user, LabEnvironment $environment): TerminalSession
    {
        // IMNEHNET_SANDBOX_VICTIM_V2
        if (! $user->isActive() || (int) $environment->user_id !== (int) $user->getKey()) {
            throw new RuntimeException('This terminal is not assigned to your account.');
        }

        $pair = LabSession::query()
            ->where('user_id', $user->getKey())
            ->where(function ($query) use ($environment): void {
                $query->where('attacker_environment_id', $environment->getKey());

                if ($this->sessionPurpose === 'sandbox') {
                    $query->orWhere('victim_environment_id', $environment->getKey());
                }
            })
            ->latest('id')
            ->first();

        if ($pair === null) {
            throw new RuntimeException('No paired lab is assigned to this environment.');
        }

        return Cache::lock(
            'imnehnet:ctf:session-lifecycle:'.$pair->getKey(), 120
        )->block(10, function () use ($pair, $user, $environment): TerminalSession {
            $pair->refresh();
            $environment->refresh();
            $isAttacker = (int) $pair->attacker_environment_id === (int) $environment->getKey();
            $isVictim = $this->sessionPurpose === 'sandbox'
                && (int) $pair->victim_environment_id === (int) $environment->getKey();

            $expectedName = $isAttacker ? $pair->attacker_container : $pair->victim_container;
            $expectedRemote = $isAttacker ? $pair->attacker_remote : $pair->victim_remote;
            $configuredRemote = $isAttacker
                ? config('infrastructure.incus_attacker_remote', 'lab-atk')
                : config('infrastructure.incus_remote', 'lab-env');

            if ((! $isAttacker && ! $isVictim)
                || ! in_array($pair->status->value, ['READY', 'RUNNING', 'COMPLETED'], true)
                || (int) $pair->user_id !== (int) $user->getKey()
                || (int) $environment->user_id !== (int) $user->getKey()
                || $environment->container_name !== $expectedName
                || rtrim((string) $expectedRemote, ':') !== rtrim((string) $environment->incus_remote, ':')
                || rtrim((string) $environment->incus_remote, ':') !== rtrim((string) $configuredRemote, ':')
                || ($isAttacker && $environment->container_name !== 'attacker-user-'.$user->getKey())
                || $this->shellUser !== 'student') {
                throw new RuntimeException('Terminal target or lab lifecycle is not valid.');
            }

            $this->assertOpenable($environment, $isVictim);
            $this->pairedLabId = (int) $pair->id;
            $this->evidenceEpoch = $pair->ctf_epoch;
            if ($this->sessionPurpose === 'guided_ctf') {
                app(CtfEvidence::class)->assertHealthy($pair);
            }
            // IMNEHNET_CTF_EMBED_V2
            if (Cache::has('imnehnet:terminal-hold:'.$pair->id)) {
                throw new RuntimeException('Operasi Stop masih diproses.');
            }
            $existing = TerminalSession::query()->where('user_id', $user->id)
                ->where('lab_environment_id', $environment->id)
                ->where('purpose', $this->sessionPurpose)->where('lab_session_id', $pair->id)
                ->where('ctf_epoch', $pair->ctf_epoch)->usable()->latest('created_at')->get()
                ->first(fn (TerminalSession $row): bool => $this->isConnected($row));
            if ($existing && $this->embedTicketFor($existing) !== null) {
                if ($existing->expires_at->lt(now()->addMinutes(5))) {
                    $existing->forceFill(['expires_at' => now()->addMinutes(max(15, $this->sessionTtlMinutes))])->save();
                }

                return $existing;
            }
            $bootstrap = new Process([
                (string) config('infrastructure.incus_binary', 'incus'),
                'exec', rtrim($environment->incus_remote, ':').':'.$environment->container_name,
                '--', 'sh', '-ec',
                'if ! id student >/dev/null 2>&1; then useradd -m -U -s /bin/bash student; fi; '.
                'test "$(id -u student)" -gt 0; test "$(id -g student)" -gt 0; '.
                'test -x /bin/bash; test -d /home/student; '.
                'test "$(getent passwd student | cut -d: -f6)" = /home/student; '.
                'for g in $(id -nG student); do case "$g" in root|sudo|wheel|docker|lxd|incus-admin) exit 23;; esac; done',
            ]);
            $bootstrap->setEnv($this->environment());
            $bootstrap->setTimeout(30);
            $bootstrap->run();
            if (! $bootstrap->isSuccessful()) {
                throw new RuntimeException('Cannot prepare the student account: '.trim($bootstrap->getErrorOutput()));
            }

            return Cache::lock('imnehnet:student-terminal:allocate', 60)
                ->block(10, function () use ($user, $environment): TerminalSession {
                    $session = $this->openPrepared($user, $environment);
                    try {
                        if ($this->embedTickets()->tokenFor((int) $session->ttyd_port) === null) {
                            $this->embedTickets()->issue((int) $session->ttyd_port);
                        }
                    } catch (\Throwable $exception) {
                        $this->close($session);
                        throw $exception;
                    }

                    return $session;
                });
        });
    }

    public function queueOwnedLabPower(LabSession $lab, bool $start): void
    {
        Cache::lock('imnehnet:ctf:session-lifecycle:'.$lab->id, 120)
            ->block(5, function () use ($lab, $start): void {
                $lab->refresh();
                if (! auth()->user()?->isActive() || (int) $lab->user_id !== (int) auth()->id()) {
                    throw new RuntimeException('Lab ownership verification failed.');
                }
                $allowed = $start ? ['STOPPED'] : ['READY', 'RUNNING', 'COMPLETED'];
                if (! in_array($lab->status->value, $allowed, true)) {
                    throw new RuntimeException('Status lab tidak menerima operasi ini.');
                }
                $key = 'imnehnet:terminal-hold:'.$lab->id;
                if (Cache::has($key)) {
                    throw new RuntimeException('Operasi power sebelumnya masih diproses.');
                }
                Cache::put($key, true, 3600);
                try {
                    $this->closeOwnedLab($lab);
                    Queue::push(new ChangePairedLabPower((int) $lab->id, $start));
                } catch (\Throwable $error) {
                    Cache::forget($key);
                    throw $error;
                }
            });
    }

    public function closeOwnedLab(LabSession $lab): void
    {
        if (! auth()->user()?->isActive() || (int) $lab->user_id !== (int) auth()->id()) {
            throw new RuntimeException('Lab ownership verification failed.');
        }
        $ids = array_filter([$lab->attacker_environment_id, $lab->victim_environment_id]);
        foreach (TerminalSession::query()->where('user_id', $lab->user_id)
            ->whereIn('lab_environment_id', $ids)->whereNull('closed_at')->get() as $terminal) {
            $this->close($terminal);
        }
    }

    private function embedTickets(): TerminalEmbedTicket
    {
        return new TerminalEmbedTicket(
            (string) config('infrastructure.terminal.embed_directory')
        );
    }

    public function embedTicketFor(TerminalSession $session): ?string
    {
        return $session->isUsable() && $this->isConnected($session)
            ? $this->embedTickets()->tokenFor((int) $session->ttyd_port) : null;
    }

    private function openPrepared(User $user, LabEnvironment $environment): TerminalSession
    {
        $pairedVictim = false;

        if ($this->sessionPurpose === 'sandbox' && $this->pairedLabId !== null) {
            $pair = LabSession::query()->find($this->pairedLabId);
            $pairedVictim = $pair !== null
                && (int) $pair->user_id === (int) $user->getKey()
                && in_array($pair->status->value, ['READY', 'RUNNING', 'COMPLETED'], true)
                && (int) $pair->victim_environment_id === (int) $environment->getKey()
                && (int) $environment->user_id === (int) $user->getKey()
                && $pair->victim_container === $environment->container_name
                && rtrim((string) $pair->victim_remote, ':') === rtrim((string) $environment->incus_remote, ':')
                && rtrim((string) $environment->incus_remote, ':') === rtrim((string) config('infrastructure.incus_remote', 'lab-env'), ':');
        }

        $this->assertOpenable($environment, $pairedVictim);

        $existing = TerminalSession::query()
            ->where('user_id', $user->id)
            ->where('lab_environment_id', $environment->id)
            ->where('purpose', $this->sessionPurpose)
            ->where('lab_session_id', $this->pairedLabId)
            ->where('ctf_epoch', $this->evidenceEpoch)
            ->usable()
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (TerminalSession $session): bool => $this->isConnected($session));

        if ($existing instanceof TerminalSession) {
            return $existing;
        }

        $shellUid = $this->resolveShellUid($environment->container_name, $environment->incus_remote);

        if ($shellUid === null) {
            throw new RuntimeException(sprintf(
                'The "%s" user does not exist in container "%s", so no shell can be opened as a student. Bootstrap the environment first.',
                $this->account(),
                $environment->container_name,
            ));
        }

        $shellGid = $this->resolveShellGid(
            $environment->container_name,
            $environment->incus_remote
        );

        if ($shellGid === null || $shellGid === 0) {
            throw new RuntimeException(
                'Student primary group is invalid or privileged.'
            );
        }

        $this->enforceSessionLimit($user);

        $port = $this->reservePort();
        $this->embedTickets()->revoke($port);
        $credential = $this->generateReadableCredential();

        $pid = $this->spawn($environment->container_name, $environment->incus_remote, $shellUid, $shellGid, $port, $credential);

        $session = TerminalSession::query()->create([
            'user_id' => $user->id,
            'lab_environment_id' => $environment->id,
            'container_name' => $environment->container_name,
            'pid' => (string) $pid,
            'ttyd_port' => (string) $port,
            'credential' => $credential,
            'shell_uid' => $shellUid,
            'shell_user' => $this->account(),
            'purpose' => $this->sessionPurpose,
            'lab_session_id' => $this->pairedLabId,
            'ctf_epoch' => $this->evidenceEpoch,
            'expires_at' => now()->addMinutes($this->sessionTtlMinutes),
        ]);

        // The grant is written last, once the session row and the process both
        // exist. Writing it first would let a browser reach a slot whose ttyd
        // has not bound yet, which presents as a terminal that loads and then
        // sits there empty.
        $this->auth->grant($port, $credential);

        // Recorded after the grant exists, so the log never claims a shell was
        // opened before the browser could actually have reached one.
        $this->activity?->record(
            actor: $user,
            action: ActivityAction::TerminalOpened,
            resource: $session,
            status: 'completed',
            description: sprintf(
                'Opened a %s shell on %s for session %s.',
                $this->account(),
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

        if (TerminalSession::query()->where('ttyd_port', $session->ttyd_port)
            ->where('id', '!=', $session->id)->usable()->exists()) {
            return;
        }
        $this->embedTickets()->revoke((int) $session->ttyd_port);
        $this->terminate($session);

        // Revoked after the process is gone, so the window in which the slot is
        // granted but dead is smaller than the window in which it is withdrawn
        // but still reachable.
        if ($session->ttyd_port !== null && is_numeric($session->ttyd_port)) {
            $this->auth->revoke((int) $session->ttyd_port);
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

        // IMNEHNET_CLOSED_TTYD_REAPER_V1
        // Recover processes left running after their database sessions
        // were marked closed. Never interfere with a usable session.
        TerminalSession::query()
            ->whereNotNull('closed_at')
            ->whereNotNull('pid')
            ->get()
            ->each(function (TerminalSession $session) use (&$reaped): void {
                if ($session->pid === null || ! is_numeric($session->pid)) {
                    return;
                }

                $pid = (int) $session->pid;

                if (! $this->isTerminalProcess($pid)
                    || ! $this->processIsAlive($pid)) {
                    return;
                }

                if (TerminalSession::query()
                    ->where('ttyd_port', $session->ttyd_port)
                    ->where('id', '!=', $session->id)
                    ->usable()
                    ->exists()) {
                    return;
                }

                $this->close($session);

                if (! $this->processIsAlive($pid)) {
                    $reaped++;
                }
            });

        return $reaped;
    }

    /**
     * @throws RuntimeException when the environment cannot host a terminal
     */
    private function assertOpenable(
        LabEnvironment $environment,
        bool $authorizedSandboxVictim = false
    ): void {
        if (! $environment->terminal_enabled && ! $authorizedSandboxVictim) {
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
    private function resolveShellUid(string $containerName, string $remote): ?int
    {
        EnvironmentWorkspace::assertValidContainerName($containerName);

        $process = new Process([
            (string) config('infrastructure.incus_binary', 'incus'),
            'exec',
            rtrim($remote, ':').':'.$containerName,
            '--',
            'id',
            '-u',
            $this->account(),
        ]);
        $process->setEnv($this->environment());
        $process->setTimeout(max(10, $this->probeTimeoutSeconds));

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

    private function resolveShellGid(string $containerName, string $remote): ?int
    {
        EnvironmentWorkspace::assertValidContainerName($containerName);

        $process = new Process([
            (string) config('infrastructure.incus_binary', 'incus'),
            'exec',
            rtrim($remote, ':').':'.$containerName,
            '--',
            'id',
            '-g',
            $this->account(),
        ]);
        $process->setEnv($this->environment());
        $process->setTimeout(max(10, $this->probeTimeoutSeconds));

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $gid = trim($process->getOutput());

        return ctype_digit($gid) && (int) $gid > 0 ? (int) $gid : null;
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

        if (! config('infrastructure.terminal.browser_access_enabled')) {
            return null;
        }

        // No credential in the address. Apache checks this slot's per-session password file,
        // so the secret is presented as HTTP Basic rather than carried in a query string,
        // where it would be written to the access log and to browser history.
        if ($this->embedTicketFor($session) === null) {
            return null;
        }

        return sprintf('/embed/%s/', $session->ttyd_port);
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

        $live->slice(max(2, $this->maxSessionsPerUser) - 1)->each(
            fn (TerminalSession $session) => $this->close($session),
        );
    }

    /**
     * Start ttyd and return its pid.
     *
     * Output is disabled rather than piped: this process outlives the request,
     * and a pipe nobody drains would fill and block the shell.
     */
    private function spawn(string $containerName, string $remote, int $shellUid, int $shellGid, int $port, string $credential): int
    {
        $command = [
            $this->binary,
            '-i', '127.0.0.1',
            '-p', (string) $port,
            '-W',
            '-t', 'titleFixed='.$this->account().'@'.$containerName,
            (string) config('infrastructure.incus_binary', 'incus'),
            'exec',
            rtrim($remote, ':').':'.$containerName,
            '--user', (string) $shellUid,
            '--group', (string) $shellGid,
            '--cwd', '/home/'.$this->account().($this->sessionPurpose === 'guided_ctf' ? '/ctf' : ''),
            '--env', 'HOME=/home/'.$this->account(),
            '--env', 'USER='.$this->account(),
            '--env', 'LOGNAME='.$this->account(),
            '--',
            // bash -l, not bash -i — see AdminTerminalService::NODES. The ttyd build this
            // project is configured to use segfaults on an interactive shell command, so
            // this line was one of the two reasons a student terminal had never once
            // opened a shell.
            'setpriv', '--no-new-privs',
            'bash', '-l',
        ];

        $this->prepareLogDirectory();

        // Output goes to a file rather than a pipe. This process outlives the
        // request, and a pipe nobody drains would fill and block the shell. A
        // file also keeps the reason a session refused to open, which is the
        // difference between a diagnosable failure and a blank terminal.
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

        if (! $this->isTerminalProcess($pid)) {
            throw new RuntimeException(sprintf(
                'The terminal process did not start. See %s for the reason.',
                $log,
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
        $config = (string) env('INCUS_CONF', '/var/lib/imnehnet-incus');
        if ($config === '' || ! is_readable($config.'/config.yml')) {
            throw new RuntimeException('Incus client config is not readable by the web process.');
        }

        return [
            'TERM' => 'xterm-256color', 'LANG' => 'C.UTF-8', 'LC_ALL' => 'C.UTF-8',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'INCUS_CONF' => $config,
        ];
    }

    private function logPath(int $port): string
    {
        return $this->logDirectory.'/ttyd-'.$port.'.log';
    }
}
