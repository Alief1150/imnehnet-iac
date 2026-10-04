<?php

namespace App\Models;

use App\Enums\LabSessionStatus;
use App\Enums\ObjectiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's attempt at one course: an attacker container, a victim container,
 * and the pairing between them.
 *
 * The pairing is stored rather than derived, because the whole security argument
 * rests on it. Every check the portal makes — which shell may be opened, which
 * target address may be shown, which log lines count as proof — resolves through
 * this row, and this row is reached from the authenticated user. Nothing a browser
 * sends names a container.
 */
class LabSession extends Model
{
    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('infrastructure.environment_connection', 'imnehnet');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LabSessionStatus::class,
            'current_objective' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The Incus target for the victim, spelled the way the command line wants it.
     *
     * One place adds the colon. Six call sites each spelling it out is six places for
     * one of them to be wrong, and two of them were: a session stored "lab-env:" and a
     * target built by appending another colon, giving "lab-env::victim-web-104" and a
     * provisioning failure on the first command.
     *
     * The record holds a name, not a command-line fragment. A trailing colon is
     * tolerated on the way in because configuration in this project has always carried
     * it, but it is not stored.
     */
    public function victimTarget(): string
    {
        return static::remoteTarget($this->victim_remote, $this->victim_container);
    }

    /** The Incus target for the attacker, spelled the same way. */
    public function attackerTarget(): string
    {
        return static::remoteTarget($this->attacker_remote, $this->attacker_container);
    }

    /**
     * A remote and a container, with the remote normalised either way.
     *
     * Accepts "lab-env", "lab-env:" and "lab-env" with the container already appended,
     * so a caller cannot produce a target with two colons.
     */
    public static function remoteTarget(?string $remote, ?string $container): string
    {
        $remote = rtrim((string) $remote, ':');

        return $remote === '' ? (string) $container : $remote.':'.$container;
    }

    /**
     * The user who owns this session.
     *
     * A method, not a relation, and deliberately so. A session lives in the
     * infrastructure database alongside lab_environments, and the users table is not
     * there. Eloquent cannot express that as a relation: newRelatedInstance copies the
     * parent's connection onto the related model when it has none of its own, so
     * belongsTo always asks the wrong database, and passing a pre-configured instance
     * does not help because it builds a fresh one anyway.
     *
     * So this asks the portal's own database directly. User resolves on
     * database.default, which is where users live.
     *
     * The cost is eager loading, and it is not paid: a session has one owner, it is
     * read when something is recorded rather than when a list is drawn, and the portal
     * already has the signed-in user in hand.
     */
    public function owner(): ?User
    {
        if ($this->user_id === null) {
            return null;
        }

        return User::query()->find($this->user_id);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<LabEnvironment, $this> */
    public function victimEnvironment(): BelongsTo
    {
        return $this->belongsTo(LabEnvironment::class, 'victim_environment_id');
    }

    /** @return HasMany<ObjectiveProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(ObjectiveProgress::class);
    }

    /**
     * The objective in play, as a relation rather than a number.
     *
     * current_objective is stored so the interface can render a header without a
     * join, but anything that needs the objective itself resolves it here, which
     * is a single place to change if the numbering is ever derived instead.
     *
     * @return BelongsTo<Objective, $this>
     */
    public function currentObjective(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'current_objective', 'sequence');
    }

    /**
     * Whether the address may be shown to the student.
     *
     * The specification says the target is disclosed once the lab is ready, so a
     * session that is not ready has no address to disclose even if a row happens
     * to carry one from a previous run.
     */
    public function targetIsDisclosable(): bool
    {
        return $this->status->allowsTerminal() && filled($this->victim_ip);
    }

    /**
     * How far through the course this session is, as completed out of total.
     *
     * @return array{completed: int, total: int}
     */
    public function progressCount(): array
    {
        $objectives = $this->course?->objectives()->count() ?? 0;

        $completed = $this->progress()
            ->where('status', ObjectiveStatus::Completed->value)
            ->count();

        return ['completed' => $completed, 'total' => $objectives];
    }

    /**
     * Elapsed time as the results page shows it.
     *
     * Null before the session has a start, and equal to the whole run rather than
     * growing without limit once it has a finish.
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }
}