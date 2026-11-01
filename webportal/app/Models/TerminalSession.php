<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One authorised web terminal session against one environment.
 *
 * The row is the authority for what the session is allowed to open. The
 * browser is handed an identifier and nothing else: no container name, no
 * launch command, no port it is free to choose. The server resolves all three
 * from this row, so a request cannot talk the portal into a shell somewhere it
 * was not granted.
 *
 * Sessions are short-lived on purpose. A terminal is a shell, and a shell that
 * outlives the browser tab that opened it is a shell nobody is watching. The
 * expiry is enforced by killing the process, not by asking the browser to
 * forget, so closing a tab ends the session even when nobody logs out.
 *
 * @property string $id
 * @property int $user_id
 * @property int $lab_environment_id
 * @property string $container_name
 * @property string|null $pid
 * @property string|null $ttyd_port
 * @property string|null $credential
 * @property int|null $shell_uid
 * @property string|null $shell_user
 * @property Carbon $expires_at
 * @property Carbon|null $closed_at
 */
class TerminalSession extends Model
{
    use HasUuids;

    protected $table = 'terminal_sessions';

    /**
     * Sessions live beside the environment they belong to, because the two are
     * read together and a session pointing at a container that no longer exists
     * has to be reachable in order to be cleaned up.
     */
    public function getConnectionName(): ?string
    {
        return (string) config('infrastructure.environment_connection', 'imnehnet');
    }

    /** @var list<string> */
    protected $guarded = [];

    /**
     * The credential is encrypted rather than hashed.
     *
     * A hash would prove the value the browser presents is the one we issued,
     * which sounds sufficient, but the session also has to be reconnected to a
     * still-running ttyd, and that ttyd needs the plaintext. Since the value
     * never leaves the management plane and is destroyed with the session,
     * reversible encryption is the honest choice.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credential' => 'encrypted',
            'shell_uid' => 'integer',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LabEnvironment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(LabEnvironment::class, 'lab_environment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A session is live only while it is both unclosed and unexpired. Both are
     * needed: closed_at records an explicit close, and expires_at stops a
     * forgotten session outliving its grant even if the reaper has not run yet.
     *
     * @param  Builder<TerminalSession>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('closed_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->closed_at === null && $this->expires_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Whether the process behind this session is still alive.
     *
     * A stored pid is a claim, not a fact: the process can be reaped by the
     * operating system, and a session row can outlive it. This is what the
     * terminal page checks before telling anyone a terminal is connected.
     */
    public function processIsAlive(): bool
    {
        if ($this->pid === null || $this->closed_at !== null) {
            return false;
        }

        return is_numeric($this->pid) && @posix_kill((int) $this->pid, 0);
    }
}
