<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guided course. One exists for the MVP.
 *
 * Lives on the infrastructure connection alongside lab_environments, because a
 * session points at a victim environment there and the two have to be reachable
 * from the same transaction.
 */
class Course extends Model
{
    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('infrastructure.environment_connection', 'imnehnet');
    }

    /** @return HasMany<Objective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class)->orderBy('sequence');
    }

    /** @return HasMany<LabSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(LabSession::class);
    }

    public function objectiveCount(): int
    {
        return $this->objectives()->count();
    }

    /** The single course of the MVP, by slug. */
    public static function webServerBreach(): ?self
    {
        return static::query()->where('slug', 'imnehnet-web-server-breach')->first();
    }
}