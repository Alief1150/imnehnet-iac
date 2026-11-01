<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final class IncusInventoryMonitoringService
{
    private const CACHE_KEY = 'monitoring.incus.inventory.snapshot.v2';

    private const STALE_AFTER_SECONDS = 180;

    /**
     * Read-only for web requests: NEVER executes incus.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $snapshot = Cache::get(self::CACHE_KEY);
        if (! is_array($snapshot) || ! isset($snapshot['updated_at'])) {
            return $this->unavailable('missing');
        }

        try {
            $age = max(0, (int) Carbon::parse($snapshot['updated_at'])->diffInSeconds(now(), false));
        } catch (Throwable) {
            return $this->unavailable('invalid_timestamp');
        }

        if ($age > self::STALE_AFTER_SECONDS) {
            return $this->unavailable('stale', (string) $snapshot['updated_at']);
        }

        $snapshot['freshness'] = 'fresh';

        return $snapshot;
    }

    /**
     * Invoke ONLY from CLI under the iaas account, which owns the Incus remote credentials.
     * Does not modify any Incus instance.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        if (! app()->runningInConsole()) {
            throw new RuntimeException('Incus inventory refresh is CLI-only.');
        }

        $result = $this->emptySummary();
        $binary = (string) config('infrastructure.incus_binary', 'incus');
        $remotes = [
            'LAB-ENV' => (string) config('infrastructure.incus_remote', 'lab-env:'),
            'LAB-ATK' => (string) config('infrastructure.incus_attacker_remote', 'lab-atk'),
        ];

        foreach ($remotes as $host => $remote) {
            try {
                $target = rtrim($remote, ':').':';
                $process = Process::timeout(15)->run([$binary, 'list', $target, '--format=json']);
                if (! $process->successful()) {
                    throw new RuntimeException('Incus list returned a non-zero exit code.');
                }
                $instances = json_decode($process->output(), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($instances) || ! array_is_list($instances)) {
                    throw new RuntimeException('Unexpected Incus inventory response.');
                }
                $counts = ['running' => 0, 'stopped' => 0, 'other' => 0];
                foreach ($instances as $instance) {
                    if (! is_array($instance)) {
                        continue;
                    }
                    $status = strtolower((string) ($instance['status'] ?? 'unknown'));
                    $key = in_array($status, ['running', 'stopped'], true) ? $status : 'other';
                    $counts[$key]++;
                    $result[$key]++;
                    $result['total']++;
                }
                $result['available']++;
                $result['hosts'][$host] = ['status' => 'available', 'counts' => $counts];
            } catch (Throwable $exception) {
                $result['errors'][] = $host;
                $result['hosts'][$host] = ['status' => 'unavailable', 'counts' => null];
                // Deliberately do not log TLS credentials, full command output or private details.
                logger()->warning('Monitoring Incus inventory refresh failed', [
                    'host' => $host,
                    'error_type' => get_class($exception),
                ]);
            }
        }

        $result['updated_at'] = now()->toIso8601String();
        $result['freshness'] = 'fresh';
        // Store without TTL so the UI can explicitly distinguish stale from missing.
        Cache::forever(self::CACHE_KEY, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    private function emptySummary(): array
    {
        return ['running' => 0, 'stopped' => 0, 'other' => 0, 'total' => 0,
            'available' => 0, 'errors' => [], 'hosts' => []];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $reason, ?string $updatedAt = null): array
    {
        return array_merge($this->emptySummary(), [
            'freshness' => $reason,
            'updated_at' => $updatedAt,
        ]);
    }
}
