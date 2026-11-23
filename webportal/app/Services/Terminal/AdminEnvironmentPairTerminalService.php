<?php

namespace App\Services\Terminal;

use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Models\User;
use App\Support\TerminalEmbedTicket;
use App\Support\TerminalSlotAuth;
use RuntimeException;
use Throwable;

final class AdminEnvironmentPairTerminalService
{
    public const PURPOSE = 'admin_pair';

    public function __construct(
        private readonly AdminEnvironmentPairResolver $resolver,
    ) {}

    /**
     * Open both verified Incus containers for one administrator.
     *
     * If either new terminal fails, both sessions for this pair
     * are closed. Student and host sessions are never selected.
     *
     * @return array{attacker: TerminalSession, victim: TerminalSession}
     */
    public function openPair(User $admin, int $labId): array
    {
        if (! config('infrastructure.terminal.browser_access_enabled')) {
            throw new RuntimeException('Browser terminal access is disabled.');
        }

        $pair = $this->resolver->resolve($admin, $labId);

        $existing = $this->ownedSessions($admin, $labId);

        $attacker = $existing->first(
            fn (TerminalSession $s): bool =>
                (int) $s->lab_environment_id === (int) $pair['attacker']->id
                && $this->connected($s)
        );

        $victim = $existing->first(
            fn (TerminalSession $s): bool =>
                (int) $s->lab_environment_id === (int) $pair['victim']->id
                && $this->connected($s)
        );

        if ($attacker && $victim) {
            return ['attacker' => $attacker, 'victim' => $victim];
        }

        // A partial pair is not reusable. Start from a clean state.
        $this->closeAll($admin, $labId);

        try {
            $attacker = $this->start($admin, $pair['attacker'], $labId);
            $victim = $this->start($admin, $pair['victim'], $labId);

            return ['attacker' => $attacker, 'victim' => $victim];
        } catch (Throwable $exception) {
            try {
                $this->closeAll($admin, $labId);
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }

            throw $exception;
        }
    }

    /**
     * Close only this admin's terminal sessions for this lab.
     * Does not require either container to be running.
     */
    public function closeAll(User $admin, int $labId): void
    {
        $this->authorize($admin);

        if ($labId <= 0) {
            throw new RuntimeException('Invalid lab session.');
        }

        $errors = [];

        foreach ($this->ownedSessions($admin, $labId) as $session) {
            try {
                $this->closeSession($session);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                'Some terminal sessions could not be closed: '.
                implode('; ', $errors)
            );
        }
    }


    /**
     * IMNEHNET_ADMIN_DELETE_PAIR_V8
     * Internal lifecycle cleanup. Scoped strictly by purpose and LabSession.
     * Does not require an interactive administrator to remain logged in.
     */
    public function closeLabSessions(int $labId): int
    {
        if ($labId <= 0) {
            throw new RuntimeException('Invalid lab ID.');
        }

        $sessions = TerminalSession::query()
            ->where('purpose', self::PURPOSE)
            ->where('lab_session_id', $labId)
            ->whereNull('closed_at')
            ->get();

        $count = 0;

        foreach ($sessions as $session) {
            $this->closeSession($session);
            $count++;
        }

        return $count;
    }

