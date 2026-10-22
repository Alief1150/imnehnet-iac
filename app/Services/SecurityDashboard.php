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
     * Every plane that must appear on the coverage panel, in display order.
     * The agent column names the SIEM reporter; anything else that reports
     * is listed after these as an unmapped reporter.
     */
    public const NODES = [
        ['agent' => 'Linux-Mint-Host', 'node' => 'server-imnehnet', 'role' => 'Physical host'],
        ['agent' => 'iaas-mgmt', 'node' => 'IAAS-MGMT', 'role' => 'Management plane'],
        ['agent' => 'LAB-ENV', 'node' => 'LAB-ENV', 'role' => 'Defender lab plane'],
        ['agent' => 'LAB-ATK', 'node' => 'LAB-ATK', 'role' => 'Attacker lab plane'],
    ];

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
                'tint' => 'rose',
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
                'tint' => 'teal',
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
                'tint' => 'blue',
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
                'tint' => 'emerald',
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
     * Chart geometry the blade draws without thinking: a smoothed line, a
     * filled area, labelled axes and date ticks, all scaled to a nice
     * ceiling so quiet days still show their shape.
     *
     * @param  list<array{0: int, array<string, mixed>}>  $hourly
     * @return array<string, mixed>
     */
    /**
     * The drawing surface, in viewBox units.
     *
     * The chart is drawn in absolute coordinates rather than a scaled group,
     * so the line, the dots and the labels all keep their own shape: a
     * scaled group turns every dot into an ellipse, and an SVG that is
     * letterboxed inside a wider panel leaves the curve floating in the
     * middle of an empty box. The box keeps this ratio, so the drawing
     * fills it exactly at any width.
     */
    private const CHART_WIDTH = 320;

    private const CHART_HEIGHT = 140;

    private const PLOT_LEFT = 36;

    private const PLOT_RIGHT = 316;

    private const PLOT_TOP = 10;

    private const PLOT_BOTTOM = 116;

    /**
     * Hourly curve, for the panel when no daily curve has been collected.
     *
     * @param list<array{0: int, array<string, mixed>}> $hourly
     * @return array<string, mixed>
     */
    public function chart(array $hourly, string $range): array
    {
        $seconds = self::RANGES[$range] ?? self::RANGES['24h'];
        $now = time();
        $points = [];

        foreach ($hourly as $entry) {
            [$ts, $bucket] = $entry;

            if ($ts < $now - $seconds) {
                continue;
            }

            $points[] = ['ts' => (int) $ts, 'v' => (float) ($bucket['total'] ?? 0)];
        }

        return $this->render($points, 'alerts at', 'd M H:i', 5);
    }

    /**
     * The daily curve, the one the panel shows.
     *
     * @param list<array{0: int, 1: float, 2?: float, 3?: float, 4?: float}> $daily Day timestamp, total, low, med, high.
     * @return array<string, mixed>
     */
    public function chartDaily(array $daily): array
    {
        $points = [];

        foreach ($daily as $entry) {
            if (! is_array($entry) || count($entry) < 2) {
                continue;
            }

            $points[] = ['ts' => (int) $entry[0], 'v' => (float) $entry[1]];
        }

        return $this->render($points, 'alerts on', 'd M', 6);
    }

    /**
     * Turns points into the geometry the view draws.
     *
     * @param list<array{ts: int, v: float}> $points
     * @return array<string, mixed>
     */
    private function render(array $points, string $noun, string $format = 'd M H:i', int $maxTicks = 5): array
    {
        if ($points === []) {
            return ['empty' => true, 'coverage' => 'no samples in this window yet'];
        }

        $stride = (int) max(1, floor(count($points) / 64));
        $sampled = [];

        foreach ($points as $index => $point) {
            if ($index % $stride === 0 || $index === count($points) - 1) {
                $sampled[] = $point;
            }
        }

        $count = count($sampled);
        $peak = max(array_map(fn (array $point): float => $point['v'], $sampled));
        $ymax = $this->niceCeil($peak);

        $plotWidth = self::PLOT_RIGHT - self::PLOT_LEFT;
        $plotHeight = self::PLOT_BOTTOM - self::PLOT_TOP;
        $plot = [];

        foreach ($sampled as $index => $point) {
            $x = $count > 1
                ? self::PLOT_LEFT + $index * $plotWidth / ($count - 1)
                : (self::PLOT_LEFT + self::PLOT_RIGHT) / 2;
            $ratio = $peak > 0 ? min(1.0, max(0.0, $point['v'] / $ymax)) : 0.0;
            $plot[] = [
                'x' => round($x, 2),
                'y' => round(self::PLOT_BOTTOM - $ratio * $plotHeight, 2),
                'v' => $point['v'],
                'ts' => $point['ts'],
            ];
        }

        $segments = $this->smoothPath(array_map(
            fn (array $point): array => [$point['x'], $point['y']],
            $plot,
        ));

        $first = $plot[0];
        $last = $plot[$count - 1];

        $line = sprintf('M%.2f,%.2f %s', $first['x'], $first['y'], $segments);
        $area = sprintf(
            'M%.2f,%.2f L%.2f,%.2f %s L%.2f,%.2f Z',
            $first['x'],
            self::PLOT_BOTTOM,
            $first['x'],
            $first['y'],
            $segments,
            $last['x'],
            self::PLOT_BOTTOM,
        );

        $yticks = [];

        foreach ([0, 0.25, 0.5, 0.75, 1] as $fraction) {
            $yticks[] = [
                'y' => round(self::PLOT_BOTTOM - $fraction * $plotHeight, 2),
                'label' => $this->compactCount($ymax * $fraction),
            ];
        }

        $tickCount = min($count, $maxTicks);
        $xticks = [];

        for ($i = 0; $i < $tickCount; $i++) {
            $point = $plot[(int) round($i * ($count - 1) / max(1, $tickCount - 1))];
            $xticks[] = [
                'x' => $point['x'],
                'label' => Carbon::createFromTimestamp($point['ts'])->format($format),
                'anchor' => $tickCount === 1 ? 'middle' : ($i === 0 ? 'start' : ($i === $tickCount - 1 ? 'end' : 'middle')),
            ];
        }

        return [
            'empty' => false,
            'width' => self::CHART_WIDTH,
            'height' => self::CHART_HEIGHT,
            'left' => self::PLOT_LEFT,
            'right' => self::PLOT_RIGHT,
            'top' => self::PLOT_TOP,
            'bottom' => self::PLOT_BOTTOM,
            'line' => $line,
            'area' => $area,
            'yticks' => $yticks,
            'xticks' => $xticks,
            'ymax' => $ymax,
            'peak' => $peak,
            'dots' => array_map(
                fn (array $point): array => [
                    'x' => $point['x'],
                    'y' => $point['y'],
                    'label' => sprintf(
                        '%s %s %s',
                        number_format($point['v']),
                        $noun,
                        Carbon::createFromTimestamp($point['ts'])->format($format),
                    ),
                ],
                $plot,
            ),
            'last' => [
                'v' => $last['v'],
                'label' => Carbon::createFromTimestamp($last['ts'])->format($format),
            ],
            'from_label' => Carbon::createFromTimestamp($first['ts'])->format($format),
            'to_label' => Carbon::createFromTimestamp($last['ts'])->format($format),
            'coverage' => sprintf('%d buckets', $count),
        ];
    }
    /**
     * One row per plane: reporting with its numbers, quiet when the SIEM
     * heard nothing, unknown when nothing was collected at all.
     *
     * @param array<string, mixed> $telemetry
     * @return array{ok: bool, rows: list<array<string, mixed>>}
     */
    public function coverage(array $telemetry): array
    {
        $alerts = $telemetry['alerts'] ?? null;
        $ok = is_array($alerts) && ($alerts['ok'] ?? false);
        $byAgent = $ok && isset($alerts['by_agent']) && is_array($alerts['by_agent']) ? $alerts['by_agent'] : [];
        $rows = [];

        foreach (self::NODES as $node) {
            $stats = $byAgent[$node['agent']] ?? null;

            $rows[] = [
                'node' => $node['node'],
                'role' => $node['role'],
                'agent' => $node['agent'],
                'state' => ! $ok ? 'unknown' : ($stats === null ? 'quiet' : 'reporting'),
                'total' => $stats['total'] ?? null,
                'low' => $stats['low'] ?? null,
                'med' => $stats['med'] ?? null,
                'high' => $stats['high'] ?? null,
                'last' => $stats['last'] ?? null,
                'ip' => $stats['ip'] ?? null,
            ];
        }

        foreach ($byAgent as $name => $stats) {
            if (collect(self::NODES)->contains(fn (array $node): bool => $node['agent'] === $name)) {
                continue;
            }

            $rows[] = [
                'node' => $name,
                'role' => 'Unmapped reporter',
                'agent' => $name,
                'state' => 'reporting',
                'total' => $stats['total'] ?? null,
                'low' => $stats['low'] ?? null,
                'med' => $stats['med'] ?? null,
                'high' => $stats['high'] ?? null,
                'last' => $stats['last'] ?? null,
                'ip' => $stats['ip'] ?? null,
            ];
        }

        return ['ok' => $ok, 'rows' => $rows];
    }

    private function niceCeil(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($value));

        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($step * $magnitude >= $value) {
                return (float) ($step * $magnitude);
            }
        }

        return 10.0 * $magnitude;
    }

    private function compactCount(float $value): string
    {
        if ($value >= 1000) {
            return rtrim(rtrim(sprintf('%.1f', $value / 1000), '0'), '.').'k';
        }

        return (string) (int) round($value);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function smoothPath(array $points): string
    {
        $count = count($points);

        if ($count === 1) {
            return '';
        }

        if ($count === 2) {
            return sprintf('L%.2f,%.2f', $points[1][0], $points[1][1]);
        }

        $path = '';

        for ($i = 0; $i < $count - 1; $i++) {
            $before = $points[max(0, $i - 1)];
            $from = $points[$i];
            $to = $points[$i + 1];
            $after = $points[min($count - 1, $i + 2)];
            $path .= sprintf(
                ' C%.2f,%.2f %.2f,%.2f %.2f,%.2f',
                $from[0] + ($to[0] - $before[0]) / 6,
                $from[1] + ($to[1] - $before[1]) / 6,
                $to[0] - ($after[0] - $from[0]) / 6,
                $to[1] - ($after[1] - $from[1]) / 6,
                $to[0],
                $to[1]
            );
        }

        return $path;
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
            'low' => ['label' => 'Low', 'count' => (int) ($day['low'] ?? 0), 'color' => '#10b981'],
            'medium' => ['label' => 'Medium', 'count' => (int) ($day['med'] ?? 0), 'color' => '#3b82f6'],
            'high' => ['label' => 'High', 'count' => (int) ($day['high'] ?? 0), 'color' => '#f59e0b'],
        ];

        $total = array_sum(array_column($buckets, 'count'));
        $slices = [];

        foreach ($buckets as $key => $bucket) {
            $slices[] = [
                'key' => $key,
                'label' => $bucket['label'],
                'count' => $bucket['count'],
                'share' => $total > 0 ? round(100 * $bucket['count'] / $total, 1) : 0.0,
                'color' => $bucket['color'],
            ];
        }

        if ($total > 0) {
            $drift = round(100 - array_sum(array_column($slices, 'share')), 1);

            if ($drift !== 0.0) {
                $biggest = array_search(max(array_column($slices, 'count')), array_column($slices, 'count'), true);
                $slices[$biggest]['share'] = round($slices[$biggest]['share'] + $drift, 1);
            }
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
