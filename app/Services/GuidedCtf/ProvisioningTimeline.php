<?php

namespace App\Services\GuidedCtf;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class ProvisioningTimeline
{
    private const TTL = 86400;

    public static function events(int $sessionId): array
    {
        try {
            $events = Cache::get(self::key($sessionId), []);

            return is_array($events) ? $events : [];
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    public static function record(
        int $sessionId,
        string $stage,
        string $message,
        string $level = 'info',
    ): void {
        try {
            $key = self::key($sessionId);

            Cache::lock($key.':lock', 5)->block(2, function () use (
                $key,
                $stage,
                $message,
                $level
            ): void {
                $events = Cache::get($key, []);

                if (! is_array($events)) {
                    $events = [];
                }

                $events[] = [
                    'time' => now()->format('H:i:s'),
                    'stage' => $stage,
                    'message' => $message,
                    'level' => in_array($level, ['info', 'success', 'error'], true)
                        ? $level
                        : 'info',
                ];

                Cache::put($key, array_slice($events, -40), self::TTL);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function key(int $sessionId): string
    {
        return 'imnehnet:ctf:provisioning:'.$sessionId;
    }
}
