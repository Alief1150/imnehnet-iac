<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Read-only Prometheus history; no VM series is invented when a target is missing. */
final class MonitoringVmSeriesService
{
    private const EXPRESSIONS = [
        'cpu' => '100 * (1 - avg by (host) (rate(node_cpu_seconds_total{job="imnehnet_vm",mode="idle"}[2m])))',
        'memory' => '100 * (1 - (node_memory_MemAvailable_bytes{job="imnehnet_vm"} / node_memory_MemTotal_bytes{job="imnehnet_vm"}))',
        'storage' => '100 * (1 - (node_filesystem_avail_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"} / node_filesystem_size_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"}))',
    ];

    /** @return array<string, mixed> */
    public function series(): array
    {
        return Cache::remember('monitoring.vm.series.v3', now()->addSeconds(10), function (): array {
            $url = (string) config('infrastructure.prometheus_range_url', '');
            $minutes = max(1, (int) config('infrastructure.resource_trend_minutes', 30));
            $step = 15; // Prometheus query resolution, not scrape interval; no server config modified.
            $end = Carbon::now();
            $result = [];
            foreach (self::EXPRESSIONS as $metric => $query) {
                $result[$metric] = ['IAAS-MGMT' => [], 'LAB-ENV' => [], 'LAB-ATK' => []];
                if ($url === '') {
                    continue;
                }
                try {
                    $response = Http::timeout((int) config('infrastructure.probe_timeout_seconds', 3))
                        ->acceptJson()->get($url, [
                            'query' => $query,
                            'start' => $end->copy()->subMinutes($minutes)->timestamp,
                            'end' => $end->timestamp,
                            'step' => $step,
                        ]);
                    if (! $response->successful() || $response->json('status') !== 'success') {
                        continue;
                    }
                    foreach ($response->json('data.result', []) as $series) {
                        $host = (string) ($series['metric']['host'] ?? '');
                        if (! in_array($host, ['IAAS-MGMT', 'LAB-ENV', 'LAB-ATK'], true)) {
                            continue;
                        }
                        $points = [];
                        foreach (($series['values'] ?? []) as $sample) {
                            if (isset($sample[0], $sample[1]) && is_numeric($sample[1]) && is_finite((float) $sample[1])) {
                                $points[] = ['x' => (int) round(((float) $sample[0]) * 1000), 'y' => round(min(100, max(0, (float) $sample[1])), 1)];
                            }
                        }
                        $result[$metric][$host] = $points;
                    }
                } catch (Throwable) {
                    // An unavailable exporter is shown as No Data, never as zero.
                }
            }

            return $result;
        });
    }
}
