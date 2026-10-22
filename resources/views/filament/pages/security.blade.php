<x-filament-panels::page>
    <style>
        /* FlyonUI components, scoped to this Filament page. */
        .im-security {
            --sec-surface: #fff;
            --sec-raised: #f5f7fa;
            --sec-line: #e5e9f0;
            --sec-text: #182338;
            --sec-muted: #65748b;
            --sec-accent: #009b88;
            --color-primary: #009b88;
            --color-primary-content: #fff;
            display: grid;
            gap: 20px;
            width: 100%;
            min-width: 0;
            color: var(--sec-text);
            font-size: 14px;
            line-height: 1.5;
        }
        :is(.dark, [data-theme="dark"]) .im-security {
            --sec-surface: #121b28;
            --sec-raised: #182334;
            --sec-line: #263449;
            --sec-text: #e8eef8;
            --sec-muted: #96a7bf;
            --sec-accent: #2dd4bf;
        }
        .im-security > * { margin-block: 0; min-width: 0; }
        .im-security .im-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .im-security .im-chartrow { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 20px; align-items: stretch; }
        .im-security .im-chartrow > * { grid-column: auto; }
        .im-security .im-chartrow > .card > .card-body { height: 100%; }
        .im-security .im-panel { min-width: 0; width: 100%; height: auto; overflow: hidden; border: 1px solid var(--sec-line); border-radius: 14px; background: var(--sec-surface); color: var(--sec-text); box-shadow: 0 2px 6px rgb(0 0 0 / .025); }
        .im-security .im-panel > .card-body { display: flex; flex-direction: column; gap: 0; min-width: 0; padding: 22px; }
        .im-security .im-panel p { color: var(--sec-muted); line-height: 1.6; overflow-wrap: anywhere; }
        .im-security h2, .im-security h3, .im-security .card-title { color: var(--sec-text); }
        .im-security h3 { display: flex; align-items: center; gap: 8px; font-size: 14px; line-height: 1.5; font-weight: 650; }
        .im-security .im-panel p.text-xs { font-size: 12px; }
        .im-security .fi-fi { display: inline-block; width: 18px; height: 18px; flex-shrink: 0; vertical-align: middle; }
        .im-security .im-page-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; }
        .im-security .im-page-heading h2 { margin: 0; font-size: 25px; letter-spacing: -.035em; font-weight: 700; }
        .im-security .im-page-heading p { margin-top: 4px; color: var(--sec-muted); font-size: 13px; }
        .im-security .im-freshness { margin-top: -12px; font-size: 12px; color: var(--sec-muted); }
        .im-security .im-stat-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .im-security .im-stat-label { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .im-security .im-stat-label h3 { color: var(--sec-muted); font-size: 12px; font-weight: 500; }
        .im-security .im-stat-label > span { display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 36px; border-radius: 10px; color: var(--sec-accent); background: color-mix(in srgb, var(--sec-accent) 10%, transparent); }
        .im-security .im-kpi-value { display: flex; gap: 6px; align-items: baseline; margin-top: 16px; }
        .im-security .im-kpi-value > span:first-child { color: var(--sec-text); font-size: 30px; line-height: 1.1; letter-spacing: -.035em; font-weight: 700; font-variant-numeric: tabular-nums; }
        .im-security .im-kpi-delta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 12px; font-size: 11px; }
        .im-security .im-spark-svg { display: block; flex: 0 0 66px; width: 66px; height: 30px; overflow: visible; color: var(--sec-accent); }
        .im-security .im-chart-heading { display: flex; flex-wrap: wrap; align-items: start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .im-security .im-chart-heading p { margin-top: 3px; }
        .im-security .im-segments { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 3px; width: fit-content; padding: 4px; border: 1px solid var(--sec-line); border-radius: 9px; background: var(--sec-raised); }
        .im-security .im-segments .btn { min-height: 28px; height: 28px; padding: 0 10px; border: 0; border-radius: 6px; font-size: 11px; font-weight: 600; box-shadow: none; }
        .im-security .im-segments .btn-ghost { background: transparent; color: var(--sec-muted); }
        .im-security .im-segments .btn-primary { background: #009b88; color: #fff; }
        .im-security .im-segments .btn:hover { filter: brightness(1.08); }
        .im-security :is(a, button):focus-visible { outline: 2px solid var(--sec-accent); outline-offset: 3px; }
        .im-security .im-chart-window { color: var(--sec-muted); font-size: 11px; }
        .im-security .im-chart-box { position: relative; width: 100%; min-width: 0; margin-top: 10px; aspect-ratio: var(--im-chart-ratio, 320 / 90); }
        .im-security .im-trend-svg { display: block; width: 100%; height: 100%; overflow: visible; }
        .im-security .im-gridline { stroke: var(--sec-muted); stroke-opacity: .17; stroke-width: .5; stroke-dasharray: 1.5 1.5; }
        .im-security .im-axis-label { fill: var(--sec-muted); font-family: inherit; font-size: 5px; }
        .im-security .im-trend-line { stroke: var(--sec-accent); }
        .im-security .im-dot { cursor: crosshair; fill: var(--sec-accent); stroke: var(--sec-surface); transition: fill .15s; }
        .im-security .im-dot:hover { fill: var(--sec-text); }
        .im-security .im-chart-empty { display: grid; place-items: center; height: 100%; min-height: 120px; padding: 16px; border: 1px dashed var(--sec-line); border-radius: 10px; text-align: center; }
        .im-security .im-severity-layout { display: grid; grid-template-columns: 142px minmax(0, 1fr); align-items: center; gap: 18px; flex: 1; margin-top: 18px; }
        .im-security .im-donut-wrap { position: relative; width: 142px; max-width: 100%; aspect-ratio: 1; }
        .im-security .im-donut-svg { display: block; width: 100%; height: 100%; }
        .im-security .im-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 2px; pointer-events: none; }
        .im-security .im-donut-center strong { font-size: 23px; font-weight: 700; color: var(--sec-text); font-variant-numeric: tabular-nums; }
        .im-security .im-donut-center span { color: var(--sec-muted); font-size: 10px; }
        .im-security .im-severity-list { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
        .im-security .im-severity-item { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto 38px; align-items: center; gap: 8px; width: 100%; min-height: 35px; padding: 6px 7px; border: 1px solid transparent; border-radius: 8px; background: transparent; text-align: left; font-size: 12px; color: var(--sec-text); cursor: pointer; }
        .im-security .im-severity-item:hover, .im-security .im-severity-item[aria-pressed="true"] { background: var(--sec-raised); border-color: var(--sec-line); }
        .im-security .im-severity-dot { display: block; width: 8px; height: 8px; border-radius: 50%; }
        .im-security .im-severity-count { font-weight: 650; font-variant-numeric: tabular-nums; }
        .im-security .im-severity-share { color: var(--sec-muted); text-align: right; font-size: 11px; font-variant-numeric: tabular-nums; }
        .im-security .im-severity-note { margin-top: 12px; padding: 9px 11px; background: var(--sec-raised); border-radius: 8px; font-size: 11px; }
        .im-security .im-table-scroll { width: 100%; overflow-x: auto; margin-top: 18px; }
        .im-security .table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 12px; }
        .im-security .table thead { background: var(--sec-raised); color: var(--sec-muted); font-size: 10px; letter-spacing: .055em; text-transform: uppercase; }
        .im-security .table th { padding: 11px 13px; font-weight: 600; white-space: nowrap; }
        .im-security .table td { padding: 13px; border-bottom: 1px solid var(--sec-line); color: var(--sec-text); font-size: 12px; }
        .im-security .table tr:last-child td { border-bottom: 0; }
        .im-security .table tbody tr:hover { background: var(--sec-raised); }
        .im-security .im-integrations-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
        .im-security .im-integration { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 15px; border: 1px solid var(--sec-line); border-radius: 10px; }
        .im-security .im-api-note { grid-column: 1 / -1; margin-top: 0; }
        .im-security .im-link-button { display: inline-flex; justify-content: center; align-items: center; flex-shrink: 0; width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--sec-line); background: var(--sec-raised); color: var(--sec-accent); }
        .im-security .badge { white-space: nowrap; font-size: 10px; font-weight: 600; border-radius: 6px; }
        .im-security .im-visibility-value { white-space: nowrap; font-size: 23px; font-weight: 650; color: var(--sec-text); }
        .im-security .im-missing { color: #b8790c !important; }
        :is(.dark, [data-theme="dark"]) .im-security .im-missing { color: #fbbf24 !important; }
        .im-security .im-alert-filter { margin-top: 14px; }
        @media (max-width: 1199px) {
            .im-security .im-chartrow { grid-template-columns: minmax(0, 1fr); }
            .im-security .im-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .im-security .im-severity-layout { grid-template-columns: 142px minmax(0, 1fr); max-width: 500px; width: 100%; align-self: center; }
        }
        @media (max-width: 639px) {
            .im-security { gap: 14px; }
            .im-security .im-kpis { gap: 10px; }
            .im-security .im-panel > .card-body { padding: 16px; }
            .im-security .im-kpis > .im-panel > .card-body { padding: 14px; }
            .im-security .im-kpi-value > span:first-child { font-size: 26px; }
            .im-security .im-stat-label > span { width: 28px; height: 28px; flex-basis: 28px; }
            .im-security .im-stat-label { gap: 7px; }
            .im-security .im-spark-svg { display: none; }
            .im-security .im-chart-box { min-height: 110px; overflow-x: auto; }
            .im-security .im-trend-svg { min-width: 340px; min-height: 96px; }
            .im-security .im-integrations-grid { grid-template-columns: minmax(0, 1fr); }
            .im-security .im-severity-layout { grid-template-columns: 120px minmax(0, 1fr); gap: 10px; }
            .im-security .im-donut-wrap { width: 120px; }
            .im-security .im-severity-item { gap: 6px; padding-inline: 2px; font-size: 11px; }
        }
        @media (max-width: 359px) {
            .im-security .im-kpis { grid-template-columns: minmax(0, 1fr); }
            .im-security .im-severity-layout { grid-template-columns: minmax(0, 1fr); justify-items: center; }
            .im-security .im-severity-list { width: 100%; }
        }
        .im-security .im-stat-tools { display: flex; align-items: center; gap: 8px; }
        .im-security .im-live-dot {
            width: 7px; height: 7px; border-radius: 9999px; background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55);
            animation: im-live-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
            flex: none;
        }
        @keyframes im-live-ping {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55); }
            70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .im-security .im-live-badge {
            display: inline-flex; align-items: center; gap: 6px;
            border-radius: 9999px; padding: 2px 9px;
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #059669; background: rgba(16, 185, 129, 0.12);
        }
        .im-security .im-detail-toggle {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 6px;
            color: #9ca3af; transition: transform .2s ease, color .2s ease, background-color .2s ease;
        }
        .im-security .im-detail-toggle:hover { color: #374151; background: rgba(107, 114, 128, 0.08); }
        .im-security .im-detail-toggle.is-open { transform: rotate(180deg); color: #0d9488; }
        .im-security .im-detail-toggle svg { width: 14px; height: 14px; }
        .im-security .im-kpi-detail {
            margin-top: 10px; padding: 10px 12px;
            border-radius: 10px;
            background: rgba(107, 114, 128, 0.07);
            border-left: 2px solid #14b8a6;
            display: grid; gap: 6px;
        }
        .im-security .im-kpi-detail p {
            font-size: 11px; line-height: 1.55; color: #6b7280;
        }
        .im-security .im-kpi-detail-key {
            display: block; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; font-size: 9.5px; color: #0d9488;
            margin-bottom: 1px;
        }
        .im-security .im-trend-line {
            stroke-dasharray: 1400;
            stroke-dashoffset: 1400;
            animation: im-trend-draw 1.2s ease-out forwards;
        }
        @keyframes im-trend-draw { to { stroke-dashoffset: 0; } }
        .im-security .im-dot-live { animation: im-dot-pulse 2.2s ease-in-out infinite; }
        @keyframes im-dot-pulse {
            0%, 100% { opacity: 1; r: .95; }
            50% { opacity: .55; r: 1.5; }
        }
        .im-security .im-chart-tools { display: flex; align-items: center; gap: 10px; }
        .im-security .im-axis-label { font-size: 5px; }
        .im-security .im-trend-line { transition: d .7s ease; }
        .im-security .im-chart-box .im-trend-svg { overflow: visible; }
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
        $ranges = array_map('strtoupper', array_keys(\App\Services\SecurityDashboard::RANGES));
        $ranges = array_combine(array_keys(\App\Services\SecurityDashboard::RANGES), $ranges);
        $baseUrl = \App\Filament\Pages\Security::getUrl(panel: 'admin');

        $integrationCards = [
            ['entry' => $grafana, 'name' => 'Grafana', 'icon' => 'chart-bar', 'blurb' => 'View dashboards and visualizations'],
            ['entry' => $wazuhDash, 'name' => 'Wazuh', 'icon' => 'shield', 'blurb' => 'Investigate alerts and manage agents'],
        ];
    @endphp

    <div wire:poll.15s class="im-security">
        <div class="im-page-heading">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">Security</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor security alerts, agent activity, and environment coverage.</p>
            </div>
        </div>

        @php $fresh = $this->freshness(); @endphp
        <p class="im-freshness">
            Updated {{ $fresh['at'] !== null ? \Illuminate\Support\Carbon::createFromTimestamp($fresh['at'])->diffForHumans() : 'never' }}
            @if ($fresh['stale'])
                — <span class="font-semibold text-amber-600 dark:text-amber-400">stale{{ $fresh['errors'] !== [] ? ': '.implode('; ', $fresh['errors']) : ': the collector has not reported yet' }}</span>
            @endif
        </p>

        {{-- KPI cards --}}
        <section class="im-kpis grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Security overview">
            @foreach ($kpis as $kpiKey => $kpi)
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
                <article class="card im-panel">
                    <div class="card-body">
                    <div class="im-stat-top">
                        <div class="im-stat-label">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">{!! \App\Support\Flyon::html($kpi['icon'] ?? 'shield-alert') !!}</span>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</h3>
                        </div>
                        <div class="im-stat-tools">
                            <span class="im-live-dot" title="Collected every minute"></span>
                            @if (count($spark) > 1)
                                <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="im-spark-svg" role="img" aria-label="{{ $kpi['label'] }} recent trend">
                                    <polyline points="{{ $sparkPoints }}" fill="none" stroke="#2dd4bf" stroke-width="1.8" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            @endif
                            <button type="button" wire:click="toggleDetail('{{ $kpiKey }}')"
                                    aria-expanded="{{ in_array($kpiKey, $this->openDetails, true) ? 'true' : 'false' }}"
                                    aria-label="Explain {{ $kpi['label'] }}"
                                    title="What this figure counts, and how to read it"
                                    class="im-detail-toggle {{ in_array($kpiKey, $this->openDetails, true) ? 'is-open' : '' }}">
                                {!! \App\Support\Flyon::html('chevron-down') !!}
                            </button>
                        </div>
                    </div>
                    <div class="im-kpi-value">
                        <span class="font-mono text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ is_numeric($kpi['value'] ?? null) ? number_format((float) $kpi['value']) : 'n/a' }}</span>
                        @if (is_numeric($kpi['value'] ?? null) && ! empty($kpi['unit'])) <span class="text-sm text-gray-500">{{ $kpi['unit'] }}</span> @endif
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $kpi['subtitle'] ?? '' }}</p>
                    <p class="im-kpi-delta">
                        @if ($deltaFlat)
                            <span class="text-gray-400">— {{ $delta['label'] }}</span>
                        @else
                            <span class="{{ $deltaUp ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $deltaUp ? '▲' : ($deltaDown ? '▼' : '—') }} {{ $delta['label'] }}</span>
                        @endif
                        @if (($kpi['state'] ?? '') !== 'ready')
                            <span class="badge badge-soft bg-gray-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span>
                        @endif
                    </p>
                    @if (in_array($kpiKey, $this->openDetails, true) && ! empty($kpi['detail']))
                        <div class="im-kpi-detail">
                            <p><span class="im-kpi-detail-key">What it counts</span>{{ $kpi['detail']['what'] ?? '' }}</p>
                            <p><span class="im-kpi-detail-key">Where it comes from</span>{{ $kpi['detail']['source'] ?? '' }}</p>
                            <p><span class="im-kpi-detail-key">How to read it</span>{{ $kpi['detail']['indicator'] ?? '' }}</p>
                        </div>
                    @endif
                    </div>
                </article>
            @endforeach
        </section>

        {{-- Trend + severity --}}
        <div class="im-chartrow">
            <section class="card im-panel">
                    <div class="card-body">
                <div class="im-chart-heading">
                    <div>
                        <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                            <span class="text-teal-600 dark:text-teal-400">{!! \App\Support\Flyon::html('chart-line') !!}</span>
                            Alert Trend
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total security alerts over time</p>
                    </div>
                    <div class="im-chart-tools">
                        <span class="im-live-badge" title="Refilled from the SIEM every minute"><span class="im-live-dot"></span>live</span>
                    <div class="join im-segments" role="group" aria-label="Time range">
                        @foreach ($ranges as $key => $label)
                            <a href="{{ $baseUrl }}?range={{ $key }}"
                               aria-current="{{ $range === $key ? 'true' : 'false' }}"
                               class="btn btn-xs join-item {{ $range === $key ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <p class="im-chart-window">
                    @if (! ($chart['empty'] ?? true))
                        {{ $chart['from_label'] }} → {{ $chart['to_label'] }}
                    @else
                        {{ $chart['coverage'] }}
                    @endif
                </p>
                <div class="im-chart-box" style="--im-chart-ratio: {{ $chart['width'] ?? 320 }} / {{ $chart['height'] ?? 90 }};">
                    @if (! ($chart['empty'] ?? true))
                        <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="im-trend-svg" role="img" aria-label="Alert counts over time">
                            @foreach ($chart['yticks'] as $tick)
                                <line x1="{{ $chart['left'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['right'] }}" y2="{{ $tick['y'] }}" class="im-gridline" />
                                <text x="{{ $chart['left'] - 4 }}" y="{{ $tick['y'] + 1.7 }}" text-anchor="end" class="im-axis-label">{{ $tick['label'] }}</text>
                            @endforeach
                            @foreach ($chart['xticks'] as $tick)
                                <text x="{{ $tick['x'] }}" y="{{ $chart['height'] - 10 }}" text-anchor="{{ $tick['anchor'] ?? 'middle' }}" class="im-axis-label">{{ $tick['label'] }}</text>
                            @endforeach
                            <path d="{{ $chart['area'] }}" fill="var(--sec-accent)" fill-opacity="0.13" stroke="none" />
                            <path d="{{ $chart['line'] }}" fill="none" class="im-trend-line" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($chart['dots'] as $dot)
                                <circle class="im-dot {{ $loop->last ? 'im-dot-live' : '' }}" cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r=".95" stroke-width="1" vector-effect="non-scaling-stroke">
                                    <title>{{ $dot['label'] }}</title>
                                </circle>
                            @endforeach
                        </svg>
                    @else
                        <div class="im-chart-empty">
                            <p>No samples in this window yet.<br><span class="text-xs">The collector reads the SIEM log every minute.</span></p>
                        </div>
                    @endif
                </div>
                </div>
        </section>

            <section class="card im-panel">
                    <div class="card-body">
                <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                    <span class="text-blue-600 dark:text-blue-400">{!! \App\Support\Flyon::html('chart-bar') !!}</span>
                    Alert Severity
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Distribution of security alerts · last 24h</p>
                @if ($donut['total'] > 0)
                    @php $offset = 25; @endphp
                    <div class="im-severity-layout">
                    <div class="im-donut-wrap">
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
                        <div class="im-donut-center">
                            <strong>{{ number_format($donut['total']) }}</strong>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Total Alerts</span>
                        </div>
                    </div>
                    <div class="im-severity-list" role="group" aria-label="Filter alerts by severity">
                        @foreach ($donut['slices'] as $slice)
                            <button type="button" wire:click="toggleSev('{{ $slice['key'] }}')" title="Show only {{ $slice['label'] }} alerts" class="im-severity-item" aria-pressed="{{ $this->sev === $slice['key'] ? 'true' : 'false' }}">
                                <span class="im-severity-dot" style="background-color: {{ $slice['color'] }}"></span>
                                <span>{{ $slice['label'] }}</span>
                                <span class="im-severity-count">{{ $slice['count'] }}</span>
                                <span class="im-severity-share">{{ number_format($slice['share'], 0) }}%</span>
                            </button>
                        @endforeach
                    </div>
                    </div>
                    @if (($donut['slices'][0]['share'] ?? 0) >= 90)
                        <p class="im-severity-note">Routine low-level agent noise dominates this volume — triage starts at Medium and High. Click a row to filter the table.</p>
                    @endif
                @else
                    <div class="mt-4 flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 px-5 text-center dark:border-gray-800">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No alerts in 24h</p>
                        <p class="mt-2 max-w-sm text-xs leading-relaxed text-gray-500 dark:text-gray-400">A quiet day renders as an empty ring, not as zeros with a story.</p>
                    </div>
                @endif
                </div>
        </section>
        </div>

        {{-- Container visibility --}}
        <section class="card im-panel" aria-label="Container visibility">
                    <div class="card-body">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-emerald-600 dark:text-emerald-400">{!! \App\Support\Flyon::html('eye') !!}</span>
                        Container visibility
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paired lab containers the SIEM heard from in 24h. A container without an enrolled agent renders as missing, never as covered.</p>
                </div>
                <span class="im-visibility-value">{{ $visibility['expected'] !== null ? $visibility['reporting'].' of '.$visibility['expected'] : 'n/a' }}</span>
            </div>
            @if ($visibility['missing'] !== [])
                <p class="im-missing mt-2 text-xs">Not reporting: <span class="font-mono">{{ implode(', ', $visibility['missing']) }}</span></p>
            @endif
            @if (($visibility['state'] ?? '') !== 'ready')
                <p class="mt-2 text-xs"><span class="badge badge-soft bg-gray-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span></p>
            @endif
            </div>
        </section>

        {{-- Environment coverage --}}
        <section class="card im-panel" aria-label="Environment coverage">
                    <div class="card-body">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-violet-600 dark:text-violet-400">{!! \App\Support\Flyon::html('server') !!}</span>
                        Environment coverage
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alerts per plane in 24h, straight from the SIEM log. A quiet plane renders as quiet — the portal never fills a gap with a guess.</p>
                </div>
                @if (! $coverage['ok'])
                    <span class="badge badge-soft bg-gray-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">collecting</span>
                @endif
            </div>
            <div class="im-table-scroll">
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
                                <td class="py-2.5"><span class="badge badge-soft px-2 py-0.5 text-[10px] font-semibold uppercase {{ ($row['state'] ?? '') === 'reporting' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500 dark:text-gray-400' }}">{{ $row['state'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        </section>

        {{-- Security Integrations --}}
        <section class="card im-panel" aria-label="Security Integrations">
                    <div class="card-body">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-teal-600 dark:text-teal-400">{!! \App\Support\Flyon::html('external-link') !!}</span>
                        Security Integrations
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Access external security tools for deeper analysis and monitoring.</p>
                </div>
            </div>
            <div class="im-integrations-grid">
                @foreach ($integrationCards as $card)
                    @php $entry = $card['entry'] ?? null; $ok = ($entry['status'] ?? '') === 'healthy'; @endphp
                    <div class="im-integration">
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
                            <span class="badge badge-soft inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold {{ $ok ? 'badge-success bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500 dark:text-gray-400' }}">
                                <span class="size-1.5 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>{{ $ok ? 'Available' : 'Unreachable' }}
                            </span>
                            @if (! empty($entry['url']))
                                <a href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer" title="Open {{ $card['name'] }}" class="btn btn-square btn-sm btn-ghost im-link-button">→</a>
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
                    <p class="im-api-note text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ $apiLine }}.
                        An API answering 401 refuses anonymous callers, which is correct behaviour — not an outage.
                    </p>
                @endif
            </div>
            </div>
        </section>

        {{-- Recent Security Alerts --}}
        <section class="card im-panel" aria-label="Recent Security Alerts">
                    <div class="card-body">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="card-title flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                        <span class="text-amber-600 dark:text-amber-400">{!! \App\Support\Flyon::html('clock') !!}</span>
                        Recent Security Alerts
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Latest event per reporter, newest first.</p>
                </div>
                @if ($wazuhDash !== null && ! empty($wazuhDash['url']))
                    <a href="{{ $wazuhDash['url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-semibold text-teal-600 hover:underline dark:text-teal-400">View all alerts →</a>
                @endif
            </div>
            <div class="join im-segments im-alert-filter" role="group" aria-label="Severity filter">
                @foreach (['all' => 'All', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $sevKey => $sevLabel)
                    <button type="button" wire:click="setSev('{{ $sevKey }}')" aria-pressed="{{ $this->sev === $sevKey ? 'true' : 'false' }}" class="btn btn-xs join-item {{ $this->sev === $sevKey ? 'btn-primary' : 'btn-ghost' }}">{{ $sevLabel }}</button>
                @endforeach
            </div>
            <div class="im-table-scroll">
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
                                <td class="py-2.5 pr-4"><span class="badge badge-soft px-2 py-0.5 text-[10px] font-semibold uppercase {{ $alert['severity'] === 'high' ? 'badge-warning bg-rose-500/10 text-rose-600 dark:text-rose-400' : ($alert['severity'] === 'medium' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400') }}">{{ $alert['severity'] }}</span></td>
                                <td class="py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $alert['rule'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-gray-400">{{ $this->sev === 'all' ? 'No alerts recorded yet — the collector reads the SIEM log every minute.' : 'No '.$this->sev.' alerts in this window.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
