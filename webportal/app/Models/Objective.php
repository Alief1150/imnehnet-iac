<?php

namespace App\Models;

use App\Enums\EvidenceType;
use App\Enums\ValidationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a course.
 *
 * An objective carries the evidence rule rather than the evidence. What counts as
 * proof — which record on the victim is read, and what is looked for in it — is
 * data, so adding a step to the course does not mean writing code.
 *
 * There is no flag column here, and that is the point. The flag lives inside the
 * victim container and is read from there when a submission is checked. A column
 * would put it in the portal database, in its backups, and in every query that
 * selects an objective.
 */
class Objective extends Model
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
            'sequence' => 'integer',
            'validation_type' => ValidationType::class,
            'evidence_type' => EvidenceType::class,
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The identifier shown beside the title, zero padded so a list reads 01..06.
     */
    public function numberedTitle(): string
    {
        return sprintf('%02d — %s', $this->sequence, $this->title);
    }

    /**
     * What may be sent to a browser, given how far this objective has got.
     *
     * The specification requires that the hidden directory and the flag are not
     * disclosed before the objective that concerns them. The validator needs the
     * evidence value to know what to look for; the browser never does, because a
     * student who can read the answer out of a JSON response has solved the
     * objective without attacking anything.
     *
     * So evidence_value is withheld while the objective is locked, and evidence_type
     * is withheld at every state: knowing that a path will be checked is the same
     * as knowing the path.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(\App\Enums\ObjectiveStatus $status): array
    {
        $payload = [
            'sequence' => $this->sequence,
            'title' => $this->title,
            'status' => $status->value,
            // The wording and the nudge are the course content and are always
            // available; a locked objective is shown as a title only.
            'description' => $status->revealsDetail() ? $this->description : null,
            'hint' => $status->revealsDetail() ? $this->hint : null,
            // Tells the interface to render a submission box rather than a
            // checklist. Never says what the answer is.
            'accepts_flag' => $this->validation_type === ValidationType::Flag,
        ];

        if (! $status->revealsDetail()) {
            return $payload;
        }

        $payload['evidence_type'] = $this->evidence_type?->value;

        // The value itself is released only once the objective is active or
        // already completed. Completed is included so the results page can show
        // what was demonstrated after the fact.
        if ($status !== \App\Enums\ObjectiveStatus::Locked) {
            $payload['evidence_value'] = $this->evidence_value;
        }

        return $payload;
    }
}