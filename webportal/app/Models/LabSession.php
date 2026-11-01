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
     * The user who owns this session.
     *
     * A method, not a relation, and deliberately so. A session lives in the
     * infrastructure database alongside lab_environments, and the users table is not
     * there. Eloquent cannot express that as a relation: newRelatedInstance copies the
     * parent's connection onto the related model when it has none of its own, so
     * belongsTo always asks the wrong database, and passing a pre-configured instance
     * does not help because it builds a fresh one anyway. There is no setConnection on
     * Relation to reach for either.
     *
     * So this asks the portal's own database directly. User resolves on
     * database.default, which is where users live.
     *
     * The cost is eager loading, and it is not paid: a session has one owner, it is read
     * when something is recorded rather than when a list is drawn, and the portal already
     * has the signed-in user in hand.
     *
     * This mattered more than it looks. markFailed asked the session for its user, that
     * raised a query exception, and so a lab that failed to provision could not be
     * recorded as failed — the one moment the portal most needed to say something.
     */
    public function owner(): ?User
    {
        if ($this->user_id === null) {
            return null;
        }

        return User::query()->find($this->user_id);
    }

    /**
     * The Incus target for the victim, spelled the way the command line wants it.
     *
     * One place adds the colon. Six call sites each spelling it out is six places for one
     * of them to be wrong, and two of them were: a session stored "lab-env:" and a target
     * built by appending another colon, giving "lab-env::victim-web-104" and a
     * provisioning failure on the very first command.
     *
     * The record holds a remote name, not a command-line fragment. A trailing colon is
     * tolerated on the way in because configuration in this project has always carried
     * it, but it is not stored.
     */
    public function victimTarget(): string
    {
        return self::remoteTarget($this->victim_remote, $this->victim_container);
    }

    /** The Incus target for the attacker, spelled the same way. */
    public function attackerTarget(): string
    {
        return self::remoteTarget($this->attacker_remote, $this->attacker_container);
    }

    /**
     * A remote and a container, with the remote normalised either way.
     *
     * Accepts "lab-env", "lab-env:" and "lab-env" with the container already appended,
     * so no caller can produce a target with two colons.
     */
    public static function remoteTarget(?string $remote, ?string $container): string
    {
        $remote = rtrim((string) $remote, ':');

        return $remote === '' ? (string) $container : $remote.':'.$container;
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

    /**
     * The attacker, as an environment the terminal can open.
     *
     * The attacker is recorded as an environment so the terminal machinery needs no
     * second code path for it: the shell is opened by container name, and both halves
     * of a lab are containers this portal knows how to open a shell in. incus_remote is
     * what distinguishes them and needs no column of its own to do it.
     *
     * @return BelongsTo<LabEnvironment, $this>
     */
    public function attackerEnvironment(): BelongsTo
    {
        return $this->belongsTo(LabEnvironment::class, 'attacker_environment_id');
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
     * The gate is that the lab's infrastructure exists, not that it is currently
     * usable. Those are not the same question, and treating them as one left a student
     * who had finished the course looking at a results page that said its target was
     * withheld — for a lab they had just worked through for six objectives, whose
     * address they had typed into commands themselves the whole time.
     *
     * What is actually secret in this course is the hidden directory in objective four,
     * and Objective::toPublicArray withholds that against the objective's own state.
     * The address is not a secret. It is the student's own target, resolved from their
     * own session, and it exists from the moment provisioning records it.
     *
     * So a session that got far enough to have an address may show it — including
     * after completion, after a reset, and after a failure, since in every one of those
     * the student has already been told.
     */
    public function targetIsDisclosable(): bool
    {
        return filled($this->victim_ip)
            && ! in_array($this->status, [
                LabSessionStatus::Creating,
                LabSessionStatus::Provisioning,
                LabSessionStatus::Failed,
                LabSessionStatus::Deleting,
                LabSessionStatus::Deleted,
            ], true);
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