<x-filament-panels::page>
    {{-- Scoped layout fallback: works even when the deployed Vite bundle has not picked up new utility classes. --}}
    <style>
        .im-security .im-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .im-security .im-chartrow {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .im-security .im-halves {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }
        .im-security .im-kpis > *,
        .im-security .im-chartrow > *,
        .im-security .im-halves > * {
            min-width: 0;
        }
        .im-security .im-panel {
            min-width: 0;
            overflow: hidden;
        }
        .im-security .fi-fi {
            width: 1.15em;
            height: 1.15em;
            flex-shrink: 0;
        }
        .im-security h3 .fi-fi {
            width: 1.1em;
            height: 1.1em;
        }
        .im-security .im-dot {
            transition: r 0.15s ease, fill 0.15s ease;
            cursor: crosshair;
            fill: transparent;
        }
        .im-security .im-dot:hover {
            r: 4.5;
            fill: #2dd4bf;
        }
        .im-security .im-spark-svg {
            display: block;
            width: 100%;
            height: 44px;
            max-height: 44px;
            overflow: hidden;
        }
        .im-security .im-donut-svg {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            overflow: hidden;
        }
        .im-security .im-chart-box {
            position: relative;
            width: 100%;
            aspect-ratio: 320 / 140;
        }
        @media (max-width: 1279px) {
            .im-security .im-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 1199px) {
            .im-security .im-chartrow,
            .im-security .im-halves {
                grid-template-columns: minmax(0, 1fr);
            }
        }
        @media (max-width: 767px) {
            .im-security .im-kpis {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
    @php
        $kpis = $this->kpis();
        $chart = $this->alertTrend();
        $donut = $this->severityDonut();
        $integrations = collect($this->integrations());
        $grafana = $integrations->firstWhere('key', 'grafana');
        $wazuhDash = $integrations->firstWhere('key', 'wazuh_dashboard');
        $wazuhApi = $integrations->firstWhere('key', 'wazuh_api');
        $recent = $this->recentAlerts();
        $visibility = $this->visibility();
        $coverage = $this->coverage();
        $range = $this->selectedRange();
        $ranges = ['24h' => '24H', '7d' => '7D', '30d' => '30D', '90d' => '90D', '1y' => '1Y'];
        $baseUrl = \App\Filament\Pages\Security::getUrl(panel: 'admin');

        $integrationCards = [
            ['entry' => $grafana, 'name' => 'Grafana', 'icon' => 'chart-bar', 'blurb' => 'View dashboards and visualizations'],
            ['entry' => $wazuhDash, 'name' => 'Wazuh', 'icon' => 'shield', 'blurb' => 'Investigate alerts and manage agents'],
        ];
    @endphp

    <div wire:poll.15s class="im-security space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">Security</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Security alerts, agent status, and monitoring access. Every figure names what measured it.</p>
            </div>
            <div class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-white/10 dark:bg-gray-900" role="group" aria-label="Time range">
                @foreach ($ranges as $key => $label)
                    <a href="{{ $baseUrl }}?range={{ $key }}"
                       class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $range === $key ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @php $fresh = $this->freshness(); @endphp
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Updated {{ $fresh['at'] !== null ? \Illuminate\Support\Carbon::createFromTimestamp($fresh['at'])->diffForHumans() : 'never' }}
            @if ($fresh['stale'])
                — <span class="font-semibold text-amber-600 dark:text-amber-400">stale{{ $fresh['errors'] !== [] ? ': '.implode('; ', $fresh['errors']) : ': the collector has not reported yet' }}</span>
            @endif
        </p>

        {{-- KPI cards --}}
        <section class="im-kpis grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Security overview">
            @foreach ($kpis as $kpi)
                @php
                    $spark = array_values(array_filter($kpi['spark'] ?? [], fn ($p) => is_numeric($p[1] ?? null)));
                    $sparkMax = $spark === [] ? 0 : max(array_map(fn ($p) => (float) $p[1], $spark));
                    $sparkPoints = collect($spark)->map(function ($point, $index) use ($spark, $sparkMax) {
                        $x = count($spark) > 1 ? $index * 100 / (count($spark) - 1) : 50;
                        $y = $sparkMax > 0 ? 100 - min(100, max(0, (float) $point[1] / $sparkMax * 100)) : 100;
                        return sprintf('%.2f,%.2f', $x, $y);
                    })->implode(' ');
                    $delta = $kpi['delta'] ?? ['value' => null, 'label' => ''];
                    $deltaFlat = ! is_numeric($delta['value'] ?? null);
                    $deltaUp = ! $deltaFlat && (float) $delta['value'] > 0;
                    $deltaDown = ! $deltaFlat && (float) $delta['value'] < 0;
                @endphp
                <article class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">{!! \App\Support\Flyon::html($kpi['icon'] ?? 'shield-alert') !!}</span>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</h3>
                        </div>
                        @if (count($spark) > 1)
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="im-spark-svg w-24" role="img" aria-label="{{ $kpi['label'] }} recent trend">
                                <polyline points="{{ $sparkPoints }}" fill="none" stroke="#2dd4bf" stroke-width="6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @endif
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ is_numeric($kpi['value'] ?? null) ? number_format((float) $kpi['value']) : 'n/a' }}</span>
                        @if (is_numeric($kpi['value'] ?? null) && ! empty($kpi['unit'])) <span class="text-sm text-gray-500">{{ $kpi['unit'] }}</span> @endif
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $kpi['subtitle'] ?? '' }}</p>
                    <p class="mt-2 flex items-center gap-1.5 text-xs">
                        @if ($deltaFlat)
                            <span class="text-gray-400">— {{ $delta['label'] }}</span>
                        @else
                            <span class="{{ $deltaUp ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $deltaUp ? '▲' : ($deltaDown ? '▼' : '—') }} {{ $delta['label'] }}</span>
                        @endif
                        @if (($kpi['state'] ?? '') !== 'ready')
                            <span class="rounded-full bg-gray-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span>
                        @endif
                    </p>
                </article>
            @endforeach
        </section>

        {{-- Trend + severity --}}
        <div class="im-chartrow grid gap-6 grid-cols-1 md:grid-cols-12">
            <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:col-span-8">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                            <span class="text-teal-600 dark:text-teal-400">{!! \App\Support\Flyon::html('chart-line') !!}</span>
                            Alert Trend
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total security alerts over time</p>
                    </div>
                    <div class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-white/10 dark:bg-gray-900" role="group" aria-label="Time range">
                        @foreach ($ranges as $key => $label)
                            <a href="{{ $baseUrl }}?range={{ $key }}"
                               class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $range === $key ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if (! ($chart['empty'] ?? true))
                        {{ $chart['from_label'] }} → {{ $chart['to_label'] }}
                    @else
                        {{ $chart['coverage'] }}
                    @endif
                </p>
                <div class="im-chart-box">
                    @if (! ($chart['empty'] ?? true))
                        <svg viewBox="0 0 320 140" class="h-full w-full" role="img" aria-label="Alert counts over time">
                            @foreach ($chart['yticks'] as $tick)
                                <line x1="{{ $chart['left'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['right'] }}" y2="{{ $tick['y'] }}" stroke="currentColor" stroke-opacity=".12" stroke-width=".5" />
                                <text x="{{ $chart['left'] - 4 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end" font-size="7" class="fill-gray-400">{{ $tick['label'] }}</text>
                            @endforeach
                            @foreach ($chart['xticks'] as $tick)
                                <text x="{{ $tick['x'] }}" y="132" text-anchor="{{ $tick['anchor'] ?? 'middle' }}" font-size="7" class="fill-gray-400">{{ $tick['label'] }}</text>
                            @endforeach
                            <path d="{{ $chart['area'] }}" fill="#2dd4bf" fill-opacity="0.15" stroke="none" />
                            <path d="{{ $chart['line'] }}" fill="none" stroke="#2dd4bf" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($chart['dots'] as $dot)
                                <circle class="im-dot" cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="2.6" fill="#2dd4bf" stroke="#ffffff" stroke-width="1" vector-effect="non-scaling-stroke">
                                    <title>{{ $dot['label'] }}</title>
                                </circle>
                            @endforeach
                        </svg>
                    @else
                        <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-gray-200 text-center text-sm text-gray-400 dark:border-gray-800">
                            <p>No samples in this window yet.<br><span class="text-xs">The collector reads the SIEM log every minute.</span></p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:col-span-4">
                <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                    <span class="text-blue-600 dark:text-blue-400">{!! \App\Support\Flyon::html('chart-bar') !!}</span>
                    Alert Severity
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Distribution of security alerts, last 24h — from the Wazuh indexer. Click a row to filter the table.</p>
                @if ($donut['total'] > 0)
                    @php $offset = 25; @endphp
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-6">
                    <div class="relative w-44 max-w-full shrink-0">
                        <svg viewBox="0 0 42 42" class="im-donut-svg" role="img" aria-label="Alert severities, {{ $donut['total'] }} total">
                            <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke-width="6" class="stroke-gray-100 dark:stroke-gray-800" />
                            @foreach ($donut['slices'] as $slice)
                                @if ($slice['count'] > 0)
                                    <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke-width="6"
                                            pathLength="100"
                                            stroke="{{ $slice['color'] }}"
                                            stroke-dasharray="{{ number_format($slice['share'], 1) }} {{ number_format(100 - $slice['share'], 1) }}"
                                            stroke-dashoffset="{{ number_format($offset, 1) }}">
                                        <title>{{ $slice['label'] }}: {{ $slice['count'] }} ({{ number_format($slice['share'], 1) }}%)</title>
                                    </circle>
                                    @php $offset -= $slice['share']; @endphp
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="font-mono text-2xl font-bold text-gray-950 dark:text-white">{{ $donut['total'] }}</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Total Alerts</span>
                        </div>
                    </div>
                    <dl class="min-w-[190px] flex-1 space-y-1.5">
                        @foreach ($donut['slices'] as $slice)
                            <button type="button" wire:click="toggleSev('{{ $slice['key'] }}')" title="Show only {{ $slice['label'] }} alerts" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1 text-left text-sm transition hover:bg-gray-500/5 {{ $this->sev === $slice['key'] ? 'bg-gray-500/10' : '' }}">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $slice['color'] }}"></span>
                                <dt class="flex-1 text-gray-600 dark:text-gray-300">{{ $slice['label'] }}</dt>
                                <dd class="font-mono font-semibold text-gray-950 dark:text-white">{{ $slice['count'] }}</dd>
                                <dd class="w-12 text-right font-mono text-xs text-gray-500">{{ number_format($slice['share'], 0) }}%</dd>
                            </button>
                        @endforeach
                    </dl>
                    </div>
                    @if (($donut['slices'][0]['share'] ?? 0) >= 90)
                        <p class="mt-3 rounded-lg bg-gray-500/5 px-3 py-2 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">Routine low-level agent noise dominates this volume — triage starts at Medium and High. Click a row to filter the table.</p>
                    @endif
                @else
                    <div class="mt-4 flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 px-5 text-center dark:border-gray-800">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No alerts in 24h</p>
                        <p class="mt-2 max-w-sm text-xs leading-relaxed text-gray-500 dark:text-gray-400">A quiet day renders as an empty ring, not as zeros with a story.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- Container visibility --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Container visibility">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-emerald-600 dark:text-emerald-400">{!! \App\Support\Flyon::html('eye') !!}</span>
                        Container visibility
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paired lab containers the SIEM heard from in 24h. A container without an enrolled agent renders as missing, never as covered.</p>
                </div>
                <span class="font-mono text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $visibility['expected'] !== null ? $visibility['reporting'].' of '.$visibility['expected'] : 'n/a' }}</span>
            </div>
            @if ($visibility['missing'] !== [])
                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">Not reporting: <span class="font-mono">{{ implode(', ', $visibility['missing']) }}</span></p>
            @endif
            @if (($visibility['state'] ?? '') !== 'ready')
                <p class="mt-2 text-xs"><span class="rounded-full bg-gray-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span></p>
            @endif
        </section>

        {{-- Environment coverage --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Environment coverage">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-violet-600 dark:text-violet-400">{!! \App\Support\Flyon::html('server') !!}</span>
                        Environment coverage
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alerts per plane in 24h, straight from the SIEM log. A quiet plane renders as quiet — the portal never fills a gap with a guess.</p>
                </div>
                @if (! $coverage['ok'])
                    <span class="rounded-full bg-gray-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span>
                @endif
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="table w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr><th class="py-2.5 pr-4 font-medium">Node</th><th class="py-2.5 pr-4 font-medium">Role</th><th class="py-2.5 pr-4 font-medium">Agent</th><th class="py-2.5 pr-4 text-right font-medium">Alerts</th><th class="py-2.5 pr-4 font-medium">Last seen</th><th class="py-2.5 font-medium">State</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($coverage['rows'] as $row)
                            <tr>
                                <td class="py-2.5 pr-4 font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $row['node'] }}</td>
                                <td class="py-2.5 pr-4 text-xs text-gray-600 dark:text-gray-300">{{ $row['role'] }}</td>
                                <td class="py-2.5 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['agent'] }}</td>
                                <td class="py-2.5 pr-4 text-right font-mono text-xs text-gray-900 dark:text-white">{{ $row['total'] !== null ? number_format((float) $row['total']) : '—' }}</td>
                                <td class="whitespace-nowrap py-2.5 pr-4 text-xs text-gray-500 dark:text-gray-400">{{ $row['last'] ? \Illuminate\Support\Carbon::createFromTimestamp($row['last'])->diffForHumans() : '—' }}</td>
                                <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ ($row['state'] ?? '') === 'reporting' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500 dark:text-gray-400' }}">{{ $row['state'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Security Integrations --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Security Integrations">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-teal-600 dark:text-teal-400">{!! \App\Support\Flyon::html('external-link') !!}</span>
                        Security Integrations
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Access external security tools for deeper analysis and monitoring.</p>
                </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($integrationCards as $card)
                    @php $entry = $card['entry'] ?? null; $ok = ($entry['status'] ?? '') === 'healthy'; @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-4 py-3.5 dark:border-gray-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $ok ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400' : 'bg-gray-500/10 text-gray-500' }}">{!! \App\Support\Flyon::html($card['icon']) !!}</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Open {{ $card['name'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['blurb'] }}</p>
                                @if (! empty($entry['url']))
                                    <p class="mt-0.5 truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">{{ parse_url($entry['url'], PHP_URL_HOST) }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $ok ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500 dark:text-gray-400' }}">
                                <span class="size-1.5 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>{{ $ok ? 'Available' : 'Unreachable' }}
                            </span>
                            @if (! empty($entry['url']))
                                <a href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer" title="Open {{ $card['name'] }}" class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">→</a>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if ($wazuhApi !== null)
                    @php
                        $apiLine = 'Management API: '.((($wazuhApi['status'] ?? '') === 'healthy') ? 'reachable (auth-protected)' : 'not reachable');
                        if (isset($wazuhApi['latency_ms']) && $wazuhApi['latency_ms'] !== null) {
                            $apiLine .= ' · '.$wazuhApi['latency_ms'].' ms';
                        }
                    @endphp
                    <p class="mt-3 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ $apiLine }}.
                        An API answering 401 refuses anonymous callers, which is correct behaviour — not an outage.
                    </p>
                @endif
            </div>
        </section>

        {{-- Recent Security Alerts --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Recent Security Alerts">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-amber-600 dark:text-amber-400">{!! \App\Support\Flyon::html('clock') !!}</span>
                        Recent Security Alerts
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Latest event per reporter, newest first.</p>
                </div>
                @if ($wazuhDash !== null && ! empty($wazuhDash['url']))
                    <a href="{{ $wazuhDash['url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-semibold text-teal-600 hover:underline dark:text-teal-400">View all alerts →</a>
                @endif
            </div>
            <div class="mt-3 inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-white/10 dark:bg-gray-900" role="group" aria-label="Severity filter">
                @foreach (['all' => 'All', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $sevKey => $sevLabel)
                    <button type="button" wire:click="setSev('{{ $sevKey }}')" class="rounded-lg px-3 py-1 text-xs font-semibold {{ $this->sev === $sevKey ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $sevLabel }}</button>
                @endforeach
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="table w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr><th class="py-2.5 pr-4 font-medium">Time</th><th class="py-2.5 pr-4 font-medium">Source</th><th class="py-2.5 pr-4 font-medium">Event</th><th class="py-2.5 pr-4 font-medium">Severity</th><th class="py-2.5 font-medium">Rule</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recent as $alert)
                            <tr>
                                <td class="whitespace-nowrap py-2.5 pr-4 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($alert['ts'])->diffForHumans() }}</td>
                                <td class="py-2.5 pr-4 font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $alert['agent'] }}</td>
                                <td class="max-w-md truncate py-2.5 pr-4 text-xs text-gray-600 dark:text-gray-300" title="{{ $alert['description'] }}">{{ $alert['description'] }}</td>
                                <td class="py-2.5 pr-4"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $alert['severity'] === 'high' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : ($alert['severity'] === 'medium' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400') }}">{{ $alert['severity'] }}</span></td>
                                <td class="py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $alert['rule'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-gray-400">{{ $this->sev === 'all' ? 'No alerts recorded yet — the collector reads the SIEM log every minute.' : 'No '.$this->sev.' alerts in this window.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
