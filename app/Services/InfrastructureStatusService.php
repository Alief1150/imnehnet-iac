<?php

namespace App\Services;

use App\Data\ServiceHealth;
use App\Infrastructure\Health\CommandRunner;
use App\Infrastructure\Health\HttpHealthProvider;
use App\Infrastructure\Health\IncusHealthProvider;
use App\Infrastructure\Health\LocalServiceHealthProvider;
use App\Infrastructure\Health\UnavailableHealthProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InfrastructureStatusService
{
    public function __construct(private readonly CommandRunner $commands) {}

    /** @return array<string, array<string, mixed>> */
    public function summary(): array
    {
        return Cache::remember(
            'infrastructure.status.summary',
            now()->addSeconds(5),
            fn (): array => $this->probeSummary(),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function probeSummary(): array
    {
        $timeout = (int) config('infrastructure.probe_timeout_seconds', 3);

        $incus = (new IncusHealthProvider(
            $this->commands,
            (string) config('infrastructure.incus_remote', 'lab-env:'),
        ))->check();
        $prometheus = (new HttpHealthProvider(
            'prometheus',
            'Prometheus',
            config('infrastructure.prometheus_health_url'),
            $timeout,
            fn (Response $response): bool => trim($response->body()) === 'Prometheus Server is Healthy.',
        ))->check();
        $grafana = (new HttpHealthProvider(
            'grafana',
            'Grafana',
            config('infrastructure.grafana_health_url'),
            $timeout,
            fn (Response $response): bool => $response->json('database') === 'ok',
        ))->check();
        $blackbox = (new HttpHealthProvider(
            'blackbox',
            'Blackbox Exporter',
            config('infrastructure.blackbox_health_url'),
            $timeout,
            fn (Response $response): bool => str_contains(strtolower($response->body()), 'healthy'),
        ))->check();
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
        $wazuh = (new UnavailableHealthProvider(
            'wazuh',
            'Wazuh',
            'not_connected',
            'Authenticated Wazuh API integration is not connected.',
        ))->check();

        $services = [
            $incus,
            $this->databaseHealth(),
            $prometheus,
            $grafana,
            $blackbox,
            $this->automationHealth($tofu, $ansible),
            $wazuh,
        ];

        $summary = [];

        foreach ($services as $service) {
            $summary[$service->key] = $service->toArray();
        }

        return $summary;
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

    private function automationHealth(ServiceHealth $tofu, ServiceHealth $ansible): ServiceHealth
    {
        $available = $tofu->status === 'healthy' && $ansible->status === 'healthy';

        return new ServiceHealth(
            key: 'automation',
            label: 'Automation',
            status: $available ? 'degraded' : 'unknown',
            summary: $available
                ? 'OpenTofu and Ansible are available; live mutation remains disabled in Milestone 1.'
                : 'OpenTofu or Ansible availability could not be verified.',
            source: 'tofu version; ansible --version',
            checkedAt: Carbon::now(),
            latencyMs: ($tofu->latencyMs ?? 0) + ($ansible->latencyMs ?? 0),
            version: implode(' | ', array_filter([$tofu->version, $ansible->version])),
        );
    }
}
