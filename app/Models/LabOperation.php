<?php

namespace App\Models;

use App\Enums\OperationStatus;
use Database\Factories\LabOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'lab_id',
    'requested_by',
    'type',
    'status',
    'idempotency_key',
    'attempt_count',
    'error_code',
    'error_message',
    'started_at',
    'finished_at',
])]
class LabOperation extends Model
{
    /** @use HasFactory<LabOperationFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => OperationStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
