<x-filament-panels::page>
    @php
        $resources = $this->resources();
        $physicalHost = $this->physicalHost();
        $hostDetails = $this->hostDetails();
        $trends = $this->trends();
        $inventory = $this->containerInventory();
        $backends = $this->backends();
        $health = $this->health();
        $vmSeries = $this->vmSeries();
        $metricNames = ['cpu' => 'Average CPU', 'memory' => 'Average Memory', 'storage' => 'Average Storage'];
        $metricIcons = ['cpu' => 'cpu', 'memory' => 'memory-stick', 'storage' => 'hard-drive'];
        $metricColors = ['cpu' => '#14b8a6', 'memory' => '#3b82f6', 'storage' => '#f59e0b'];
        $statusTone = fn ($status) => match ($status) {
            'healthy' => 'badge-success', 'degraded' => 'badge-warning', 'offline' => 'badge-error',
            default => 'badge-soft badge-neutral',
        };
    @endphp

    <div wire:poll.10s class="space-y-4" data-im-monitoring>
        @php
            $hostSamples = collect(['cpu', 'memory', 'storage'])->map(fn ($key) => $physicalHost[$key]['sample_at'] ?? null)->filter(fn ($v) => is_numeric($v));
            $lastHostSample = $hostSamples->isNotEmpty() ? (float) $hostSamples->min() : null;
            $hostAge = $hostDetails['age_seconds'] ?? null;
            $hostOnline = ($hostDetails['up'] ?? false) && collect(['cpu', 'memory', 'storage'])->every(fn ($key) => ($physicalHost[$key]['status'] ?? '') === 'live');
        @endphp
        <div data-monitor-v2-layout class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">Infrastructure Monitoring</h2>
                <p class="mt-1 text-xs text-base-content/60">Near-real-time telemetry · physical host and 3 VMs · dashboard refresh 10s</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="badge {{ $hostOnline ? 'badge-success' : 'badge-warning' }}">{{ $hostOnline ? '● Host Telemetry Live' : 'Host Telemetry Unavailable' }}</span>
                <span class="badge badge-soft badge-info">Last sample: {{ $hostAge !== null ? $hostAge.'s ago' : 'No Data' }}</span>
                <span class="badge badge-outline"><span data-monitor-clock>{{ now('Asia/Jakarta')->format('d M Y, H:i:s') }} WIB</span></span>
            </div>
        </div>



        <style>

            .im-v2-host-grid {

                display: grid;

                grid-template-columns: minmax(190px,1.05fr) minmax(0,2.45fr) minmax(205px,1.1fr);

                gap: 16px;

                align-items: center;

            }

            .im-v2-host-metrics {

                display: grid;

                grid-template-columns: repeat(3,minmax(0,1fr));

                gap: 12px;

                min-width: 0;

            }

            .im-v2-host-divider {

                border-left: 1px solid rgba(148,163,184,.18);

                padding-left: 14px;

                min-width: 0;

            }

            @@media (max-width:1150px) {

                .im-v2-host-grid { grid-template-columns:1fr; }

                .im-v2-host-divider {

                    border-left:0;

                    padding-left:0;

                    border-top:1px solid rgba(148,163,184,.18);

                    padding-top:10px;

                }

            }

            @@media (max-width:600px) {

                .im-v2-host-metrics { grid-template-columns:1fr; }

            }

        </style>



        <section aria-label="Physical Host IMNEHNET"

                 class="card min-w-0 border border-base-content/10 shadow-sm">

            <div class="card-body p-4">

                <div class="im-v2-host-grid">



                    <div class="min-w-0">

                        <div class="flex items-center gap-2">

                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200">

                                {!! \App\Support\Lucide::icon('server') !!}

                            </span>

                            <div class="min-w-0">

                                <h3 class="text-base font-semibold">Physical Host — IMNEHNET</h3>

                                <p class="text-xs text-base-content/60">Native Linux host · Node Exporter</p>

                            </div>

                        </div>

                        <span class="badge badge-sm mt-2 {{ $hostOnline ? 'badge-success' : 'badge-warning' }}">

                            {{ $hostOnline ? 'Online' : 'Telemetry unavailable' }}

                        </span>

                    </div>



                    <div class="im-v2-host-metrics">

                        @foreach (['cpu'=>'CPU','memory'=>'Memory','storage'=>'Storage'] as $key=>$label)

                            @php

                                $m = $physicalHost[$key] ?? [];

                                $v = $m['percent'] ?? null;

                                $live = ($m['status'] ?? '') === 'live';

                                $tone = $live ? 'badge-success' : 'badge-warning';

                            @endphp



                            <div class="im-v2-host-divider">

                                <div class="flex flex-wrap items-center justify-between gap-1">

                                    <span class="text-xs text-base-content/65">{{ $label }} Usage</span>

                                    <span class="badge badge-xs {{ $tone }}">

                                        {{ $live ? 'Live' : 'No Data / Stale' }}

                                    </span>

                                </div>



                                <div class="mt-2 text-2xl font-semibold tabular-nums">

                                    {{ $live && is_numeric($v) ? number_format((float)$v,1).'%' : 'n/a' }}

                                </div>



                                <div class="progress mt-2 h-1.5 bg-base-200">

                                    <div class="progress-bar"

                                         style="width: {{ $live && is_numeric($v) ? min(100,max(0,(float)$v)) : 0 }}%; background-color: {{ $metricColors[$key] }}">

                                    </div>

                                </div>

                            </div>

                        @endforeach

                    </div>



                    <div class="im-v2-host-divider space-y-2 text-xs">

                        <div class="flex items-center justify-between gap-3">

                            <span class="text-base-content/60">Uptime</span>

                            <span class="font-semibold tabular-nums">

                                @if (is_numeric($hostDetails['uptime_seconds'] ?? null))

                                    {{ floor($hostDetails['uptime_seconds'] / 86400) }}d

                                    {{ floor(($hostDetails['uptime_seconds'] % 86400) / 3600) }}h

                                @else

                                    No Data

                                @endif

                            </span>

                        </div>



                        <div class="flex items-center justify-between gap-3">

                            <span class="text-base-content/60">Source</span>

                            <span class="font-medium text-right">Prometheus (Node Exporter)</span>

                        </div>



                        <div class="flex items-center justify-between gap-3">

                            <span class="text-base-content/60">Last sample</span>

                            <span class="font-semibold tabular-nums">

                                {{ $hostAge !== null ? $hostAge.'s ago' : 'No Data' }}

                            </span>

                        </div>

                    </div>



                </div>

            </div>

        </section>



        

        <details data-im-collapse-storage class="group">
