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

        // OpenTofu and Ansible get their own entries rather than only appearing
        // inside the automation roll-up: they fail for different reasons, and an
        // operator needs to know which one to go and look at. Each is executed
        // once and its result feeds both its own entry and the roll-up, because
        // probing twice would cost a second process spawn per dashboard load and
        // could make the two answers disagree.
        $openTofu = $this->openTofuHealth();
        $ansible = $this->ansibleHealth();

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
            $openTofu,
            $ansible,
            $this->automationHealth($openTofu, $ansible),
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

    /**
     * The roll-up both tools used to be folded into.
     *
     * Kept because it answers a question the separate entries do not: whether
     * the automation path is usable end to end. Built from results already
     * collected rather than by running the commands a second time.
     */
    private function automationHealth(ServiceHealth $tofu, ServiceHealth $ansible): ServiceHealth
    {
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

    private function openTofuHealth(): ServiceHealth
    {
        $directory = (string) config('infrastructure.tofu.directory');

        if (! is_dir($directory)) {
            return new ServiceHealth(
                key: 'opentofu',
                label: 'OpenTofu',
                status: 'not_configured',
                summary: 'The OpenTofu working directory does not exist.',
                source: (string) config('infrastructure.tofu.binary', 'tofu'),
                checkedAt: Carbon::now(),
                group: 'automation',
            );
        }

        $health = (new LocalServiceHealthProvider(
            $this->commands,
            'opentofu',
            'OpenTofu',
            [(string) config('infrastructure.tofu.binary', 'tofu'), 'version'],
        ))->check();

        if ($health->status !== 'healthy') {
            return $health;
        }

        // The directory matters as much as the binary: it is where every
        // environment's state lives and where the exclusive lock is taken, so a
        // client that runs perfectly but cannot write there is not enough.
        $lockFile = (string) config('infrastructure.tofu.lock_file');
        $lockWritable = is_writable($lockFile) || is_writable(dirname($lockFile));

        return new ServiceHealth(
            key: 'opentofu',
            label: 'OpenTofu',
            status: $lockWritable ? 'healthy' : 'degraded',
            summary: $lockWritable
                ? 'The client runs and the directory lock is writable.'
                : 'The client runs but the directory lock is not writable, so lifecycle actions cannot be serialised.',
            source: $health->source,
            checkedAt: $health->checkedAt,
            latencyMs: $health->latencyMs,
            version: $health->version,
            group: 'automation',
        );
    }

    /**
     * Ansible, checked by whether its client runs at all.
     *
     * Reachability to the compute plane is a separate question, answered by
     * AnsibleService::ping(), which the lifecycle and the terminal use. This
     * probe only has to say whether the tool is present, so it does not repeat
     * a multi-second inventory run every time the dashboard is opened.
     */
    private function ansibleHealth(): ServiceHealth
    {
        if (! is_dir((string) config('infrastructure.ansible.directory'))) {
            return new ServiceHealth(
                key: 'ansible',
                label: 'Ansible',
                status: 'not_configured',
                summary: 'The Ansible directory does not exist.',
                source: (string) config('infrastructure.ansible.ping_binary', 'ansible'),
                checkedAt: Carbon::now(),
                group: 'automation',
            );
        }

        return (new LocalServiceHealthProvider(
            $this->commands,
            'ansible',
            'Ansible',
            [(string) config('infrastructure.ansible.ping_binary', 'ansible'), '--version'],
        ))->check();
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
