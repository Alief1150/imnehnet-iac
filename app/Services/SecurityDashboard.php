<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * What the Security page shows, computed from collected telemetry.
 *
 * ── One rule for every figure ────────────────────────────────────────────────
 *
 * Each number names what it counts: alerts read from the SIEM's own log file,
 * failed sign-ins from the portal log and the host auth logs, isolation from
 * live labs against the live packet filter. A figure with nothing behind it
 * renders as unknown with the reason beside it. Deltas compare equal windows
 * and say which; ranges never outrun the retained hours.
 *
 * Everything here works on plain arrays, so tests feed canned telemetry
 * instead of reading production logs on every run.
 */
final class SecurityDashboard
{
    public const RANGES = ['24h' => 86400, '7d' => 604800];

    /**
     * The four headline cards.
     *
     * @param  array<string, mixed>  $telemetry
     * @param  list<array{0: int, 1: float}>  $isolationHistory
     * @return array<string, array<string, mixed>>
     */
    public function kpis(array $telemetry, array $isolationHistory): array
    {
        $alerts = $telemetry['alerts'] ?? [];
        $failed = $telemetry['failed_logins'] ?? [];
        $isolation = $telemetry['isolation'] ?? [];

        $day = $alerts['day'] ?? null;

        return [
            'alerts' => [
                'label' => 'Active Alerts',
                'icon' => 'shield-alert',
                'value' => is_array($day) ? ($day['total'] ?? null) : null,
                'unit' => null,
                'subtitle' => 'SIEM alerts in 24h',
                'delta' => $this->deltaCounts((int) ($alerts['day']['total'] ?? 0), (int) ($alerts['prev_day_total'] ?? 0), $alerts['file_span'] ?? []),
                'spark' => $this->hourlyTotals($alerts['hourly'] ?? []),
                'state' => ($alerts['ok'] ?? false) ? 'ready' : 'unknown',
            ],
            'agents' => [
                'label' => 'Wazuh Agents',
                'icon' => 'activity',
                'value' => isset($alerts['agents_24h']) && is_array($alerts['agents_24h']) ? count($alerts['agents_24h']) : null,
                'unit' => null,
                'subtitle' => 'distinct reporters in 24h',
                'delta' => ['value' => null, 'label' => 'point-in-time set'],
                'spark' => $this->hourlyAgents($alerts['hourly'] ?? []),
                'state' => ($alerts['ok'] ?? false) ? 'ready' : 'unknown',
            ],
            'failed' => [
                'label' => 'Failed Logins',
                'icon' => 'users',
                'value' => $failed['total_24h'] ?? null,
                'unit' => null,
                'subtitle' => sprintf(
                    'portal %s · hosts %s in 24h',
                    $this->countOrDash($failed['portal_24h'] ?? null),
                    $this->countOrDash($failed['host_24h'] ?? null),
                ),
                'delta' => ['value' => null, 'label' => 'watching for repeats'],
                'spark' => $this->hourlyCounts($failed['hourly'] ?? []),
                'state' => ($failed['ok'] ?? false) ? 'ready' : 'unknown',
            ],
            'isolation' => [
                'label' => 'Isolation Policy',
                'icon' => 'shield-check',
                'value' => $isolation['percent'] ?? null,
                'unit' => '%',
                'subtitle' => isset($isolation['allowed'], $isolation['expected'])
                    ? sprintf('%d of %d live pairs allowed', $isolation['allowed'], $isolation['expected'])
                    : 'no reading',
                'delta' => $this->deltaIsolation($isolationHistory),
                'spark' => $isolationHistory,
                'state' => ($isolation['ok'] ?? false) ? 'ready' : 'unknown',
            ],
        ];
    }