<summary class="mb-3 flex cursor-pointer list-none items-center justify-between rounded-lg border border-base-content/10 bg-base-200/30 px-4 py-3 font-semibold">
<span>Physical Storage &amp; Swap Health</span>
<span class="text-xs text-base-content/60"><span class="group-open:hidden">Show</span><span class="hidden group-open:inline">Hide</span> <span class="inline-block transition-transform group-open:rotate-180">⌄</span></span>
</summary>
<section data-host-capacity-v2 class="space-y-3">

            



            @php

                $capacity = app(\App\Services\MonitoringHostCapacityService::class)->summary();

                $capacityNames = [

                    'root' => 'Root SSD (/)',

                    'hdd1' => 'HDD 1 (/hdd1)',

                    'hdd2' => 'HDD 2 (/hdd2)',

                    'swap' => 'Swap Memory',

                ];

            @endphp



            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">

                @foreach ($capacityNames as $key => $label)

                    @php

                        $disk = $capacity[$key] ?? [];

                        $pct = $disk['percent'] ?? null;

                        $fresh = ($disk['status'] ?? '') === 'live';

                        $warning = $fresh && is_numeric($pct) && $pct >= 90;

                        $caution = $fresh && is_numeric($pct) && $pct >= 75;

                        $color = $warning ? '#ef4444' : ($caution ? '#f59e0b' : '#14b8a6');

                    @endphp



                    <article class="card border border-base-content/10 shadow-sm">

                        <div class="card-body gap-2 p-4">

                            <div class="flex items-center justify-between gap-2">

                                <span class="text-sm font-medium">{{ $label }}</span>

                                <span class="badge badge-sm {{ !$fresh ? 'badge-neutral' : ($warning ? 'badge-error' : ($caution ? 'badge-warning' : 'badge-success')) }}">

                                    {{ !$fresh ? 'No Data' : ($warning ? 'Critical' : ($caution ? 'Warning' : 'Healthy')) }}

                                </span>

                            </div>



                            <div class="text-2xl font-semibold tabular-nums">

                                {{ $fresh && is_numeric($pct) ? number_format((float) $pct, 1).'%' : 'n/a' }}

                            </div>



                            <div class="progress h-1.5 bg-base-200">

                                <div class="progress-bar"

                                     style="width: {{ $fresh && is_numeric($pct) ? min(100, max(0, (float) $pct)) : 0 }}%; background-color: {{ $color }}"></div>

                            </div>



                            <p class="text-xs text-base-content/60">

                                @if ($fresh && is_numeric($disk['available_gib'] ?? null))

                                    {{ number_format((float) $disk['available_gib'], $key === 'swap' ? 2 : 1) }} GiB available

                                @else

                                    Telemetry unavailable or stale

                                @endif

                            </p>

                        </div>

                    </article>

                @endforeach

            </div>

        </section>
