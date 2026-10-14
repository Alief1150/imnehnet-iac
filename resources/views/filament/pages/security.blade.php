<x-filament-panels::page>
    {{-- Scoped layout fallback: works even when the deployed Vite bundle has not picked up new utility classes. --}}
    <style>
        .im-security .im-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
        .im-security .im-chartrow { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:1.5rem; }
        .im-security .im-halves { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.5rem; }
        .im-security .im-kpis > *, .im-security .im-chartrow > *, .im-security .im-halves > * { min-width:0; }
        .im-security .im-chart-svg { display:block; width:100%; height:180px; max-height:180px; overflow:hidden; }
        .im-security .im-spark-svg { display:block; width:100%; height:44px; max-height:44px; overflow:hidden; }
        .im-security .im-donut-svg { display:block; width:100%; height:190px; max-height:190px; overflow:hidden; }
        .im-security .im-panel { min-width:0; overflow:hidden; }
        @media(max-width:1279px) { .im-security .im-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:1199px) { .im-security .im-chartrow, .im-security .im-halves { grid-template-columns:minmax(0,1fr); } }
        @media(max-width:767px) { .im-security .im-kpis { grid-template-columns:minmax(0,1fr); } }
    </style>
    @php
        $kpis = $this->kpis();
        $trend = $this->alertTrend();
        $donut = $this->severityDonut();
        $integrations = collect($this->integrations());
        $grafana = $integrations->firstWhere('key', 'grafana');
        $wazuhDash = $integrations->firstWhere('key', 'wazuh_dashboard');
        $wazuhApi = $integrations->firstWhere('key', 'wazuh_api');
        $recent = $this->recentAlerts();
        $range = $this->selectedRange();
        $ranges = collect(\App\Services\SecurityDashboard::RANGES)->mapWithKeys(fn ($seconds, $key) => [$key => strtoupper($key)])->all();
        $baseUrl = \App\Filament\Pages\Security::getUrl(panel: 'admin');
        $gridlines = [25, 50, 75];
        $integrationCards = [
            ['entry' => $grafana, 'name' => 'Grafana', 'blurb' => 'View dashboards and visualizations'],
            ['entry' => $wazuhDash, 'name' => 'Wazuh', 'blurb' => 'Investigate alerts and manage agents'],
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
            Updated {{ $fresh['at'] !== null ? \Illuminate\Support\Carbon::createFromTimestamp($fresh['at'])->diffForHumans() : 'never' }}@if ($fresh['stale'])
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
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">{!! \App\Support\Lucide::html($kpi['icon'] ?? 'shield-alert') !!}</span>
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

        @php $vis = $this->visibility(); @endphp
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Container visibility">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Container visibility</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paired lab containers the SIEM heard from in 24h. A container without an enrolled agent renders as missing, never as covered.</p>
                </div>
                <span class="font-mono text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $vis['expected'] !== null ? $vis['reporting'].' of '.$vis['expected'] : 'n/a' }}</span>
            </div>
            @if ($vis['missing'] !== [])
                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">Not reporting: <span class="font-mono">{{ implode(', ', $vis['missing']) }}</span></p>
            @endif
            @if (($vis['state'] ?? '') !== 'ready')
                <p class="mt-2 text-xs"><span class="rounded-full bg-gray-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span></p>
            @endif
        </section>

        {{-- Trend + severity --}}
        <div class="im-chartrow grid gap-6 xl:grid-cols-3">
            <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Alert Trend</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Total security alerts over time ·
                            @if ($trend['from_label'] && $trend['to_label'])
                                {{ $trend['from_label'] }} → {{ $trend['to_label'] }}
                            @else
                                {{ $trend['coverage'] }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-4">
                    @if (count($trend['series']) > 1)
                        @php
                            $line = collect($trend['series'])->map(fn ($p) => sprintf('%.2f,%.2f', $p[0], $p[1]))->implode(' ');
                            $area = '0,100 '.$line.' 100,100';
                        @endphp
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="im-chart-svg" role="img" aria-label="Alert counts over time">
                            @foreach ($gridlines as $gridline)
                                <line x1="0" y1="{{ $gridline }}" x2="100" y2="{{ $gridline }}" stroke="currentColor" stroke-opacity=".12" stroke-width=".5" vector-effect="non-scaling-stroke" />
                            @endforeach
                            <polygon points="{{ $area }}" fill="#2dd4bf" fill-opacity="0.15" />
                            <polyline points="{{ $line }}" fill="none" stroke="#2dd4bf" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @else
                        <div class="flex h-44 items-center justify-center rounded-lg border border-dashed border-gray-200 text-center text-sm text-gray-400 dark:border-gray-800">
                            <p>No samples in this window yet.<br><span class="text-xs">The collector reads the SIEM log every minute.</span></p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Alert Severity</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Distribution of security alerts, last 24h</p>
                @if ($donut['total'] > 0)
                    @php $offset = 25; @endphp
                    <div class="relative mx-auto mt-2 w-44">
                        <svg viewBox="0 0 42 42" class="im-donut-svg" role="img" aria-label="Alert severities, {{ $donut['total'] }} total">
                            <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke-width="6" class="stroke-gray-100 dark:stroke-gray-800" />
                            @foreach ($donut['slices'] as $slice)
                                @if ($slice['count'] > 0)
                                    <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke-width="6"
                                            pathLength="100"
                                            stroke="{{ $slice['color'] }}"
                                            stroke-dasharray="{{ number_format($slice['share'], 1) }} {{ number_format(100 - $slice['share'], 1) }}"
                                            stroke-dashoffset="{{ number_format($offset, 1) }}" />
                                    @php $offset -= $slice['share']; @endphp
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="font-mono text-2xl font-bold text-gray-950 dark:text-white">{{ $donut['total'] }}</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Total Alerts</span>
                        </div>
                    </div>
                    <dl class="mt-3 space-y-2">
                        @foreach ($donut['slices'] as $slice)
                            <div class="flex items-center gap-2.5 text-sm">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $slice['color'] }}"></span>
                                <dt class="flex-1 text-gray-600 dark:text-gray-300">{{ $slice['label'] }}</dt>
                                <dd class="font-mono font-semibold text-gray-950 dark:text-white">{{ $slice['count'] }}</dd>
                                <dd class="w-12 text-right font-mono text-xs text-gray-500">{{ number_format($slice['share'], 0) }}%</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <div class="mt-4 flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 px-5 text-center dark:border-gray-800">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No alerts in 24h</p>
                        <p class="mt-2 max-w-sm text-xs leading-relaxed text-gray-500 dark:text-gray-400">A quiet day renders as an empty ring, not as zeros with a story.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- Integrations --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Security Integrations</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Access external security tools for deeper analysis and monitoring.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($integrationCards as $card)
                    @php $entry = $card['entry'] ?? null; $ok = ($entry['status'] ?? '') === 'healthy'; @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-4 py-3.5 dark:border-gray-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $ok ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400' : 'bg-gray-500/10 text-gray-500' }}">{!! \App\Support\Lucide::html($card['name'] === 'Wazuh' ? 'shield' : 'chart-bar') !!}</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Open {{ $card['name'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['blurb'] }}</p>
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
            </div>
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
        </section>

        {{-- Recent alerts --}}
        <section class="card im-panel rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recent Security Alerts</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Latest security events from Wazuh and system monitoring.</p>
                </div>
                @if ($wazuhDash !== null && ! empty($wazuhDash['url']))
                    <a href="{{ $wazuhDash['url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-semibold text-teal-600 hover:underline dark:text-teal-400">View all alerts →</a>
                @endif
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="table w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr><th class="py-2.5 pr-4 font-medium">Time</th><th class="py-2.5 pr-4 font-medium">Source</th><th class="py-2.5 pr-4 font-medium">Event</th><th class="py-2.5 pr-4 font-medium">Severity</th><th class="py-2.5 font-medium">Rule</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recent as $alert)
                            @php $sev = $alert['severity'] ?? 'low'; @endphp
                            <tr>
                                <td class="whitespace-nowrap py-2.5 pr-4 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($alert['ts'])->diffForHumans() }}</td>
                                <td class="py-2.5 pr-4 font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $alert['agent'] }}</td>
                                <td class="max-w-md truncate py-2.5 pr-4 text-xs text-gray-600 dark:text-gray-300" title="{{ $alert['description'] }}">{{ $alert['description'] }}</td>
                                <td class="py-2.5 pr-4"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $sev === 'high' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : ($sev === 'medium' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400') }}">{{ $sev }}</span></td>
                                <td class="py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $alert['rule'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-gray-400">No alerts recorded yet — the collector reads the SIEM log every minute.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
