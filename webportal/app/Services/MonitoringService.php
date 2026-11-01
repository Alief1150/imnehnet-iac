<?php

namespace App\Services;

use App\Data\MonitoringBackend;
use App\Data\ResourceTrend;
use App\Data\ResourceUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MonitoringService
{
    /**
     * PromQL expressions are read-only and hard-coded, so they are inlined
     * rather than exposed through configuration.
     *
     * @var array<string, string>
     */
    private const QUERIES = [
        'cpu' => 'avg(100 * (1 - avg by (host) (rate(node_cpu_seconds_total{job="imnehnet_vm",mode="idle"}[2m])))) and on() (count(100 * (1 - avg by (host) (rate(node_cpu_seconds_total{job="imnehnet_vm",mode="idle"}[2m])))) == 3)',
        'memory' => 'avg(100 * (1 - (node_memory_MemAvailable_bytes{job="imnehnet_vm"} / node_memory_MemTotal_bytes{job="imnehnet_vm"}))) and on() (count(node_memory_MemTotal_bytes{job="imnehnet_vm"}) == 3)',
        'storage' => 'avg(100 * (1 - (node_filesystem_avail_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"} / node_filesystem_size_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"}))) and on() (count(node_filesystem_size_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"}) == 3)',
        'storage_total' => 'sum(node_filesystem_size_bytes{job="imnehnet_vm",mountpoint="/",fstype!="rootfs"})',
        'containers' => 'count(count by (name) (container_last_seen{name!=""}))',
        'network_rx' => 'sum(rate(node_network_receive_bytes_total{device!~"lo|veth.*|docker.*|br-.*"}[2m])) * 8',
        'network_tx' => 'sum(rate(node_network_transmit_bytes_total{device!~"lo|veth.*|docker.*|br-.*"}[2m])) * 8',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function summary(): array
    {
        return Cache::remember(
            'monitoring.resource.summary',
            now()->addSeconds((int) config('infrastructure.resource_cache_seconds', 10)),
            function (): array {
                $summary = [];

                foreach ($this->probe() as $usage) {
                    $summary[$usage->key] = $usage->toArray();
                }

                return $summary;
            },
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function trend(): array
    {
        return Cache::remember(
            'monitoring.resource.trend',
            now()->addSeconds((int) config('infrastructure.resource_cache_seconds', 10)),
            function (): array {
                $summary = [];

                foreach ($this->trends() as $trend) {
                    $summary[$trend->key] = $trend->toArray();
                }

                return $summary;
            },
        );
    }

    /**
     * Cache only scalar/array payloads, never serialized DTO instances.
     *
     * @return array<int, array<string, mixed>>
     */
    public function backends(): array
    {
        return Cache::remember(
            'monitoring.backends.v2',
            now()->addSeconds((int) config('infrastructure.resource_cache_seconds', 10)),
            fn (): array => array_map(
                fn (MonitoringBackend $backend): array => $backend->toArray(),
                $this->probeBackends(),
            ),
        );
    }

    /**
     * @return array<int, ResourceUsage>
     */
    public function probe(): array
    {
        $url = (string) config('infrastructure.prometheus_query_url', 'http://127.0.0.1:9090/api/v1/query');
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);

        if ($url === '') {
            return [$this->notConfigured()];
        }

        return [
            $this->usage(
                key: 'cpu',
                label: 'CPU',
                percent: $this->percent($url, self::QUERIES['cpu'], $timeout),
                source: $this->source($url, self::QUERIES['cpu']),
            ),
            $this->usage(
                key: 'memory',
                label: 'Memory',
                percent: $this->percent($url, self::QUERIES['memory'], $timeout),
                source: $this->source($url, self::QUERIES['memory']),
            ),
            $this->usage(
                key: 'storage',
                label: 'Storage',
                percent: $this->percent($url, self::QUERIES['storage'], $timeout),
                source: $this->source($url, self::QUERIES['storage']),
            ),
            $this->containerUsage($this->scalar($url, self::QUERIES['containers'], $timeout), $url),
        ];
    }

    /**
     * @return array<int, ResourceTrend>
     */
    public function trends(): array
    {
        $url = (string) config('infrastructure.prometheus_query_url', 'http://127.0.0.1:9090/api/v1/query');
        $rangeUrl = (string) config('infrastructure.prometheus_range_url', 'http://127.0.0.1:9090/api/v1/query_range');
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);
        $minutes = (int) config('infrastructure.resource_trend_minutes', 30);
        $step = (int) config('infrastructure.resource_trend_step_seconds', 60);

        if ($rangeUrl === '') {
            return [];
        }

        $end = now();
        $start = $end->copy()->subMinutes($minutes);

        $trends = [];

        foreach (['cpu', 'memory', 'storage'] as $key) {
            $samples = $this->range($rangeUrl, self::QUERIES[$key], $start, $end, $step, $timeout);
            $latest = $samples === [] ? null : round(end($samples), 1);

            $trends[] = new ResourceTrend(
                key: $key,
                label: ucfirst($key),
                status: $latest === null ? 'unknown' : $this->threshold($latest),
                summary: $latest === null
                    ? 'No historical samples are available yet.'
                    : sprintf('Currently %.1f%% over the last %d minutes.', $latest, $minutes),
                source: sprintf('%s?query=%s', $rangeUrl, self::QUERIES[$key]),
                checkedAt: Carbon::now(),
                samples: $samples,
                latestPercent: $latest,
            );
        }

        return $trends;
    }

    /**
     * @return array<int, MonitoringBackend>
     */
    private function probeBackends(): array
    {
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);

        $definitions = [
            [
                'key' => 'prometheus',
                'label' => 'Prometheus',
                'url' => (string) config('infrastructure.console_base_url').':'.(string) config('infrastructure.prometheus_console_port'),
                'description' => 'Time-series database queried for every figure on this page.',
                'scope' => 'Native Node Exporters on IAAS-MGMT, LAB-ENV and LAB-ATK',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.prometheus_health_url'), $timeout, 'Prometheus Server is Healthy.'),
            ],
            [
                'key' => 'grafana',
                'label' => 'Grafana',
                'url' => (string) config('infrastructure.console_base_url').':'.(string) config('infrastructure.grafana_console_port'),
                'description' => 'Dashboard and visualisation layer over the same Prometheus data.',
                'scope' => 'Visualisation service for infrastructure telemetry',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.grafana_health_url'), $timeout, null),
            ],
            [
                'key' => 'wazuh_dashboard',
                'label' => 'Wazuh Dashboard',
                'url' => 'https://'.(parse_url((string) config('infrastructure.console_base_url'), PHP_URL_HOST) ?: '100.77.79.18').':8443',
                'description' => 'Security operations interface over the Wazuh indexer.',
                'scope' => 'Alert triage and agent inventory',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.wazuh_dashboard_url'), $timeout, null, verifyTls: false),
            ],
            [
                'key' => 'wazuh_api',
                'label' => 'Wazuh API',
                'url' => 'https://'.(parse_url((string) config('infrastructure.console_base_url'), PHP_URL_HOST) ?: '100.77.79.18').':'.(string) config('infrastructure.wazuh_console_port'),
                'description' => 'Management API the portal would query for security telemetry.',
                'scope' => 'Management API reachability; authentication not verified',
                'check' => fn (): bool => $this->apiReachable((string) config('infrastructure.wazuh_health_url'), $timeout),
            ],
        ];

        $backends = [];

        foreach ($definitions as $definition) {
            $started = hrtime(true);
            $healthy = (bool) $definition['check']();

            $backends[] = new MonitoringBackend(
                key: $definition['key'],
                label: $definition['label'],
                url: $definition['url'],
                description: $definition['description'],
                scope: $definition['scope'],
                status: $healthy ? 'healthy' : 'offline',
                checkedAt: Carbon::now(),
                latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
            );
        }

        return $backends;
    }

    /**
     * Whether an authenticated API answers at all.
     *
     * 401 and 403 are the API refusing an unauthenticated request, which is the
     * correct behaviour — the same rule the plane probes apply to the Incus API.
     * Reporting that as offline would be wrong: it confuses "protected" with
     * "down", and the operator cannot tell which one to fix.
     */
    private function apiReachable(string $url, int $timeout): bool
    {
        if ($url === '') {
            return false;
        }

        try {
            $status = Http::timeout($timeout)->withOptions(['verify' => false])->get($url)->status();

            return in_array($status, [200, 401, 403], true);
        } catch (Throwable $exception) {
            Log::debug('Management API probe failed.', ['url' => $url, 'error' => $exception->getMessage()]);

            return false;
        }
    }

    private function reachable(string $url, int $timeout, ?string $expectedBody, bool $verifyTls = true): bool
    {
        if ($url === '') {
            return false;
        }

        try {
            $request = Http::timeout($timeout);

            if (! $verifyTls) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                return false;
            }

            return $expectedBody === null || trim($response->body()) === $expectedBody;
        } catch (Throwable $exception) {
            Log::debug('Monitoring backend probe failed.', ['url' => $url, 'error' => $exception->getMessage()]);

            return false;
        }
    }

    private function range(
        string $url,
        string $expression,
        Carbon $start,
        Carbon $end,
        int $step,
        int $timeout,
    ): array {
        try {
            $response = Http::timeout($timeout)->acceptJson()->get($url, [
                'query' => $expression,
                'start' => $start->getTimestamp(),
                'end' => $end->getTimestamp(),
                'step' => $step,
            ]);

            $series = $response->json('data.result');

            if (! is_array($series) || $series === []) {
                return [];
            }

            $values = $series[0]['values'] ?? [];

            if (! is_array($values)) {
                return [];
            }

            $samples = [];

            foreach ($values as $point) {
                if (isset($point[1]) && is_numeric($point[1])) {
                    $samples[] = round((float) $point[1], 1);
                }
            }

            return $samples;
        } catch (Throwable $exception) {
            Log::debug('Prometheus range query failed.', ['query' => $expression, 'error' => $exception->getMessage()]);

            return [];
        }
    }

    private function usage(
        string $key,
        string $label,
        ?float $percent,
        string $source,
        ?int $totalBytes = null,
    ): ResourceUsage {
        if ($percent === null) {
            return new ResourceUsage(
                key: $key,
                label: $label,
                status: 'unknown',
                summary: 'The metrics backend returned no usable series for this resource.',
                source: $source,
                checkedAt: Carbon::now(),
            );
        }

        return new ResourceUsage(
            key: $key,
            label: $label,
            status: $this->threshold($percent),
            summary: sprintf('%.1f%% average utilization across three monitored VMs.', $percent),
            source: $source,
            checkedAt: Carbon::now(),
            usedPercent: round($percent, 1),
            usedBytes: $totalBytes !== null ? (int) round($totalBytes * $percent / 100) : null,
            totalBytes: $totalBytes,
        );
    }

    private function containerUsage(?float $count, string $url): ResourceUsage
    {
        if ($count === null) {
            return new ResourceUsage(
                key: 'containers',
                label: 'Containers',
                status: 'unknown',
                summary: 'Container metrics are not available from the metrics backend.',
                source: $url,
                checkedAt: Carbon::now(),
            );
        }

        $total = (int) $count;

        return new ResourceUsage(
            key: 'containers',
            label: 'Containers',
            status: 'healthy',
            summary: sprintf('%d container(s) observed on the host.', $total),
            source: $url,
            checkedAt: Carbon::now(),
            count: $total,
        );
    }

    private function source(string $url, string $expression): string
    {
        return sprintf('%s?query=%s', $url, $expression);
    }

    private function threshold(float $percent): string
    {
        return match (true) {
            $percent < (float) config('infrastructure.resource_degraded_percent', 75) => 'healthy',
            $percent < (float) config('infrastructure.resource_critical_percent', 90) => 'degraded',
            default => 'offline',
        };
    }

    private function percent(string $url, string $expression, int $timeout): ?float
    {
        $value = $this->scalar($url, $expression, $timeout);

        return $value === null ? null : round($value, 1);
    }

    private function scalar(string $url, string $expression, int $timeout): ?float
    {
        try {
            $response = Http::timeout($timeout)->acceptJson()->get($url, ['query' => $expression]);

            if (! $response->successful()) {
                return null;
            }

            $results = $response->json('data.result');

            if (! is_array($results) || $results === []) {
                return null;
            }

            $value = $results[0]['value'][1] ?? null;

            return is_numeric($value) ? (float) $value : null;
        } catch (Throwable $exception) {
            Log::debug('Prometheus query failed.', ['query' => $expression, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    private function notConfigured(): ResourceUsage
    {
        return new ResourceUsage(
            key: 'prometheus',
            label: 'Metrics',
            status: 'not_configured',
            summary: 'The metrics backend is not configured on the control plane.',
            source: 'not configured',
            checkedAt: Carbon::now(),
        );
    }
}
