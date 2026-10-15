<?php

namespace App\Services;

use App\Infrastructure\Health\CommandRunner;
use App\Models\ActivityLog;
use App\Models\LabSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What the SIEM and the packet filter report, read by the host account.
 *
 * ── Why this runs as iaas, not as the web ────────────────────────────────────
 *
 * The alert log lives under a root-owned docker volume the web server may not
 * read, the host auth logs belong to syslog:adm, and the nftables state answers
 * only over the lab key. Rather than weaken any of that, collection happens in
 * WarmInfrastructureCacheCommand, which cron runs as iaas, and the page only
 * ever reads the cache.
 *
 * ── Merge, never overwrite ───────────────────────────────────────────────────
 *
 * Alerts, failed logins and isolation are collected independently, and each is
 * written back only when its own collection succeeded. A run as the wrong user
 * or a plane rebooting mid-minute must leave yesterday's numbers with a stale
 * stamp rather than replace them with a failure.
 */
final class SecurityTelemetryCollector
{
    private const ALERTS_FILE = '/var/lib/docker/volumes/single-node_wazuh_logs/_data/alerts/alerts.json';

    private const AUTH_LOGS = ['/var/log/auth.log', '/var/log/auth.log.1'];

    private const SSH_KEY = '/home/iaas/.ssh/imnehnet_ansible';

    /** Seconds after which a reading is shown as stale rather than current. */
    private const STALE_AFTER_SECONDS = 180;

    /** Points kept per series. Isolation is one point per minute for 24 hours. */
    private const HISTORY_POINTS = 1440;

    /** Lines parsed per collection at most: months of alerts at current rates. */
    private const MAX_ALERT_LINES = 20000;

    public function __construct(private readonly CommandRunner $commands) {}

