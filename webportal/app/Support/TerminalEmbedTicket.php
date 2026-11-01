<?php

namespace App\Support;

use RuntimeException;

/**
 * A leased terminal slot, expressed as a ticket file the web server can test for.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────
 *
 * The embedded console lives in an iframe, and an iframe cannot send an Authorization
 * header — there is no attribute for it. The direct /terminal/ path answers that with
 * a browser password prompt in its own tab. An embedded frame must instead prove the
 * slot is leased without any prompt, and Apache cannot ask the portal whether a token
 * is still valid once the browser upgrades to a WebSocket, because that leaves HTTP
 * and never returns a response the portal could sign.
 *
 * So the portal writes the answer down and Apache reads it, in the same shape as the
 * per-slot password files: one file per leased slot, named "<port>-<token>", and the
 * vhost tests for it on every request. Writing it opens the slot; deleting it closes
 * it immediately, with no reload.
 *
 * ── Why the ticket is not in the URL ─────────────────────────────────────────
 *
 * The ticket used to be sketched as part of the iframe address, and that puts a live
 * secret in the access log, in browser history and in the Referer of anything the
 * frame loads. Instead the browser carries it in a per-slot cookie
 * (imnehnet_slot_<port>), and the address names only the slot. A cookie is sent on
 * neither a log line nor a history entry, and the vhost still refuses any request
 * whose cookie does not name a ticket file that exists.
 *
 * ── Deleting rather than emptying ────────────────────────────────────────────
 *
 * revoke() removes the file, for the same reason the password files go rather than
 * being emptied: an empty file would still pass the vhost's lease test and then fail
 * every request behind it, so an unopened slot would look like a broken console
 * rather than an absent one.
 */
class TerminalEmbedTicket
{
    /** Hex characters of ticket per slot. 128 bits: guessed at nothing. */
    private const TOKEN_BYTES = 16;

    public function __construct(private readonly string $directory) {}

    /**
     * Lease a slot for embedding and return its ticket.
     *
     * Written atomically: Apache may test for the file at any moment, and the test is
     * existence, so a half-written file is a slot that answers while it should not.
     * The content is only bookkeeping for operators reading the directory; the vhost
     * never reads it.
     */
    public function issue(int $port): string
    {
        $this->ensureDirectory();

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $path = $this->path($port, $token);
        $temporary = $this->directory.'/.'.getmypid().'.'.$port.'.tmp';

        $body = sprintf("port=%d\nissued_at=%s\n", $port, date('c'));

        if (@file_put_contents($temporary, $body) === false) {
            throw new RuntimeException(sprintf('The terminal ticket file "%s" could not be written.', $temporary));
        }

        @chmod($temporary, 0640);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('The terminal ticket file "%s" could not be replaced.', $path));
        }

        return $token;
    }

    /**
     * The ticket currently leasing this slot, if there is one.
     *
     * Read from the directory rather than remembered, so what the page hands the
     * browser is the ticket Apache is actually testing for. Null when the slot is
     * not leased for embedding — which is also what keeps a frame from mounting
     * when there is nothing behind it.
     */
    public function tokenFor(int $port): ?string
    {
        $matches = glob($this->directory.'/'.$port.'-*') ?: [];

        foreach ($matches as $match) {
            if (is_file($match)) {
                $token = substr(basename($match), strlen((string) $port) + 1);

                if ($token !== false && $token !== '') {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * Close a slot for embedding.
     */
    public function revoke(int $port): void
    {
        foreach (glob($this->directory.'/'.$port.'-*') ?: [] as $match) {
            if (is_file($match)) {
                @unlink($match);
            }
        }
    }

    public function revokeAll(): void
    {
        foreach ($this->leasedPorts() as $port) {
            $this->revoke($port);
        }
    }

    /**
     * The slots currently leased for embedding.
     *
     * @return list<int>
     */
    public function leasedPorts(): array
    {
        $ports = [];

        foreach (glob($this->directory.'/*-*') ?: [] as $match) {
            if (! is_file($match)) {
                continue;
            }

            $head = explode('-', basename($match), 2)[0] ?? '';

            if (ctype_digit($head)) {
                $ports[] = (int) $head;
            }
        }

        sort($ports);

        return array_values(array_unique($ports));
    }

    public function path(int $port, string $token): string
    {
        return $this->directory.'/'.$port.'-'.$token;
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        // Group-writable is what lets either the portal or the host account write here;
        // the directory is setgid to the web server's group so files land readable by
        // Apache whichever of the two created them. The mode is octal: 2770 without the
        // leading zero is decimal 2770, which PHP happily applies as garbage permission
        // bits — owner left without read, which is exactly how a directory full of
        // tickets becomes a directory glob() cannot see. That happened once.
        if (! @mkdir($this->directory, 02770, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The terminal ticket directory "%s" could not be created.', $this->directory));
        }
    }
}
