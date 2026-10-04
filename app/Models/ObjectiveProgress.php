<?php

namespace App\Models;

use App\Enums\ObjectiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One objective's state within one session.
 *
 * The evidence column is the audit trail. Without it, "why does this say
 * completed" can only be answered by going back to the victim's logs, which will
 * have rotated by the time anyone asks. What is stored is the line that was
 * accepted, not the whole log.
 */
class ObjectiveProgress extends Model
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
            'status' => ObjectiveStatus::class,
            'completed_at' => 'datetime',
            'evidence' => 'array',
        ];
    }

    /** @return BelongsTo<LabSession, $this> */
    public function labSession(): BelongsTo
    {
        return $this->belongsTo(LabSession::class);
    }

    /** @return BelongsTo<Objective, $this> */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }
}