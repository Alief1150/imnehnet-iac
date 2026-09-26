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
        'cpu' => '100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[2m])) * 100)',
        'memory' => '100 * (1 - (node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes))',
        'storage' => 'max(100 * (1 - (node_filesystem_avail_bytes{fstype!="tmpfs",fstype!="overlay"} / node_filesystem_size_bytes{fstype!="tmpfs",fstype!="overlay"})))',
        'storage_total' => 'max(node_filesystem_size_bytes{fstype!="tmpfs",fstype!="overlay"})',
        'containers' => 'count(count by (name) (container_last_seen{name!=""}))',
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
     * The consoles behind the figures, so an operator can open the source
     * instead of trusting a number with no origin.
     *
     * @return array<int, MonitoringBackend>
     */
    public function backends(): array
    {
        return Cache::remember(
            'monitoring.backends',
            now()->addSeconds((int) config('infrastructure.resource_cache_seconds', 10)),
            fn (): array => $this->probeBackends(),
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

        $storageTotal = $this->scalar($url, self::QUERIES['storage_total'], $timeout);

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
                totalBytes: $storageTotal,
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
                'scope' => 'IAAS-MGMT host metrics, scraped by node_exporter',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.prometheus_health_url'), $timeout, 'Prometheus Server is Healthy.'),
            ],
            [
                'key' => 'grafana',
                'label' => 'Grafana',
                'url' => (string) config('infrastructure.console_base_url').':'.(string) config('infrastructure.grafana_console_port'),
                'description' => 'Dashboard and visualisation layer over the same Prometheus data.',
                'scope' => 'Visualises IAAS-MGMT host metrics',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.grafana_health_url'), $timeout, null),
            ],
            [
                'key' => 'wazuh',
                'label' => 'Wazuh',
                'url' => (string) config('infrastructure.console_base_url').':'.(string) config('infrastructure.wazuh_console_port'),
                'description' => 'Security information and event management. Separate concern from resource monitoring.',
                'scope' => 'Security events from hosts and containers',
                'check' => fn (): bool => $this->reachable((string) config('infrastructure.wazuh_health_url'), $timeout, null, verifyTls: false),
            ],
        ];

        $backends = [];

        foreach ($definitions as $definition) {
            $backends[] = new MonitoringBackend(
                key: $definition['key'],
                label: $definition['label'],
                url: $definition['url'],
                description: $definition['description'],
                scope: $definition['scope'],
                status: $definition['check']() ? 'healthy' : 'offline',
                checkedAt: Carbon::now(),
            );
        }

        return $backends;
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
            summary: sprintf('%.1f%% of the host capacity is in use.', $percent),
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
