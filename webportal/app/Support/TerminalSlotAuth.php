<?php

namespace App\Support;

use RuntimeException;

/**
 * A leased terminal slot, expressed as a password file the web server can read.
 *
 * ── Why this exists ──────────────────────────────────────────────────────────────────
 *
 * The gate used to be a RewriteMap over a single "port token" file. It does not work, and the
 * bisect that established it is worth recording because two plausible repairs were tried first
 * and both were wrong:
 *
 *   RewriteCond %{imnehnet_slots:%1|NONE} =%2      -> always false, compared against nothing
 *   RewriteCond %{imnehnet_slots:%1|NONE} =%1      -> also always false, keyed by token
 *
 * The map is written "port token", so its key is the port and its value the token; a lookup
 * keyed by a backreference does not resolve, every lookup returned NONE, and every request was
 * refused — a correct token included. Removing only the map condition, with the capture
 * conditions untouched, answered 503 instead of 403, which places the fault on the lookup and
 * nowhere else.
 *
 * ── What replaces it ─────────────────────────────────────────────────────────────────
 *
 * One password file per slot, and Apache's Require valid-user reads it per request. Two
 * properties follow, and both are what a terminal needs and the map had neither of:
 *
 *   - the file's existence is the lease, so a slot opens the moment it is written;
 *   - deleting it closes the slot immediately, with no reload.
 *
 * The vhost's own documentation already described this design and why: Apache cannot ask the
 * portal whether a token is still valid once a browser upgrades to a WebSocket, because that
 * leaves HTTP and never returns a response the portal could sign. A file the portal writes and
 * the web server reads is the answer to that, and it is also why the credential does not have to
 * travel in the URL.
 *
 * ── Deleting rather than emptying ─────────────────────────────────────────────────────
 *
 * revoke() removes the file. An empty file would still pass the vhost's "is this slot leased"
 * test and then answer a password prompt that can never succeed, so an unopened slot would
 * look like a broken login rather than an absent one. Deleting gives a plain refusal.
 */
class TerminalSlotAuth
{
    /** The Basic-auth username. Fixed and arbitrary: it is a namespace, not an identity. */
    public const USERNAME = 'imnehnet';

    /**
     * bcrypt cost.
     *
     * 5 rather than the default 10 because this file is read on every request to a terminal,
     * and because the credential is 192 bits of random data — the work factor exists to slow
     * offline guessing, and there is nothing to guess at this strength.
     */
    private const COST = 5;

    public function __construct(private readonly string $directory) {}

    /**
     * Lease a slot with this credential.
     *
     * Written atomically: Apache may read the file at any moment, and a half-written password
     * file is a slot that exists but cannot be authenticated against.
     */
    public function grant(int $port, string $credential): void
    {
        $this->ensureDirectory();

        $hash = password_hash($credential, PASSWORD_BCRYPT, ['cost' => self::COST]);

        if (! is_string($hash)) {
            throw new RuntimeException('The terminal credential could not be hashed.');
        }

        $path = $this->path($port);
        $temporary = $path.'.'.getmypid().'.tmp';

        if (@file_put_contents($temporary, self::USERNAME.':'.$hash."\n") === false) {
            throw new RuntimeException(sprintf('The terminal password file "%s" could not be written.', $temporary));
        }

        // Group-readable is what lets Apache read it; the directory is setgid so the group is
        // the web server's even though the portal is the one writing.
        @chmod($temporary, 0640);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('The terminal password file "%s" could not be replaced.', $path));
        }
    }

    /**
     * Close a slot.
     *
     * The file goes, rather than being emptied — see the class comment.
     */
    public function revoke(int $port): void
    {
        $path = $this->path($port);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function revokeAll(): void
    {
        foreach ($this->leasedPorts() as $port) {
            $this->revoke($port);
        }
    }

    /**
     * The slots currently leased.
     *
     * @return list<int>
     */
    public function leasedPorts(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $ports = [];

        foreach ((array) glob($this->directory.'/*') as $file) {
            $port = basename((string) $file);

            if (ctype_digit($port)) {
                $ports[] = (int) $port;
            }
        }

        sort($ports);

        return $ports;
    }

    public function isLeased(int $port): bool
    {
        return is_file($this->path($port));
    }

    public function path(int $port): string
    {
        return $this->directory.'/'.$port;
    }

    /**
     * Refuse clearly when the directory cannot be used.
     *
     * A missing directory is created, because the portal should not need a human to provision
     * a directory it can create. A directory it cannot write is a different matter: that is a
     * deployment whose permissions are wrong, and silently leasing slots into a file Apache
     * cannot read would produce a page showing a shell that always answers 401.
     */
    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The terminal password directory "%s" could not be created.', $this->directory));
        }

        if (! is_writable($this->directory)) {
            throw new RuntimeException(sprintf(
                'The terminal password directory "%s" is not writable, so no terminal can be leased. '
                .'It has to be writable by the portal and readable by the web server.',
                $this->directory,
            ));
        }
    }
}