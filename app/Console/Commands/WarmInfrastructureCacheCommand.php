<?php

namespace App\Console\Commands;

use App\Services\InfrastructureStatusService;
use App\Services\InfrastructureTopologyService;
use App\Services\SecurityTelemetryCollector;
use Illuminate\Console\Command;

/**
 * Fill the infrastructure caches outside any request.
 *
 * ── Why this exists ──────────────────────────────────────────────────────────
 *
 * Every render of the Terminal, Infrastructure and Dashboard pages recomputes the
 * status summary and the topology when their short caches lapsed — up to a couple
 * of seconds of sequential subprocess and HTTP probes, on the click that asked
 * for the page. When several backends stall at once the timeouts add up instead,
 * and a select that should answer in a second answers in tens of them, with every
 * later click queuing behind it. Warming the same two caches every minute, as the
 * host account that can actually reach everything, keeps renders on the fast path
 * without lengthening any TTL: a stale health verdict would be a lie, but a warm
 * cache is only ever seconds old.
 */
class WarmInfrastructureCacheCommand extends Command
{
    protected $signature = 'infrastructure:warm';

    protected $description = 'Recompute the infrastructure status and topology caches';

    public function handle(
        InfrastructureStatusService $status,
        InfrastructureTopologyService $topology,
        SecurityTelemetryCollector $security,
    ): int {
        $status->summary();
        $hosts = $topology->topology()['hosts'] ?? [];

        $this->line(sprintf(
            'warmed %d host(s): %s',
            count($hosts),
            implode(',', array_map(
                fn (array $host): string => ($host['name'] ?? '?').'='.($host['health'] ?? '?'),
                $hosts,
            )),
        ));

        $telemetry = $security->collect();

        $this->line(sprintf(
            'siem %s, isolation %s',
            ($telemetry['alerts']['ok'] ?? false) ? ($telemetry['alerts']['day']['total'] ?? 0).' alert(s)/24h' : 'unreadable',
            ($telemetry['isolation']['ok'] ?? false) ? ($telemetry['isolation']['percent'] ?? '?').'% pairs' : 'unreadable',
        ));

        return self::SUCCESS;
    }
}
