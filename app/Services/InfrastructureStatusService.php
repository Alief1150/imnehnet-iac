<?php

namespace App\Services;

use App\Data\ServiceHealth;
use App\Infrastructure\Health\CommandRunner;
use App\Infrastructure\Health\HttpHealthProvider;
use App\Infrastructure\Health\IncusHealthProvider;
use App\Infrastructure\Health\LocalServiceHealthProvider;
use App\Infrastructure\Health\ManagementPlaneHealthProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InfrastructureStatusService
{
    public function __construct(private readonly CommandRunner $commands) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function summary(): array
    {
        return Cache::remember(
            'infrastructure.status.summary',
            now()->addSeconds(5),
            fn (): array => $this->probeSummary(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function probeSummary(): array
    {
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);

        $services = [
            // The management plane and the compute plane are reported separately
            // so the dashboard can distinguish "the portal is down" from "the lab
            // host is down", which is what an operator actually needs to know.
            $this->managementPlane(),
            $this->computePlane($timeout),
            (new IncusHealthProvider(
                $this->commands,
                (string) config('infrastructure.incus_remote', 'lab-env:'),
            ))->check(),
            $this->databaseHealth(),
            (new HttpHealthProvider(
                'prometheus',
                'Prometheus',
                config('infrastructure.prometheus_health_url'),
                $timeout,
                fn (Response $response): bool => trim($response->body()) === 'Prometheus Server is Healthy.',
                group: 'monitoring',
            ))->check(),
            (new HttpHealthProvider(
                'grafana',
                'Grafana',
                config('infrastructure.grafana_health_url'),
                $timeout,
                fn (Response $response): bool => $response->json('database') === 'ok',
                group: 'monitoring',
            ))->check(),
            (new HttpHealthProvider(
                'blackbox',
                'Blackbox Exporter',
                config('infrastructure.blackbox_health_url'),
                $timeout,
                fn (Response $response): bool => str_contains(strtolower($response->body()), 'healthy'),
                group: 'monitoring',
            ))->check(),
            $this->automationHealth($timeout),
            $this->securityHealth(),
        ];

        $summary = [];

        foreach ($services as $service) {
            $summary[$service->key] = $service->toArray();
        }

        return $summary;
    }

    private function managementPlane(): ServiceHealth
    {
        return (new ManagementPlaneHealthProvider(
            appName: (string) config('app.name', 'IMNEHNET'),
            environment: (string) config('app.env', 'production'),
            host: (string) gethostname(),
        ))->check();
    }

    private function computePlane(int $timeout): ServiceHealth
    {
        $url = config('infrastructure.lab_env_api_url');

        $health = (new HttpHealthProvider(
            'lab_env',
            'LAB-ENV',
            is_string($url) ? $url : null,
            $timeout,
            fn (Response $response): bool => $response->json('status') === 'Success',
            verifyTls: false,
            group: 'compute_plane',
        ))->check();

        return new ServiceHealth(
            key: $health->key,
            label: $health->label,
            status: $health->status,
            summary: $health->status === 'healthy'
                ? 'Compute plane is reachable and running Incus.'
                : $health->summary,
            source: $health->source,
            checkedAt: $health->checkedAt,
            latencyMs: $health->latencyMs,
            group: 'compute_plane',
        );
    }

    private function databaseHealth(): ServiceHealth
    {
        $started = hrtime(true);

        try {
            DB::selectOne('select 1 as healthy');

            return new ServiceHealth(
                key: 'database',
                label: 'MySQL',
                status: 'healthy',
                summary: 'Laravel database connection responded successfully.',
                source: 'Laravel database connection',
                checkedAt: Carbon::now(),
                latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
            );
        } catch (Throwable) {
            return new ServiceHealth(
                key: 'database',
                label: 'MySQL',
                status: 'offline',
                summary: 'Laravel database connection could not be verified.',
                source: 'Laravel database connection',
                checkedAt: Carbon::now(),
                latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
            );
        }
    }

    private function automationHealth(int $timeout): ServiceHealth
    {
        $tofu = (new LocalServiceHealthProvider(
            $this->commands,
            'opentofu',
            'OpenTofu',
            ['tofu', 'version'],
        ))->check();

        $ansible = (new LocalServiceHealthProvider(
            $this->commands,
            'ansible',
            'Ansible',
            ['ansible', '--version'],
        ))->check();

        $available = $tofu->status === 'healthy' && $ansible->status === 'healthy';

        return new ServiceHealth(
            key: 'automation',
            label: 'Automation',
            status: $available ? 'degraded' : 'unknown',
            summary: $available
                ? 'OpenTofu and Ansible are available; lab mutation still runs through the unavailable provider.'
                : 'OpenTofu or Ansible availability could not be verified.',
            source: 'tofu version; ansible --version',
            checkedAt: Carbon::now(),
            latencyMs: ($tofu->latencyMs ?? 0) + ($ansible->latencyMs ?? 0),
            version: implode(' | ', array_filter([$tofu->version, $ansible->version])),
            group: 'automation',
        );
    }

    private function securityHealth(): ServiceHealth
    {
        // Wazuh lives in SecurityMonitoringService so that the health card and
        // the security widget can never disagree about the SIEM state.
        $wazuh = app(SecurityMonitoringService::class)->wazuh();

        return new ServiceHealth(
            key: $wazuh->key,
            label: $wazuh->label,
            status: $wazuh->status,
            summary: $wazuh->summary,
            source: $wazuh->source,
            checkedAt: $wazuh->checkedAt,
            group: 'security',
        );
    }
}