    public function reapExpired(): int
    {
        $count = 0;

        $sessions = TerminalSession::query()
            ->where('purpose', self::PURPOSE)
            ->whereNull('closed_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($sessions as $session) {
            try {
                $this->closeSession($session);
                $count++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $count;
    }

    public function embedUrl(TerminalSession $session): ?string
    {
        if (
            $session->purpose !== self::PURPOSE
            || ! $this->connected($session)
            || ! config('infrastructure.terminal.browser_access_enabled')
        ) {
            return null;
        }

        return '/embed/'.(int) $session->ttyd_port.'/';
    }

    public function embedTicket(TerminalSession $session): ?string
    {
        if ($session->purpose !== self::PURPOSE || ! $this->connected($session)) {
            return null;
        }

        return $this->tickets()->tokenFor((int) $session->ttyd_port);
    }

    private function start(
        User $admin,
        LabEnvironment $environment,
        int $labId
    ): TerminalSession {
        $this->authorize($admin);

        $port = $this->availablePort();
        $credential = bin2hex(random_bytes(24));

        $binary = (string) config('infrastructure.terminal.binary', 'ttyd');
        $incus = (string) config('infrastructure.incus_binary', 'incus');

        $target = rtrim(
            (string) $environment->incus_remote,
            ':'
        ).':'.$environment->container_name;

        // Root is explicit: this is an authorised administrative
        // container shell, NEVER a student-owned shell.
        // The target is supplied by the validated database record.
        $command = [
            $binary,
            '-i', '127.0.0.1',
            '-p', (string) $port,
            '-W',
            '-t', 'titleFixed=admin@'.$environment->container_name,
            $incus,
            'exec', $target,
            '--user', '0',
            '--group', '0',
            '--cwd', '/',
            '--',
            'bash', '-l',
        ];

        $logDir = (string) config(
            'infrastructure.terminal.log_directory'
        );

        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        if (! is_dir($logDir) || ! is_writable($logDir)) {
            throw new RuntimeException('Terminal log directory unavailable.');
        }

        $log = rtrim($logDir, '/').'/ttyd-'.$port.'.log';

        $configDir = (string) env(
            'INCUS_CONF',
            '/var/lib/imnehnet-incus'
        );

        if (! is_readable($configDir.'/config.yml')) {
            throw new RuntimeException('Incus client configuration unavailable.');
        }

        $environmentVars = [
            'TERM' => 'xterm-256color',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'INCUS_CONF' => $configDir,
        ];

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];

        $handle = proc_open(
            array_merge(['setsid'], $command),
            $descriptors,
            $pipes,
            null,
            $environmentVars
        );

        if (! is_resource($handle)) {
            throw new RuntimeException('Could not start terminal process.');
        }

        $process = proc_get_status($handle);
        $pid = (int) ($process['pid'] ?? 0);

        if ($pid <= 1) {
            throw new RuntimeException('Terminal PID unavailable.');
        }

        $session = null;

        try {
            usleep(300000);

            if (! $this->matchesProcess($pid, $target)) {
                throw new RuntimeException(
                    'Terminal listener failed to start for '.$target
                );
            }

            $session = TerminalSession::query()->create([
                'user_id' => $admin->getKey(),
                'lab_environment_id' => $environment->getKey(),
                'lab_session_id' => $labId,
                'purpose' => self::PURPOSE,
                'container_name' => $environment->container_name,
                'pid' => (string) $pid,
                'ttyd_port' => (string) $port,
                'credential' => $credential,
                'shell_uid' => 0,
                'shell_user' => 'root',
                'expires_at' => now()->addMinutes(30),
            ]);

            $this->auth()->grant($port, $credential);
            $this->tickets()->issue($port);

            return $session;
        } catch (Throwable $exception) {
            // Failure cleanup is restricted to the newly spawned PID.
            try {
                $this->stopProcess($pid, $target);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            if ($session) {
                $session->forceFill(['closed_at' => now()])->save();
            }

            throw $exception;
        }
    }

    private function closeSession(TerminalSession $session): void
    {
        if ($session->purpose !== self::PURPOSE) {
            throw new RuntimeException('Refusing to close an unrelated session.');
        }

        $environment = LabEnvironment::query()
            ->find($session->lab_environment_id);

        $port = (int) $session->ttyd_port;

        // Do not revoke a slot that has since been leased to
        // another active session.
        $shared = TerminalSession::query()
            ->whereKeyNot($session->getKey())
            ->where('ttyd_port', (string) $port)
            ->usable()
            ->exists();

        if ($shared) {
            throw new RuntimeException('Terminal port belongs to another session.');
        }

        $this->tickets()->revoke($port);
        $this->auth()->revoke($port);

        if ($environment !== null && is_numeric($session->pid)) {
            $target = rtrim(
                (string) $environment->incus_remote,
                ':'
            ).':'.$session->container_name;

            $this->stopProcess((int) $session->pid, $target);
        }

        $session->forceFill(['closed_at' => now()])->save();
    }

    private function stopProcess(int $pid, string $target): void
    {
        if (! $this->matchesProcess($pid, $target)) {
            return;
        }

        // IMNEHNET_PAIR_FIX_V3
        @posix_kill($pid, defined('SIGTERM') ? constant('SIGTERM') : 15);

        for ($i = 0; $i < 20; $i++) {
            if (! $this->matchesProcess($pid, $target)) {
                return;
            }

            usleep(100000);
        }

        if ($this->matchesProcess($pid, $target)) {
            @posix_kill($pid, defined('SIGKILL') ? constant('SIGKILL') : 9);
        }
    }

    private function matchesProcess(int $pid, string $target): bool
    {
        if ($pid <= 1) {
            return false;
        }

        $raw = @file_get_contents("/proc/{$pid}/cmdline");

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $args = explode("\0", trim($raw, "\0"));
        $binary = basename((string) config(
            'infrastructure.terminal.binary',
            'ttyd'
        ));

        return basename((string) ($args[0] ?? '')) === $binary
            && in_array($target, $args, true)
            && in_array('exec', $args, true);
    }

    private function connected(TerminalSession $session): bool
    {
        if (! $session->isUsable() || ! is_numeric($session->pid)) {
            return false;
        }

        $environment = LabEnvironment::query()
            ->find($session->lab_environment_id);

        if (! $environment) {
            return false;
        }

        $target = rtrim(
            (string) $environment->incus_remote,
            ':'
        ).':'.$session->container_name;

        return $this->matchesProcess((int) $session->pid, $target);
    }

    private function availablePort(): int
    {
        $min = (int) config('infrastructure.terminal.port_range_min');
        $max = (int) config('infrastructure.terminal.port_range_max');

        for ($port = $min; $port <= $max; $port++) {
            $occupied = TerminalSession::query()
                ->where('ttyd_port', (string) $port)
                ->whereNull('closed_at')
                ->exists();

            if ($occupied) {
                continue;
            }

            $socket = @stream_socket_server(
                "tcp://127.0.0.1:{$port}",
                $errno,
                $error
            );

            if ($socket === false) {
                continue;
            }

            fclose($socket);

            return $port;
        }

        throw new RuntimeException('No free terminal port.');
    }

    private function ownedSessions(User $admin, int $labId)
    {
        return TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where('purpose', self::PURPOSE)
            ->where('lab_session_id', $labId)
            ->whereNull('closed_at')
            ->get();
    }

    private function authorize(User $admin): void
    {
        if (! $admin->isActive() || ! $admin->canOperateInfrastructure()) {
            throw new RuntimeException('Admin infrastructure access required.');
        }
    }

    private function auth(): TerminalSlotAuth
    {
        return new TerminalSlotAuth(
            (string) config('infrastructure.terminal.auth_directory')
        );
    }

    private function tickets(): TerminalEmbedTicket
    {
        return new TerminalEmbedTicket(
            (string) config('infrastructure.terminal.embed_directory')
        );
    }
}
