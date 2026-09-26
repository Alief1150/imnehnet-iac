<?php

namespace App\Models;

use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use Database\Factories\LabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'external_id',
    'name',
    'container_name',
    'compute_host',
    'base_image',
    'profile',
    'ip_address',
    'network',
    'status',
    'desired_state',
    'actual_state',
    'operation_state',
    'ssh_port',
    'web_port',
    'access_host',
    'access_username',
    'access_verified_at',
    'last_verified_at',
    'cpu_limit',
    'memory_limit',
    'storage_limit',
    'security_policy',
    'isolation_status',
    'last_error_message',
])]
class Lab extends Model
{
    /** @use HasFactory<LabFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'desired_state' => LabDesiredState::class,
            'actual_state' => LabActualState::class,
            'operation_state' => LabOperationState::class,
            'access_verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(LabOperation::class);
    }

    public function sshCommand(): ?string
    {
        if (! $this->access_verified_at || ! $this->access_host || ! $this->ssh_port || ! $this->access_username) {
            return null;
        }

        return sprintf(
            'ssh %s@%s -p %d',
            $this->access_username,
            $this->access_host,
            $this->ssh_port,
        );
    }
}
