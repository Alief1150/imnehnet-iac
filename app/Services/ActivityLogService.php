<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class ActivityLogService
{
    /**
     * Metadata keys are allow-listed so a caller can never persist something
     * sensitive by accident. Adding a key here is a deliberate decision.
     *
     * @var list<string>
     */
    private const ALLOWED_METADATA_KEYS = [
        'container_name',
        'operation_id',
        'error_code',
        'phase',
        'nested',
        'from_state',
        'to_state',
        'changed_fields',
        'previous_values',
        'limits',
        'port',
        'network',
        'isolation_status',
        'image',
        'hostname',
        'source_service',
        'previous_status',
        'new_status',
    ];

    public function __construct(private readonly ?Request $request = null) {}

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
        $request ??= $this->request;

        return ActivityLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'resource_type' => $resource instanceof Model ? $resource::class : $resource,
            'resource_id' => $resource instanceof Model ? (string) $resource->getKey() : null,
            'status' => $status,
            'description' => $description,
            'metadata' => $this->sanitizeMetadata($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $this->shortUserAgent($request),
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

    private function shortUserAgent(?Request $request): ?string
    {
        $agent = $request?->userAgent();

        if ($agent === null || $agent === '') {
            return null;
        }

        return mb_strlen($agent) > 255 ? mb_substr($agent, 0, 252).'...' : $agent;
    }
}
