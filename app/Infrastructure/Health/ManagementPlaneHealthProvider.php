<?php

namespace App\Infrastructure\Health;

use App\Contracts\InfrastructureHealthProvider;
use App\Data\ServiceHealth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ManagementPlaneHealthProvider implements InfrastructureHealthProvider
{
    public function __construct(
        private readonly string $appName,
        private readonly string $environment,
        private readonly string $host,
    ) {}

    /**
     * The management plane probe deliberately avoids issuing an HTTP request
     * back to this same process. The development server is single threaded, so
     * a loopback call from inside a request can block on itself and report a
     * false outage. A live application context plus a database round trip is
     * both cheaper and a truer signal that this plane can serve requests.
     */
    public function check(): ServiceHealth
    {
        $started = hrtime(true);

        try {
            $database = DB::connection()->getPdo();
            $reachable = (bool) $database->query('select 1')->fetchColumn();

            return new ServiceHealth(
                key: 'iaas_mgmt',
                label: 'IAAS-MGMT',
                status: $reachable ? 'healthy' : 'degraded',
                summary: $reachable
                    ? sprintf('%s is serving on the management plane and its database answers.', $this->appName)
                    : 'The management plane is running but its database did not answer.',
                source: 'Laravel application context + database round trip',
                checkedAt: Carbon::now(),
                latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
                version: sprintf('%s (%s) on %s', $this->appName, $this->environment, $this->host),
                group: 'management_plane',
            );
        } catch (\Throwable $exception) {
            return new ServiceHealth(
                key: 'iaas_mgmt',
                label: 'IAAS-MGMT',
                status: 'offline',
                summary: 'The management plane database could not be reached.',
                source: 'Laravel application context + database round trip',
                checkedAt: Carbon::now(),
                latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
                group: 'management_plane',
            );
        }
    }
}