    /**
     * Alert counts per hour inside the window.
     *
     * @param  list<array{0: int, array<string, mixed>}>  $hourly
     * @return array{series: list<array{0: float, 1: float}>, from_label: string|null, to_label: string|null, coverage: string}
     */
    public function trend(array $hourly, string $range): array
    {
        $seconds = self::RANGES[$range] ?? self::RANGES['24h'];
        $now = time();
        $points = [];

        foreach ($hourly as $entry) {
            [$ts, $bucket] = $entry;

            if ($ts < $now - $seconds) {
                continue;
            }

            $points[] = [$ts, (float) ($bucket['total'] ?? 0)];
        }

        if ($points === []) {
            return ['series' => [], 'from_label' => null, 'to_label' => null, 'coverage' => 'no samples in this window yet'];
        }

        $count = count($points);
        $peak = max(array_map(fn (array $point): float => (float) $point[1], $points));

        $series = array_map(
            fn (array $point, int $index): array => [
                $count > 1 ? $index * 100 / ($count - 1) : 50.0,
                $peak > 0 ? 100 - min(100, max(0, (float) $point[1] / $peak * 100)) : 100,
            ],
            $points,
            array_keys($points),
        );

        return [
            'series' => $series,
            'from_label' => Carbon::createFromTimestamp($points[0][0])->format('d M H:i'),
            'to_label' => Carbon::createFromTimestamp(end($points)[0])->format('d M H:i'),
            'coverage' => sprintf('%d hourly buckets', $count),
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array{expected: int|null, reporting: int|null, missing: list<string>, state: string}
     */
    public function visibility(array $telemetry): array
    {
        $vis = $telemetry['visibility'] ?? [];

        return [
            'expected' => isset($vis['expected']) && is_numeric($vis['expected']) ? (int) $vis['expected'] : null,
            'reporting' => isset($vis['reporting']) && is_numeric($vis['reporting']) ? (int) $vis['reporting'] : null,
            'missing' => isset($vis['missing']) && is_array($vis['missing']) ? array_values($vis['missing']) : [],
            'state' => ($vis['ok'] ?? false) ? 'ready' : 'unknown',
        ];
    }

    /**
     * Severity shares of the last 24 hours.
     *
     * @param  array<string, mixed>|null  $day
     * @return array{total: int, slices: list<array{label: string, count: int, share: float, color: string}>}
     */
    public function donut(?array $day): array
    {
        $buckets = [
            'Low' => ['count' => (int) ($day['low'] ?? 0), 'color' => '#10b981'],
            'Medium' => ['count' => (int) ($day['med'] ?? 0), 'color' => '#3b82f6'],
            'High' => ['count' => (int) ($day['high'] ?? 0), 'color' => '#f59e0b'],
        ];

        $total = array_sum(array_column($buckets, 'count'));
        $slices = [];

        foreach ($buckets as $label => $bucket) {
            $slices[] = [
                'label' => $label,
                'count' => $bucket['count'],
                'share' => $total > 0 ? round(100 * $bucket['count'] / $total, 1) : 0.0,
                'color' => $bucket['color'],
            ];
        }

        return ['total' => $total, 'slices' => $slices];
    }

    /**
     * @param  list<array{0: int, array<string, mixed>}>  $hourly
     * @return list<array{0: int, 1: float}>
     */
    private function hourlyTotals(array $hourly): array
    {
        $points = [];

        foreach (array_slice($hourly, -24) as $entry) {
            $points[] = [$entry[0], (float) ($entry[1]['total'] ?? 0)];
        }

        return $points;
    }

    /**
     * @param  list<array{0: int, array<string, mixed>}>  $hourly
     * @return list<array{0: int, 1: float}>
     */
    private function hourlyAgents(array $hourly): array
    {
        $points = [];

        foreach (array_slice($hourly, -24) as $entry) {
            $points[] = [$entry[0], (float) count($entry[1]['agents'] ?? [])];
        }

        return $points;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $hourly
     * @return list<array{0: int, 1: float}>
     */
    private function hourlyCounts(array $hourly): array
    {
        $points = [];

        foreach (array_slice($hourly, -24) as $entry) {
            $points[] = [$entry[0], (float) $entry[1]];
        }

        return $points;
    }

    /**
     * Today against yesterday, only when the file actually covers yesterday.
     *
     * @param  array{oldest?: int, newest?: int}  $span
     * @return array{value: float|null, label: string}
     */
    private function deltaCounts(int $today, int $yesterday, array $span): array
    {
        $oldest = $span['oldest'] ?? null;
        $newest = $span['newest'] ?? null;

        if (! is_int($oldest) || ! is_int($newest) || $newest - $oldest < 172800) {
            return ['value' => null, 'label' => 'insufficient history'];
        }

        $delta = $today - $yesterday;

        return [
            'value' => (float) $delta,
            'label' => sprintf('%s%d vs previous 24h', $delta >= 0 ? '+' : '−', abs($delta)),
        ];
    }

    /**
     * @param  list<array{0: int, 1: float}>  $history
     * @return array{value: float|null, label: string}
     */
    private function deltaIsolation(array $history): array
    {
        if (count($history) < 2) {
            return ['value' => null, 'label' => 'collecting baseline'];
        }

        $first = $history[0][1];
        $last = end($history)[1];
        $span = end($history)[0] - $history[0][0];

        if ($span < 3600) {
            return ['value' => null, 'label' => 'collecting baseline'];
        }

        $delta = round($last - $first, 1);

        return [
            'value' => $delta,
            'label' => sprintf('%s%.1f pp over %s', $delta >= 0 ? '+' : '−', abs($delta), $this->spanLabel($span)),
        ];
    }

    private function spanLabel(int $seconds): string
    {
        if ($seconds >= 86400) {
            return sprintf('%dd', intdiv($seconds, 86400));
        }

        return sprintf('%dh', max(1, intdiv($seconds, 3600)));
    }

    private function countOrDash(mixed $value): string|int
    {
        return is_numeric($value) ? (int) $value : '—';
    }
}
