<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    private const ALLOWED_METADATA_KEYS = [
        'container_name',
        'operation_id',
        'error_code',
        'phase',
        'nested',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        Model|string|null $resource,
        string $status,
        string $description,
        array $metadata = [],
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'resource_type' => $resource instanceof Model ? $resource::class : $resource,
            'resource_id' => $resource instanceof Model ? (string) $resource->getKey() : null,
            'status' => $status,
            'description' => $description,
            'metadata' => $this->sanitizeMetadata($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (! in_array((string) $key, self::ALLOWED_METADATA_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeMetadata($value)
                : $value;
        }

        return $sanitized;
    }
}