    /**
     * Collect every section and write back the ones that answered.
     *
     * The filesystem reader and the clock are injectable so tests feed canned
     * logs instead of reading production files on every run. The command runner
     * is injectable for the same reason: no test should SSH anywhere.
     *
     * @param  callable|null  $fs  function(string $path): ?string
     * @param  callable|null  $exec  function(array $command, int $timeout): array{successful: bool, stdout: string, stderr: string}
     * @return array<string, array<string, mixed>>
     */
    public function collect(?callable $fs = null, ?int $now = null, ?callable $exec = null): array
    {
        $read = $fs ?? fn (string $path): ?string => ($content = @file_get_contents($path)) === false ? null : $content;
        $run = $exec ?? fn (array $command, int $timeout): array => $this->commands->run($command, $timeout);
        $now ??= time();

        $telemetry = $this->telemetry();
        $isolationHistory = Cache::get('security.isolation_history.v1', []);

        $alerts = $this->collectAlerts($read, $now);

        if ($alerts !== null) {
            $telemetry['alerts'] = $alerts;
        } else {
            $telemetry['alerts'] = $this->staled($telemetry['alerts'] ?? null, 'The Wazuh alert log could not be read.');
        }

        $failed = $this->collectFailedLogins($read, $now);

        if ($failed !== null) {
            $telemetry['failed_logins'] = $failed;
        } else {
            $telemetry['failed_logins'] = $this->staled($telemetry['failed_logins'] ?? null, 'The sign-in and auth logs could not be read.');
        }

        $isolation = $this->collectIsolation($run, $now);

        if ($isolation !== null) {
            $telemetry['isolation'] = $isolation;

            if (is_numeric($isolation['percent'] ?? null)) {
                $isolationHistory[] = [$now, (float) $isolation['percent']];
                $isolationHistory = array_slice($isolationHistory, -self::HISTORY_POINTS);
                Cache::put('security.isolation_history.v1', $isolationHistory);
            }
        } else {
            $telemetry['isolation'] = $this->staled($telemetry['isolation'] ?? null, 'The packet filter could not be read.');
        }

        $visibility = $alerts !== null ? $this->collectVisibility($alerts, $now) : null;

        if ($visibility !== null) {
            $telemetry['visibility'] = $visibility;
        } else {
            $telemetry['visibility'] = $this->staled($telemetry['visibility'] ?? null, 'Container reporting could not be evaluated.');
        }

        $telemetry['collected_at'] = $now;

        Cache::put('security.telemetry.v1', $telemetry);

        return $telemetry;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function telemetry(): array
    {
        return Cache::get('security.telemetry.v1', []);
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    public function isolationHistory(): array
    {
        return Cache::get('security.isolation_history.v1', []);
    }

    public function isStale(?int $collectedAt, ?int $now = null): bool
    {
        if ($collectedAt === null) {
            return true;
        }

        return ($now ?? time()) - $collectedAt > self::STALE_AFTER_SECONDS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collectAlerts(callable $read, int $now): ?array
    {
        $content = $read(self::ALERTS_FILE);

        if ($content === null || trim($content) === '') {
            return null;
        }

        $lines = explode("\n", $content);
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if (count($lines) > self::MAX_ALERT_LINES) {
            $lines = array_slice($lines, -self::MAX_ALERT_LINES);
        }

        $alerts = [];

        foreach ($lines as $line) {
            $row = json_decode($line, true);

            if (! is_array($row)) {
                continue;
            }

            $ts = isset($row['timestamp']) && is_string($row['timestamp']) ? strtotime($row['timestamp']) : false;

            if ($ts === false) {
                continue;
            }

            $level = (int) ($row['rule']['level'] ?? 0);

            $alerts[] = [
                'ts' => $ts,
                'level' => $level,
                'severity' => $level >= 12 ? 'high' : ($level >= 7 ? 'medium' : 'low'),
                'rule' => (string) ($row['rule']['id'] ?? ''),
                'description' => (string) ($row['rule']['description'] ?? ''),
                'agent' => (string) ($row['agent']['name'] ?? 'unknown'),
                'ip' => is_string($row['agent']['ip'] ?? null) ? $row['agent']['ip'] : null,
            ];
        }

        if ($alerts === []) {
            return null;
        }

        usort($alerts, fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);

        $day = array_values(array_filter($alerts, fn (array $a): bool => $a['ts'] >= $now - 86400));
        $prev = array_values(array_filter($alerts, fn (array $a): bool => $a['ts'] >= $now - 172800 && $a['ts'] < $now - 86400));

        $buckets = [];

        foreach ($alerts as $alert) {
            $hour = intdiv($alert['ts'], 3600) * 3600;
            $buckets[$hour] ??= ['total' => 0, 'low' => 0, 'med' => 0, 'high' => 0, 'agents' => []];
            $buckets[$hour]['total']++;

            if ($alert['severity'] === 'high') {
                $buckets[$hour]['high']++;
            } elseif ($alert['severity'] === 'medium') {
                $buckets[$hour]['med']++;
            } else {
                $buckets[$hour]['low']++;
            }

            if ($alert['agent'] !== 'unknown') {
                $buckets[$hour]['agents'][$alert['agent']] = true;
            }
        }

        ksort($buckets);

        $hourly = [];

        foreach ($buckets as $ts => $bucket) {
            $bucket['agents'] = array_keys($bucket['agents']);
            sort($bucket['agents']);
            $hourly[] = [$ts, $bucket];
        }

        // Seven days of hourly buckets at most; older hours fall off.
        $hourly = array_slice($hourly, -168);

        $agents = array_values(array_unique(array_map(
            fn (array $a): string => $a['agent'],
            array_filter($day, fn (array $a): bool => $a['agent'] !== 'unknown'),
        )));
        sort($agents);

        $agentIps = array_values(array_unique(array_filter(array_map(
            fn (array $a): ?string => $a['ip'] ?? null,
            $day,
        ))));
        sort($agentIps);

        $byAgent = [];

        foreach ($day as $alert) {
            $name = $alert['agent'];

            if ($name === 'unknown') {
                continue;
            }

            $byAgent[$name] ??= ['total' => 0, 'low' => 0, 'med' => 0, 'high' => 0, 'last' => 0, 'ip' => null, 'last_rule' => '', 'last_desc' => '', 'last_sev' => 'low'];
            $byAgent[$name]['total']++;

            if ($alert['severity'] === 'high') {
                $byAgent[$name]['high']++;
            } elseif ($alert['severity'] === 'medium') {
                $byAgent[$name]['med']++;
            } else {
                $byAgent[$name]['low']++;
            }

            if ($alert['ts'] >= $byAgent[$name]['last']) {
                $byAgent[$name]['last'] = $alert['ts'];
                $byAgent[$name]['last_rule'] = $alert['rule'];
                $byAgent[$name]['last_desc'] = $alert['description'];
                $byAgent[$name]['last_sev'] = $alert['severity'];

                if ($alert['ip'] !== null) {
                    $byAgent[$name]['ip'] = $alert['ip'];
                }
            }
        }

        ksort($byAgent);

        $recent = array_slice(array_reverse($alerts), 0, 10);

        return [
            'ok' => true,
            'stale' => false,
            'error' => null,
            'collected_at' => $now,
            'file_span' => ['oldest' => $alerts[0]['ts'], 'newest' => end($alerts)['ts']],
            'day' => [
                'total' => count($day),
                'low' => count(array_filter($day, fn (array $a): bool => $a['severity'] === 'low')),
                'med' => count(array_filter($day, fn (array $a): bool => $a['severity'] === 'medium')),
                'high' => count(array_filter($day, fn (array $a): bool => $a['severity'] === 'high')),
            ],
            'prev_day_total' => count($prev),
            'agents_24h' => $agents,
            'agent_ips_24h' => $agentIps,
            'by_agent' => $byAgent,
            'hourly' => $hourly,
            'recent' => $recent,
        ];
    }

    /**
     * Which paired containers the SIEM actually heard from.
     *
     * Live labs name their attacker and victim IPs; the alert log names the
     * reporter IPs. A container without an enrolled agent never appears in
     * the log, so it renders as missing rather than as covered.
     *
     * @param  array<string, mixed>  $alerts
     * @return array<string, mixed>|null
     */
    private function collectVisibility(array $alerts, int $now): ?array
    {
        try {
            $sessions = LabSession::query()
                ->where('status', 'READY')
                ->whereNotNull('attacker_ip')
                ->whereNotNull('victim_ip')
                ->get(['attacker_ip', 'victim_ip']);
        } catch (\Throwable) {
            return null;
        }

        $expected = [];

        foreach ($sessions as $session) {
            $expected[] = (string) $session->attacker_ip;
            $expected[] = (string) $session->victim_ip;
        }

        $expected = array_values(array_unique($expected));
        $ips = array_values(array_filter($alerts['agent_ips_24h'] ?? [], 'is_string'));
        $reporting = array_values(array_intersect($expected, $ips));

        return [
            'ok' => true,
            'stale' => false,
            'error' => null,
            'collected_at' => $now,
            'expected' => count($expected),
            'reporting' => count($reporting),
            'missing' => array_values(array_diff($expected, $ips)),
        ];
    }

    /**
     * Failed sign-ins from the portal's own log plus the host auth logs.
     *
     * @return array<string, mixed>|null
     */
    private function collectFailedLogins(callable $read, int $now): ?array
    {
        try {
            $portalRows = ActivityLog::query()
                ->where('action', 'LOGIN')
                ->whereNotIn('status', ['completed'])
                ->where('created_at', '>=', Carbon::createFromTimestamp($now)->subDay())
                ->pluck('created_at');
            $portal = count($portalRows);
        } catch (\Throwable) {
            return null;
        }

        $host = 0;
        $hourly = [];
        $readAny = false;

        foreach (self::AUTH_LOGS as $path) {
            $content = $read($path);

            if ($content === null) {
                continue;
            }

            $readAny = true;

            foreach (explode("\n", $content) as $line) {
                if (! str_contains($line, 'Failed password')) {
                    continue;
                }

                $ts = $this->authTimestamp($line, $now);

                if ($ts === null || $ts < $now - 86400) {
                    continue;
                }

                $host++;
                $hour = intdiv($ts, 3600) * 3600;
                $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
            }
        }

        if (! $readAny) {
            return null;
        }

        ksort($hourly);

        foreach ($portalRows as $createdAt) {
            try {
                $ts = Carbon::parse($createdAt)->getTimestamp();
            } catch (\Throwable) {
                continue;
            }

            $hour = intdiv($ts, 3600) * 3600;
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
        }

        ksort($hourly);

        $pairs = [];

        foreach ($hourly as $ts => $count) {
            $pairs[] = [(int) $ts, (int) $count];
        }

        return [
            'ok' => true,
            'stale' => false,
            'error' => null,
            'collected_at' => $now,
            'portal_24h' => $portal,
            'host_24h' => $host,
            'total_24h' => $portal + $host,
            'hourly' => $pairs,
        ];
    }

    /**
     * syslog timestamps carry no year: assume this one, step back if future.
     */
    private function authTimestamp(string $line, int $now): ?int
    {
        if (preg_match('/^(\w{3})\s+(\d+)\s+(\d+):(\d+):(\d+)/', $line, $matches) !== 1) {
            return null;
        }

        $ts = strtotime(sprintf('%s %s %s %s:%s:%s', $matches[1], $matches[2], date('Y', $now), $matches[3], $matches[4], $matches[5]));

        if ($ts === false) {
            return null;
        }

        if ($ts > $now + 86400) {
            $ts = strtotime(sprintf('%s %s %s %s:%s:%s', $matches[1], $matches[2], ((int) date('Y', $now)) - 1, $matches[3], $matches[4], $matches[5])) ?: $ts;
        }

        return is_int($ts) ? $ts : null;
    }

    /**
     * Live labs whose pairs the packet filter must allow, against what it does.
     *
     * @return array<string, mixed>|null
     */
    private function collectIsolation(callable $run, int $now): ?array
    {
        try {
            $sessions = LabSession::query()
                ->where('status', 'READY')
                ->whereNotNull('attacker_ip')
                ->whereNotNull('victim_ip')
                ->get(['attacker_ip', 'victim_ip']);
        } catch (\Throwable) {
            return null;
        }

        $expected = [];

        foreach ($sessions as $session) {
            $expected[] = [(string) $session->attacker_ip, (string) $session->victim_ip];
        }

        $result = $run(
            ['ssh', '-i', '/home/iaas/.ssh/imnehnet_ansible',
                '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=6',
                '-o', 'StrictHostKeyChecking=accept-new',
                'labatk@192.168.100.30', 'sudo', '-n', 'nft', 'list', 'table', 'inet', 'laa-ctf'],
            20,
        );

        if (! $result['successful']) {
            return null;
        }

        $ruleset = $result['stdout'];
        $allowed = $this->allowedElements($ruleset);
        $missing = [];

        foreach ($expected as [$attackerIp, $victimIp]) {
            $found = false;

            foreach ($allowed as $element) {
                if (str_contains($element, $attackerIp) && str_contains($element, $victimIp)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missing[] = $attackerIp.' -> '.$victimIp;
            }
        }

        $matched = [];

        foreach ($allowed as $element) {
            foreach ($expected as [$attackerIp, $victimIp]) {
                if (str_contains($element, $attackerIp) && str_contains($element, $victimIp)) {
                    $matched[] = $element;
                    break;
                }
            }
        }

        $extra = array_values(array_diff($allowed, $matched));

        return [
            'ok' => true,
            'stale' => false,
            'error' => null,
            'collected_at' => $now,
            'expected' => count($expected),
            'allowed' => count($expected) - count($missing),
            'missing' => $missing,
            'extra' => $extra,
            'percent' => count($expected) === 0 ? null : round(100 * (count($expected) - count($missing)) / count($expected), 1),
            'default_deny' => str_contains($ruleset, 'cross-student deny')
                && str_contains($ruleset, 'victim-initiated deny')
                && str_contains($ruleset, 'attacker to mgmt'),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedElements(string $ruleset): array
    {
        if (preg_match('/elements\s*=\s*\{(.*?)\}/s', $ruleset, $matches) !== 1) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @return array<string, mixed>
     */
    private function staled(?array $previous, string $error): array
    {
        if (is_array($previous) && ($previous['ok'] ?? false)) {
            $previous['stale'] = true;
            $previous['error'] = $error;

            return $previous;
        }

        return [
            'ok' => false,
            'stale' => true,
            'error' => $error,
            'collected_at' => null,
        ];
    }
}
