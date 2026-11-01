<?php

namespace App\Support;

use RuntimeException;

/**
 * The web's end of the shell runner.
 *
 * ── The boundary this draws ───────────────────────────────────────────────────────────
 *
 * The web cannot start an administrative shell itself: that needs the host's own account, and
 * the web runs as the web server's user. Handing the web the ability to spawn processes as
 * that account would hand it the ability to sudo, so the request is not a command — it is a
 * choice from a fixed set, sent to a process that decides for itself whether it is allowed.
 *
 * So the client can say "open a shell on lab-env, slot 38003" and nothing else. It cannot name
 * a binary, a command, a path, a user or a port outside the window. Everything the runner
 * refuses is refused there rather than here, because a check the caller performs is a check the
 * caller can skip.
 *
 * ── Failure is reported, never papered over ───────────────────────────────────────────
 *
 * Every method throws rather than returning false. A terminal that cannot be opened and a
 * terminal the page says is open look identical to a reader, and only one of them is true.
 * When the runner is absent, the message says so and says how to start it, because "no
 * administrative shell runner is running" is an actionable sentence and "connection refused" is
 * not.
 */
class ShellRunner
{
    /** Seconds to wait for the runner to answer. Opening spawns and then waits for a bind. */
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly string $socket,
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('infrastructure.terminal.runner_socket'));
    }

    public function socket(): string
    {
        return $this->socket;
    }

    /**
     * Whether a runner is there, and whether it really is the account it must be.
     *
     * Checked with a round trip rather than by testing for the socket file: a socket can exist
     * and belong to a runner that is wedged, or to one left behind by a process that exited.
     * The reply carries the runner's own user, and a runner that is not running as iaas is
     * treated as absent — because a runner running as anything else would be starting shells
     * without the privileges that make them administrative, and reporting that as available
     * would be the exact failure this whole arrangement exists to prevent.
     */
    public function available(): bool
    {
        return $this->who() === 'iaas';
    }

    /**
     * The account the runner is running as, or null when there is no runner to ask.
     */
    public function who(): ?string
    {
        try {
            $reply = $this->send(['op' => 'ping']);
        } catch (RuntimeException) {
            return null;
        }

        return isset($reply['user']) ? (string) $reply['user'] : null;
    }

    /**
     * Start a shell on a node.
     *
     * @return array{pid: int, port: int}
     */
    public function open(string $node, int $port, string $logPath): array
    {
        $reply = $this->send(['op' => 'open', 'node' => $node, 'port' => $port, 'log' => $logPath]);

        return [
            'pid' => (int) ($reply['pid'] ?? 0),
            'port' => (int) ($reply['port'] ?? $port),
        ];
    }

    /**
     * Stop a shell this runner started.
     *
     * Refused by the runner for any pid it does not have in its record, which is the only
     * reason this can be called safely with a pid read out of a table.
     */
    public function close(int $pid): void
    {
        $this->send(['op' => 'close', 'pid' => $pid]);
    }

    public function isRunning(int $pid): bool
    {
        try {
            $reply = $this->send(['op' => 'status', 'pid' => $pid]);
        } catch (RuntimeException) {
            return false;
        }

        return (bool) ($reply['alive'] ?? false);
    }

    /**
     * One request, one reply.
     *
     * @throws RuntimeException when the runner cannot be reached or refuses.
     */
    private function send(array $request): array
    {
        $connection = @stream_socket_client(
            'unix://'.$this->socket,
            $errno,
            $errstr,
            self::TIMEOUT_SECONDS,
        );

        if (! is_resource($connection)) {
            throw new RuntimeException(sprintf(
                'No administrative shell runner is answering on %s. It runs as the host account '
                .'rather than as the web server, and it has to be started before a shell can '
                .'be opened: bash scripts/shell-supervisor.sh',
                $this->socket,
            ));
        }

        stream_set_timeout($connection, self::TIMEOUT_SECONDS);

        @fwrite($connection, json_encode($request, JSON_UNESCAPED_SLASHES).PHP_EOL);
        @fflush($connection);

        $line = fgets($connection, 8192);
        $meta = stream_get_meta_data($connection);
        @fclose($connection);

        if ($meta['timed_out']) {
            throw new RuntimeException('The shell runner did not answer in time.');
        }

        $reply = is_string($line) ? json_decode(trim($line), true) : null;

        if (! is_array($reply)) {
            throw new RuntimeException('The shell runner sent something that was not a reply.');
        }

        if (! ($reply['ok'] ?? false)) {
            throw new RuntimeException((string) ($reply['error'] ?? 'The shell runner refused the request.'));
        }

        return $reply;
    }
}