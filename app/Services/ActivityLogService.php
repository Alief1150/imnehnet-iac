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
        'workspace',
        'internal_ip',
        'runtime_status',
        'image',
        'cpu_limit',
        'memory_limit_mb',
        'apply_seconds',
        'requested_action',
        'exit_code',
        'command',
    ];

    /**
     * Keys that must never be written, at any nesting depth.
     *
     * The top level is already restricted by the allow list. This catches a
     * caller that hides a credential inside an allow-listed value, for example
     * `previous_values => ['password' => ...]`.
     *
     * Filtering at depth cannot use the allow list itself: the keys inside a
     * value are the caller's own vocabulary rather than metadata keys, so
     * `changed_fields => ['status']` would be reduced to an empty list and the
     * audit entry would claim a change without saying what it was.
     *
     * @var list<string>
     */
    private const BLOCKED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'secret',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'bearer',
        'authorization',
        'private_key',
        'passphrase',
        'credential',
        'credentials',
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
                ? $this->stripBlockedKeys($value)
                : $value;
        }

        return $sanitized;
    }

    /**
     * Removes credential-shaped keys from inside a value, at any depth.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function stripBlockedKeys(array $value): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), self::BLOCKED_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($item) ? $this->stripBlockedKeys($item) : $item;
        }

        return $clean;
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
