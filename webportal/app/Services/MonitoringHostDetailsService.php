<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MonitoringHostDetailsService
{
    public function summary(): array
    {
        return Cache::remember('monitoring.host.details.v2', 10, function (): array {
            $url = (string) config('infrastructure.prometheus_query_url', '');
            $boot = $this->value($url, 'node_boot_time_seconds{job="imnehnet_host",instance="192.168.100.1:9100"}');
            $timestamp = $this->value($url, 'timestamp(node_uname_info{job="imnehnet_host",instance="192.168.100.1:9100",nodename="imnehnet"})');
            $up = $this->value($url, 'up{job="imnehnet_host",instance="192.168.100.1:9100"}');
            $age = $timestamp === null ? null : max(0, time() - (int) $timestamp);
            $fresh = $up === 1.0 && $age !== null && $age <= 90;
            return [
                'up' => $fresh,
                'sample_at' => $timestamp,
                'age_seconds' => $age,
                'uptime_seconds' => $fresh && $boot !== null && $boot <= time() ? time() - (int) $boot : null,
            ];
        });
    }

    private function value(string $url, string $query): ?float
    {
        if ($url === '') return null;
        try {
            $response = Http::timeout(3)->acceptJson()->get($url, ['query' => $query]);
            if (! $response->successful() || $response->json('status') !== 'success') return null;
            $items = $response->json('data.result', []);
            if (! is_array($items) || count($items) !== 1) return null;
            $value = $items[0]['value'][1] ?? null;
            return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
        } catch (Throwable) { return null; }
    }
}