</details>



        



        <section aria-label="Virtual Machines" class="space-y-2">
            <div class="flex flex-wrap items-center gap-2"><h3 class="text-base font-semibold">Virtual Machines</h3><span class="text-xs text-base-content/60">3 monitored VMs · Prometheus</span></div>
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                @foreach (['IAAS-MGMT' => 'Management plane', 'LAB-ENV' => 'Defender plane', 'LAB-ATK' => 'Attacker plane'] as $vmName => $vmRole)
                    @php
                        $vmLatest = [];
                        $vmTimes = [];
                        foreach (['cpu', 'memory', 'storage'] as $metricKey) {
                            $points = $vmSeries[$metricKey][$vmName] ?? [];
                            $point = is_array($points) && $points !== [] ? end($points) : null;
                            $ts = $point['x'] ?? null;
                            $age = is_numeric($ts) ? max(0, (int) (now()->timestamp - ((float) $ts / 1000))) : null;
                            $valid = is_numeric($point['y'] ?? null) && $age !== null && $age <= 90;
                            $vmLatest[$metricKey] = $valid ? (float) $point['y'] : null;
                            if ($valid) $vmTimes[] = $age;
                        }
                        $vmLive = count($vmTimes) === 3;
                    @endphp
                    <article class="card min-w-0 border border-base-content/10 shadow-sm">
                        <div class="card-body gap-3 p-4">
                            <div class="flex items-center justify-between gap-2"><div><h4 class="font-semibold">{{ $vmName }}</h4><p class="text-xs text-base-content/55">{{ $vmRole }}</p></div><span class="badge badge-sm {{ $vmLive ? 'badge-success' : 'badge-warning' }}">{{ $vmLive ? 'Fresh' : 'No Data / Stale' }}</span></div>
                            @foreach (['cpu' => 'CPU', 'memory' => 'Memory', 'storage' => 'Storage'] as $key => $label)
                                @php $v = $vmLatest[$key]; @endphp
                                <div class="flex items-center gap-3 text-xs"><span class="w-14 shrink-0 text-base-content/70">{{ $label }}</span><span class="w-12 shrink-0 text-right font-semibold tabular-nums">{{ $v !== null ? number_format($v, 1).'%' : 'n/a' }}</span><div class="progress h-1.5 flex-1 bg-base-200"><div class="progress-bar" style="width: {{ $v !== null ? min(100, max(0, $v)) : 0 }}%; background-color: {{ $metricColors[$key] }}"></div></div></div>
                            @endforeach
                            <p class="border-t border-base-content/10 pt-2 text-[11px] text-base-content/55">{{ $vmLive ? 'Last sample: '.max($vmTimes).'s ago' : 'Telemetry incomplete or outdated' }} · Prometheus</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <section class="card min-w-0 border border-base-content/10 shadow-sm xl:col-span-2">
                <div class="card-body gap-3 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="card-title text-base">Infrastructure Performance</h3>
                            <p class="text-xs text-base-content/60">30-minute Prometheus history · IAAS-MGMT, LAB-ENV, LAB-ATK</p>
                        </div>
                        <div class="join" role="group" aria-label="Performance metric">
                            <button type="button" class="btn btn-sm join-item btn-primary" data-monitor-metric="cpu">CPU</button>
                            <button type="button" class="btn btn-sm join-item btn-soft" data-monitor-metric="memory">Memory</button>
                            <button type="button" class="btn btn-sm join-item btn-soft" data-monitor-metric="storage">Storage</button>
                        </div>
                    </div>
                    <div wire:ignore class="min-w-0" data-monitor-chart style="height:290px"></div>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-base-content/70">
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-teal-500"></span>IAAS-MGMT</span>
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-blue-500"></span>LAB-ENV</span>
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-amber-500"></span>LAB-ATK</span>
                    </div>
                    <p class="text-[11px] text-base-content/55">Live host telemetry from three native Node Exporters. Missing metrics are shown as No Data.</p>
                </div>
            </section>

            <section class="card min-w-0 border border-base-content/10 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <div>
                        <h3 class="card-title text-base">Container State</h3>
                        <p class="text-xs text-base-content/60">Incus inventory · LAB-ENV + LAB-ATK</p>
                    </div>
                    <div wire:ignore data-incus-donut style="height:240px"></div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        @foreach (['running' => 'Running', 'stopped' => 'Stopped', 'other' => 'Other'] as $key => $label)
                            <div>
                                <div class="text-lg font-semibold tabular-nums">{{ $inventory['available'] ? ($inventory[$key] ?? 0) : 'n/a' }}</div>
                                <div class="text-base-content/60">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if (($inventory['freshness'] ?? 'missing') !== 'fresh')
                        <p class="text-xs text-warning">{{ ($inventory['freshness'] ?? '') === 'stale' ? 'Incus snapshot stale; waiting for scheduled refresh.' : 'Incus snapshot unavailable; run monitoring:refresh-incus as iaas.' }}</p>
                    @elseif (($inventory['available'] ?? 0) < 2)
                        <p class="text-xs text-warning">{{ ($inventory['available'] ?? 0) === 0 ? 'Incus inventory unavailable on both planes.' : 'Partial inventory: one plane unavailable.' }} · {{ $inventory['updated_at'] ?? '' }}</p>
                    @else
                        <p class="text-xs text-base-content/60">{{ $inventory['total'] }} containers across both planes · Updated {{ $inventory['updated_at'] ?? '' }}</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <section class="card min-w-0 border border-base-content/10 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="card-title text-base">Telemetry Endpoints</h3>
                        <span class="badge badge-soft badge-info badge-sm">Service Health</span>
                    </div>
                    <div class="divide-y divide-base-content/10">
                        @foreach ($backends as $backend)
                            @php
                                $probe = $health[$backend['key']] ?? [];
                                $effectiveStatus = $backend['status'];
                            @endphp
                            <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2 shrink-0 rounded-full {{ $effectiveStatus === 'healthy' ? 'bg-success' : ($effectiveStatus === 'offline' ? 'bg-error' : 'bg-warning') }}"></span>
                                        <span class="text-sm font-semibold">{{ $backend['label'] }}</span>
                                    </div>
                                    <div class="truncate text-xs text-base-content/55">{{ $backend['scope'] }}</div>
                                </div>
                                {{-- Endpoint reachability and authenticated data access are separate checks. --}}
                                <div class="flex shrink-0 items-center gap-2">
                                    @if (isset($probe['latency_ms']) && is_numeric($probe['latency_ms']))
                                        <span class="font-mono text-xs text-base-content/60">{{ $probe['latency_ms'] }}ms</span>
                                    @endif
                                    <span class="badge badge-sm {{ $statusTone($effectiveStatus) }}">{{ str_replace('_', ' ', $effectiveStatus) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-base-content/55">Backend health is independent of resource telemetry. Wazuh authentication may require configuration.</p>
                </div>
            </section>

            <section class="card min-w-0 border border-base-content/10 shadow-sm" aria-label="Live Data Integrity">
                <div class="card-body gap-3 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="card-title text-base">Live Data Integrity</h3>
                        <span class="text-xs text-base-content/60">Sample freshness · dashboard 10s</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Source</th><th>Last sample</th><th>Status</th></tr></thead>
                            <tbody>
                                <tr><td>IMNEHNET Host</td><td>{{ $hostAge !== null ? $hostAge.'s ago' : 'No Data' }}</td><td><span class="badge badge-sm {{ $hostOnline ? 'badge-success' : 'badge-warning' }}">{{ $hostOnline ? 'fresh' : 'stale / no data' }}</span></td></tr>
                                @foreach (['IAAS-MGMT', 'LAB-ENV', 'LAB-ATK'] as $vmName)
                                    @php
                                        $ages = [];
                                        foreach (['cpu','memory','storage'] as $metricKey) {
                                            $points = $vmSeries[$metricKey][$vmName] ?? [];
                                            $pt = is_array($points) && $points !== [] ? end($points) : null;
                                            $ts = $pt['x'] ?? null;
                                            if (is_numeric($ts) && is_numeric($pt['y'] ?? null)) $ages[] = (int) (now()->timestamp - ((float) $ts / 1000));
                                        }
                                        $age = count($ages) === 3 ? max($ages) : null;
                                        $fresh = $age !== null && $age >= -15 && $age <= 90;
                                    @endphp
                                    <tr><td>{{ $vmName }}</td><td>{{ $age !== null ? max(0, $age).'s ago' : 'No Data' }}</td><td><span class="badge badge-sm {{ $fresh ? 'badge-success' : 'badge-warning' }}">{{ $fresh ? 'fresh' : 'stale / no data' }}</span></td></tr>
                                @endforeach
                                @php
                                    $incusFresh = ($inventory['freshness'] ?? '') === 'fresh' && ($inventory['available'] ?? 0) === 2;
                                @endphp
                                <tr><td>Container Inventory</td><td>{{ $inventory['updated_at'] ?? 'No Data' }}</td><td><span class="badge badge-sm {{ $incusFresh ? 'badge-success' : 'badge-warning' }}">{{ $incusFresh ? 'fresh' : 'stale / partial' }}</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-[11px] text-base-content/55">VM timestamps are query-range sample times; host freshness uses underlying Node Exporter sample timestamp. No inferred metrics.</p>
                </div>
            </section>
        </div>

    {{-- Advanced infrastructure monitoring --}}
    <section class="card min-w-0 border border-base-content/10 shadow-sm"
             aria-label="Advanced Monitoring Grafana">
        <div class="card-body relative gap-1 md:pr-44">
            <div>
                <h3 class="card-title text-base">
                    Advanced Monitoring — Grafana
                </h3>
                <p class="mt-1 text-sm text-base-content/60">
                    Explore detailed CPU, memory, disk, network,
                    and historical infrastructure metrics.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-xs text-base-content/60">
                    Prometheus metrics · Grafana dashboards
                </div>

                @php
                    $grafanaUrl = config('services.grafana.url')
                        ?: config('monitoring.grafana_url')
                        ?: 'http://100.77.79.18:3000/login';
                @endphp

                <a href="{{ $grafanaUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 md:absolute md:right-5 md:top-1/2 md:-translate-y-1/2 rounded-lg border border-teal-500/20 bg-teal-500/10 px-3 py-2 text-sm font-semibold text-teal-500 transition hover:bg-teal-500/15">
                    Open Grafana ↗
                </a>
            </div>
            <p class="text-xs text-base-content/50">
                Opens the Grafana dashboard in a new tab.
                Authentication may be required.
            </p>
        </div>
    </section>

    </div>

    @script
    <script>
        const root = $wire.$el.querySelector('[data-im-monitoring]');
        const chartElement = root?.querySelector('[data-monitor-chart]');
        const donutElement = root?.querySelector('[data-incus-donut]');
        if (chartElement && donutElement && window.ApexCharts && !chartElement.dataset.initialized) {
            chartElement.dataset.initialized = 'true';
            const colors = ['#14b8a6', '#3b82f6', '#f59e0b'];
            const hosts = ['IAAS-MGMT', 'LAB-ENV', 'LAB-ATK'];
            let metric = 'cpu';
            let seriesData = {};
            const performance = new window.ApexCharts(chartElement, {
                chart: { type: 'line', height: 290, toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, easing: 'easeinout', speed: 450, dynamicAnimation: { enabled: true, speed: 450 } }, background: 'transparent' },
                colors,
                stroke: { curve: 'smooth', width: 2.5 },
                markers: { size: 0, hover: { size: 5 } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#64748b33' },
                series: [],
                xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
                yaxis: { min: 0, max: 100, labels: { formatter: value => `${Math.round(value)}%` } },
                tooltip: { x: { format: 'HH:mm' }, y: { formatter: value => value == null ? 'No Data' : `${value.toFixed(1)}%` } },
                noData: { text: 'No Data — no verified VM exporter series' },
                legend: { show: false },
            });
            performance.render();
            const updateChart = () => {
                const dataset = seriesData?.[metric] ?? {};
                performance.updateSeries(hosts.map(host => ({ name: host, data: dataset[host] ?? [] })));
            };
            const buttons = root.querySelectorAll('[data-monitor-metric]');
            buttons.forEach(button => button.addEventListener('click', () => {
                metric = button.dataset.monitorMetric;
                buttons.forEach(b => {
                    b.classList.toggle('btn-primary', b === button);
                    b.classList.toggle('btn-soft', b !== button);
                });
                updateChart();
            }));
            const donut = new window.ApexCharts(donutElement, {
                chart: { type: 'donut', height: 240, animations: { enabled: false } },
                series: [], labels: ['Running', 'Stopped', 'Other'],
                colors: ['#14b8a6', '#3b82f6', '#f59e0b'],
                legend: { show: false },
                plotOptions: { pie: { donut: { size: '70%' } } },
                noData: { text: 'Incus inventory unavailable' },
            });
            donut.render();
            let vmBusy = false;
            let incusBusy = false;
            const refreshVm = async () => {
                if (vmBusy || document.hidden) return;
                vmBusy = true;
                try { seriesData = await $wire.vmSeries(); updateChart(); }
                catch (error) { /* retain the last verified samples */ }
                finally { vmBusy = false; }
            };
            const refreshIncus = async () => {
                if (incusBusy || document.hidden) return;
                incusBusy = true;
                try {
                    const inventory = await $wire.containerInventory();
                    const counts = [inventory.running ?? 0, inventory.stopped ?? 0, inventory.other ?? 0];
                    donut.updateSeries(inventory.freshness === 'fresh' && inventory.available === 2 && counts.some(n => n > 0) ? counts : []);
                } catch (error) { donut.updateSeries([]); }
                finally { incusBusy = false; }
            };
            const tickClock = () => {
                const clock = root.querySelector('[data-monitor-clock]');
                if (clock) clock.textContent = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date()) + ' WIB';
            };
            refreshVm(); refreshIncus(); tickClock();
            const interval = setInterval(refreshVm, 10000);
            const incusInterval = setInterval(refreshIncus, 30000);
            const clockInterval = setInterval(tickClock, 1000);
            document.addEventListener('livewire:navigating', () => {
                clearInterval(interval);
                clearInterval(incusInterval);
                clearInterval(clockInterval);
                performance.destroy();
                donut.destroy();
            }, { once: true });
        }
    </script>
    @endscript
</x-filament-panels::page>
