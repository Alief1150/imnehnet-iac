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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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