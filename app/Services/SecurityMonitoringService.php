<?php

namespace App\Services;

use App\Data\SecuritySummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SecurityMonitoringService
{
    /**
     * Wazuh alert levels follow the SIEM severity scale: 0-6 informational,
     * 7-11 warning, 12-15 critical.
     */
    private const CRITICAL_FROM = 12;

    private const WARNING_FROM = 7;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function summary(): array
    {
        return Cache::remember(
            'security.summary',
            now()->addSeconds((int) config('infrastructure.resource_cache_seconds', 10)),
            function (): array {
                $summary = [];

                foreach ([$this->wazuh(), $this->isolation()] as $entry) {
                    $summary[$entry->key] = $entry->toArray();
                }

                return $summary;
            },
        );
    }

    public function wazuh(): SecuritySummary
    {
        $url = rtrim((string) config('infrastructure.wazuh_api_url', ''), '/');
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);

        if ($url === '') {
            return $this->unavailable(
                'wazuh',
                'Wazuh',
                'not_configured',
                'The Wazuh API endpoint is not configured on the control plane.',
                'not configured',
            );
        }

        try {
            // The management API presents a self-signed certificate, so
            // verification is disabled for this loopback call only.
            $reachable = Http::timeout($timeout)->withOptions(['verify' => false])->get($url);

            if (! $reachable->successful() && $reachable->status() !== 401) {
                return $this->unavailable(
                    'wazuh',
                    'Wazuh',
                    'offline',
                    sprintf('The Wazuh API responded with HTTP %d.', $reachable->status()),
                    $url,
                );
            }

            $token = $this->authenticate($url, $timeout);

            if ($token === null) {
                return $this->unavailable(
                    'wazuh',
                    'Wazuh',
                    'not_configured',
                    'The Wazuh API is reachable but no management API credentials are configured.',
                    $url,
                );
            }

            return $this->alertCounts($url, $token, $timeout);
        } catch (Throwable $exception) {
            Log::debug('Wazuh probe failed.', ['error' => $exception->getMessage()]);

            return $this->unavailable(
                'wazuh',
                'Wazuh',
                'offline',
                'The Wazuh API could not be reached from the control plane.',
                $url,
            );
        }
    }

    /**
     * Isolation is enforced on the lab hosts by packet filter rules, not by
     * anything the portal stores.
     *
     * The inventory has no isolation column, so there is nothing here to read
     * and reporting a count would mean inventing one. The honest answer is that
     * the portal does not evaluate isolation, and saying so is more useful than
     * a green card that would send an operator to check the wrong system.
     */
    public function isolation(): SecuritySummary
    {
        return new SecuritySummary(
            key: 'isolation',
            label: 'Student Isolation',
            status: 'not_configured',
            summary: 'Isolation is enforced on the lab hosts by their own packet filter rules. '
                .'The portal holds no isolation state and does not evaluate it.',
            source: 'lab hosts (outside this portal)',
            checkedAt: Carbon::now(),
        );
    }

    private function authenticate(string $url, int $timeout): ?string
    {
        $username = config('infrastructure.wazuh_api_username');
        $password = config('infrastructure.wazuh_api_password');

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            return null;
        }

        $token = Http::timeout($timeout)
            ->withOptions(['verify' => false])
            ->withBasicAuth($username, $password)
            ->post($url.'/security/user/authenticate')
            ->json('data.token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function alertCounts(string $url, string $token, int $timeout): SecuritySummary
    {
        $count = function (array $query) use ($url, $token, $timeout): ?int {
            $value = Http::timeout($timeout)
                ->withOptions(['verify' => false])
                ->withToken($token)
                ->get($url.'/security/alerts', $query)
                ->json('data.affected_items.0.total_items');

            return is_int($value) ? $value : null;
        };

        $total = $count(['limit' => 1]);
        $critical = $count(['limit' => 1, 'level' => self::CRITICAL_FROM.'..15']);
        $warning = $count(['limit' => 1, 'level' => self::WARNING_FROM.'..'.(self::CRITICAL_FROM - 1)]);

        if ($total === null) {
            return $this->unavailable(
                'wazuh',
                'Wazuh',
                'degraded',
                'The Wazuh API authenticated but the alert query returned no usable result.',
                $url.'/security/alerts',
            );
        }

        return new SecuritySummary(
            key: 'wazuh',
            label: 'Wazuh',
            status: $critical > 0 ? 'degraded' : 'healthy',
            summary: sprintf(
                '%d active alert(s), %d critical, %d warning.',
                $total,
                $critical ?? 0,
                $warning ?? 0,
            ),
            source: $url.'/security/alerts',
            checkedAt: Carbon::now(),
            alertCount: $total,
            criticalCount: $critical ?? 0,
            warningCount: $warning ?? 0,
        );
    }

    private function unavailable(
        string $key,
        string $label,
        string $status,
        string $summary,
        string $source,
    ): SecuritySummary {
        return new SecuritySummary(
            key: $key,
            label: $label,
            status: $status,
            summary: $summary,
            source: $source,
            checkedAt: Carbon::now(),
        );
    }
}
