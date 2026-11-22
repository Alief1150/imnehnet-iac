<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminEnvironmentPair extends Model
{
    protected $guarded = [];
    protected $table = 'admin_environment_pairs';

    public function getConnectionName(): ?string
    {
        return (string) config('infrastructure.environment_connection', 'imnehnet');
    }

    public static function forEnvironment(int $id): ?self
    {
        return self::query()->where('attacker_environment_id', $id)
            ->orWhere('victim_environment_id', $id)->first();
    }
}
