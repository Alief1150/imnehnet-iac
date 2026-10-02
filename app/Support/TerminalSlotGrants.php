<?php

namespace App\Support;

use RuntimeException;

/**
 * The file that tells Apache which terminal slot is leased, and to whom.
 *
 * Apache cannot ask the portal whether a token is valid during a request to a
 * terminal slot. A browser reaching a terminal upgrades to a WebSocket, which
 * leaves HTTP and never returns a response the portal could have signed, and
 * the same applies to the initial page load once the request is being proxied
 * rather than routed. So the portal writes the answer down and Apache reads it.
 *
 * The alternative, HTTP basic auth per slot, works too, but a WebSocket cannot
 * be loaded with an Authorization header from an iframe, so every terminal would
 * raise a password prompt. A token in the query string can be, because the
 * iframe's URL is what the portal hands over in the first place.
 *
 * This is a txt RewriteMap: one "key value" pair per line. The key is the port
 * and the value is the token issued for it, which means Apache can compare the
 * token in the request against the value it expects without ever seeing the
 * portal. A slot with no line in the file matches nothing, so an unleased slot
 * is refused rather than forwarded to a listener that may not even exist.
 *
 * The file is rewritten whole on every change rather than edited in place. A
 * partial write would leave Apache with a map it cannot parse, and the failure
 * would look like terminals being randomly refused. Writing to a temporary file
 * and renaming over it means Apache reads either the previous map or the new
 * one, never a half-written one.
 */
final class TerminalSlotGrants
{
    public function __construct(private readonly string $path) {}

    /**
     * Record that a slot is leased, with the token that opens it.
     */
    public function grant(int $port, string $token): void
    {
        $this->write($this->without($port) + [$port => $token]);
    }

    /**
     * Withdraw a slot. Called when a session closes and when the reaper ends an
     * expired one, so a port stops being reachable the moment it is free.
     */
    public function revoke(int $port): void
    {
        $this->write($this->without($port));
    }

    /**
     * Drop every grant. Used by the installer and by anything that needs to be
     * certain no token issued before a restart is still accepted.
     */
    public function revokeAll(): void
    {
        $this->write([]);
    }

    /**
     * The grants currently on disk, as port => token.
     *
     * A line that cannot be parsed is skipped rather than allowed to abort the
     * whole read. One malformed line must not take every working terminal with
     * it, and the port it names is simply refused, which is the safe direction.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            return [];
        }

        $grants = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // The delimiter is fixed rather than whitespace so a malformed line
            // with three parts is detectable instead of silently losing one.
            if (! preg_match('/^(\d{4,5}) ([0-9a-f]{32,64})$/', $line, $matches)) {
                continue;
            }

            $grants[(int) $matches[1]] = $matches[2];
        }

        return $grants;
    }

    /**
     * @return array<int, string>
     */
    private function without(int $port): array
    {
        $grants = $this->all();
        unset($grants[$port]);

        return $grants;
    }

    /**
     * @param  array<int, string>  $grants
     */
    private function write(array $grants): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'The terminal slot directory "%s" could not be created.',
                $directory,
            ));
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(sprintf(
                'The terminal slot directory "%s" is not writable, so no terminal can be granted. '
                .'Apache and the portal have to share access to it.',
                $directory,
            ));
        }

        ksort($grants);

        $lines = [
            '# IMNEHNET terminal slot grants. Written by the portal; read by Apache.',
            '# port token',
        ];

        foreach ($grants as $port => $token) {
            $lines[] = $port.' '.$token;
        }

        $contents = implode("\n", $lines)."\n";

        // Rename over the target rather than truncating it, so Apache never
        // reads a map that is only half written.
        $temporary = $this->path.'.'.getmypid().'.tmp';

        if (@file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException(sprintf('The terminal slot map "%s" could not be written.', $temporary));
        }

        @chmod($temporary, 0o640);

        if (! @rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('The terminal slot map "%s" could not be replaced.', $this->path));
        }
    }
}
