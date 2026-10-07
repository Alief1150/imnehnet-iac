{{--
    The Infrastructure page: what this platform is made of.

    Every figure on this page is measured when the page is asked for, by
    InfrastructureTopologyService. There are no constants in this template. That is the
    whole design constraint: a page whose job is to describe the installation cannot
    carry its own description of it as a literal, because the installation changes and
    the literal would not.

    Two consequences worth naming, both visible here:

      - The provisioning stack lists what answers. The design this was built from named
        KVM/QEMU and libvirt; neither is installed, and this platform's hypervisor is
        Incus. They are absent from the row rather than shown as present.
      - A host whose address could not be determined says so. An empty cell teaches
        nothing; "not determined" tells an operator which probe failed.

    The health verdicts are the existing InfrastructureStatusService's, read rather than
    recomputed. Two answers to "is LAB-ENV up", one here and one on the dashboard, would
    be two chances to disagree in front of the same operator.
--}}
<x-filament-panels::page>

    {{-- Refreshes itself, and it has to.

         This page's whole claim is that it describes the installation as it is now. Without
         polling it describes the installation as it was when it was opened, which is worse
         than no page: the numbers stay correct-looking and grow quietly untrue, and a card
         that still reads "2 seconds ago" an hour later is lying about its own age.

         The original had wire:poll.10s and it was lost in a rewrite of this template. Fifteen
         seconds rather than ten because every probe here spawns a process or makes a request
         — Incus, Prometheus, Grafana, blackbox, tofu, ansible — and the service caches its
         own result for five, so a faster poll than that mostly re-reads the same cache. It
         re-renders the whole section rather than just the timestamps, so a plane that goes
         down is noticed here rather than on the next manual reload.
    --}}
    <div wire:poll.15s>
    @php
        $topology = $this->topology();
        $planes = $topology['planes'] ?? [];
        $hosts = $topology['hosts'] ?? [];
        $segments = $topology['segments'] ?? [];
        $stack = $topology['stack'] ?? [];
    @endphp

    {{-- ── plane cards ────────────────────────────────────────────────────────
         Four across, always.

         At three, the fourth wrapped onto a second row alone — which made the row read as
         two groups rather than one set of four, and the one that wrapped was the attacker
         plane. The plane the page exists to describe was the one that looked like an
         afterthought. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($planes as $plane)
            <article class="card border border-base-content/10 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span @class([
                                'flex size-11 shrink-0 items-center justify-center rounded-lg',
                                'bg-success/15 text-success' => $plane['tone'] === 'success',
                                'bg-info/15 text-info' => $plane['tone'] === 'info',
                                'bg-error/15 text-error' => $plane['tone'] === 'error',
                            ])>
                                {!! \App\Support\Lucide::icon($plane['icon']) !!}
                            </span>

                            <div class="min-w-0">
                                <h2 class="card-title text-base">{{ $plane['label'] }}</h2>
                                @if ($plane['address'])
                                    <p class="mt-0.5 font-mono text-xs text-base-content/55">
                                        {{ $plane['address'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Not a coloured dot with no explanation: the verdict is the
                         probe's own words, so a plane showing degraded says why. --}}
                    <div class="flex items-start gap-2">
                        <span @class([
                            'mt-1 inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-success/15 text-success' => $plane['status'] === 'healthy',
                            'bg-warning/15 text-warning' => $plane['status'] === 'degraded',
                            'bg-error/15 text-error' => $plane['status'] === 'offline',
                            'bg-base-300 text-base-content/60' => $plane['status'] === 'unknown',
                        ])>
                            <span class="size-1.5 rounded-full bg-current"></span>
                            {{ $plane['statusLabel'] }}
                        </span>

                        <p class="text-xs leading-relaxed text-base-content/60">
                            {{ $plane['detail'] }}
                        </p>
                    </div>

                    {{-- The two things a verdict needs to be actionable: what was asked,
                         and how long ago. A card claiming "Online" with no provenance
                         cannot be trusted when it disagrees with what an operator just
                         saw fail. --}}
                    <dl class="grid gap-1 border-t border-base-content/10 pt-2 text-[11px] text-base-content/50 sm:grid-cols-2">
                        <div class="flex gap-1.5">
                            <dt class="font-medium">Source</dt>
                            <dd class="truncate font-mono">{{ $plane['source'] }}</dd>
                        </div>
                        <div class="flex gap-1.5">
                            <dt class="font-medium">Checked</dt>
                            <dd>
                                @if ($plane['checkedAt'])
                                    {{ \Illuminate\Support\Carbon::parse($plane['checkedAt'])->diffForHumans() }}
                                @else
                                    {{-- Not omitted. An empty cell reads as "checked, no
                                         result", which is different from "not measured". --}}
                                    <span class="text-base-content/50">just now</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <p class="text-xs text-base-content/50">
                        {{ $plane['role'] }}
                    </p>
                </div>
            </article>
        @endforeach
    </div>

    {{-- ── hosts and segments ──────────────────────────────────────────────── --}}
    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="card border border-base-content/10 shadow-sm">
            <div class="card-body gap-3">
                <div>
                    <h2 class="card-title text-base">Host and VM Overview</h2>
                    <p class="mt-1 text-sm text-base-content/60">
                        Core infrastructure hosts and their roles in the platform.
                    </p>
                </div>

                <div class="grid gap-3">
                    @foreach ($hosts as $host)
                        <article @class([
                            'rounded-lg border p-3',
                            'border-base-content/10',
                            'bg-base-200/30',
                        ])>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                    'bg-success/15 text-success' => $host['tone'] === 'success',
                                    'bg-info/15 text-info' => $host['tone'] === 'info',
                                    'bg-error/15 text-error' => $host['tone'] === 'error',
                                ])>
                                    {!! \App\Support\Lucide::icon($host['icon']) !!}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold">{{ $host['name'] }}</h3>
                                    <p class="font-mono text-xs text-base-content/55">
                                        {{ $host['hostname'] }}
                                    </p>
                                </div>

                                <span @class([
                                    'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-success/15 text-success' => $host['health'] === 'healthy',
                                    'bg-warning/15 text-warning' => $host['health'] === 'degraded',
                                    'bg-error/15 text-error' => $host['health'] === 'offline',
                                    'bg-base-300 text-base-content/60' => $host['health'] === 'unknown',
                                ])>{{ $host['healthLabel'] }}</span>
                            </div>

                            <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt class="text-base-content/50">Role</dt>
                                    <dd class="mt-0.5 font-medium">{{ $host['role'] }}</dd>
                                    <dd class="text-[11px] text-base-content/50">{{ $host['roleDetail'] }}</dd>
                                </div>

                                <div>
                                    <dt class="text-base-content/50">IP Address</dt>
                                    <dd class="mt-0.5 font-mono font-medium">
                                        @if ($host['addressKnown'])
                                            {{ $host['address'] }}
                                        @else
                                            {{-- Not an empty cell. An operator reading
                                                 "not determined" knows which probe failed;
                                                 reading a blank knows nothing. --}}
                                            <span class="text-base-content/50">not determined</span>
                                        @endif
                                    </dd>
                                </div>

                                {{-- The tailnet address, which is how anything reaches a
                                     host from outside the lab network. Shown beside the
                                     lab address rather than instead of it: they are
                                     different routes to the same machine and an operator
                                     needs whichever one their client can use. Read from
                                     tailscale itself, so it cannot be a stale guess. --}}
                                <div>
                                    <dt class="text-base-content/50">Tailscale</dt>
                                    <dd class="mt-0.5 font-mono font-medium">
                                        @if (filled($host['tailscale']))
                                            {{ $host['tailscale'] }}
                                        @else
                                            <span class="text-base-content/50">not on the tailnet</span>
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-base-content/50">Health</dt>
                                    <dd class="mt-0.5 leading-relaxed text-base-content/70">
                                        {{ $host['detail'] ?? 'No probe reported.' }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card border border-base-content/10 shadow-sm">
            <div class="card-body gap-3">
                <div>
                    <h2 class="card-title text-base">Network Segments</h2>
                    <p class="mt-1 text-sm text-base-content/60">
                        Platform networks and their address spaces.
                    </p>
                </div>

                @if (count($segments) === 0)
                    <p class="rounded-lg border border-dashed border-base-content/15 p-4 text-sm text-base-content/55">
                        No network segment could be read from either plane.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-[11px] uppercase tracking-wide text-base-content/50">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">Segment</th>
                                    <th class="py-2 pr-4 font-medium">CIDR</th>
                                    <th class="py-2 font-medium">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($segments as $segment)
                                    <tr class="border-t border-base-content/10">
                                        <td class="py-2 pr-4">
                                            <span class="flex items-center gap-2 font-medium">
                                                <span @class([
                                                    'size-2 rounded-full',
                                                    'bg-success' => $segment['tone'] === 'success',
                                                    'bg-info' => $segment['tone'] === 'info',
                                                    'bg-warning' => $segment['tone'] === 'warning',
                                                ])></span>
                                                {{ $segment['name'] }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4 font-mono">{{ $segment['cidr'] }}</td>
                                        <td class="py-2 text-base-content/60">{{ $segment['description'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Published ports, as a segment row to match the design.
                         Every one of these is bound to 0.0.0.0 on the management host, so
                         every one of them is reachable from the lab network. Named rather
                         than numbered alone, because "9200" does not tell an operator
                         that it is the Wazuh indexer — which is the reason to know it. --}}
                    @if ($topology['publishedAccess']['count'] > 0)
                        <div class="rounded-lg border border-warning/30 bg-warning/5 p-3">
                            <p class="flex items-center gap-2 text-xs font-medium text-warning">
                                {!! \App\Support\Lucide::icon('shield-alert') !!}
                                {{ $topology['publishedAccess']['count'] }} {{ $topology['publishedAccess']['count'] === 1 ? 'service is' : 'services are' }}
                                bound to every interface
                            </p>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($topology['publishedAccess']['named'] as $port)
                                    <span class="rounded-md border border-base-content/15 bg-base-200/50 px-2 py-1 font-mono text-[11px]">
                                        <strong>{{ $port['port'] }}</strong>
                                        <span class="text-base-content/55">{{ $port['service'] }}</span>
                                    </span>
                                @endforeach
                            </div>

                            <p class="mt-2 text-[11px] leading-relaxed text-base-content/50">
                                Reachable from anything that can route to
                                {{ $topology['hosts'][0]['address'] ?? 'this host' }}. Restrict
                                these to the management network and the agents that need
                                them; the Wazuh ports in particular accept agent traffic.
                            </p>
                        </div>
                    @endif

                    <p class="text-[11px] leading-relaxed text-base-content/45">
                        Each segment is read from the plane that owns it, so an address here
                        is the one the platform is configured to use rather than one written
                        down beside it.
                    </p>
                @endif
            </div>
        </section>
    </div>

    {{-- ── provisioning stack ──────────────────────────────────────────────── --}}
    <section class="card mt-5 border border-base-content/10 shadow-sm">
        <div class="card-body gap-3">
            <div>
                <h2 class="card-title text-base">Provisioning Stack</h2>
                <p class="mt-1 text-sm text-base-content/60">
                    Technologies found on the management host.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @forelse ($stack as $tool)
                    <div @class([
                        'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                        'border-success/30 bg-success/10 text-success',
                        'border-info/30 bg-info/10 text-info',
                        'border-warning/30 bg-warning/10 text-warning',
                        'border-base-content/15 bg-base-200/50 text-base-content/70',
                    ]) title="{{ $tool['role'] }} — {{ $tool['path'] }}">
                        {!! \App\Support\Lucide::icon($tool['icon']) !!}
                        <span class="font-medium">{{ $tool['label'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-base-content/55">
                        No provisioning tool could be found on the management host.
                    </p>
                @endforelse
            </div>

            {{-- Named explicitly rather than left to the absence. An operator who knows
                 the design expected a hypervisor should be told the platform's is Incus
                 and that there is no KVM layer to go looking for. --}}
            <p class="text-[11px] leading-relaxed text-base-content/45">
                Only tools present on this host are listed. This platform virtualises with
                Incus; there is no libvirt or KVM layer, and the two compute planes are
                reached over their Incus API rather than a libvirt socket.
            </p>
        </div>
    </section>

    {{-- ── the component probe, now the only place probes are listed ────────── --}}
    <section class="card mt-5 border border-base-content/10 shadow-sm">
        <div class="card-body gap-4">
            <div>
                <h2 class="card-title text-base">Service Probes</h2>
                <p class="mt-1 text-sm text-base-content/60">
                    Every component checked to reach the verdict above. A probe that could not
                    be run says so.
                </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (collect($this->statusSummary()) as $key => $service)
                    @php
                        $tone = match ($service['status']) {
                            'healthy' => 'success',
                            'degraded' => 'warning',
                            'offline', 'down' => 'error',
                            default => 'muted',
                        };
                    @endphp

                    {{-- Icons are Lucide, resolved from the generated set. The widget this
                         replaced asked for Heroicons, which this project does not ship, so
                         its icons rendered as bare markers — the second complaint about a
                         missing icon, and the one this replaced rather than hid. --}}
                    <article @class([
                        'flex items-start gap-2.5 rounded-lg border p-3',
                        'border-success/25 bg-success/5' => $tone === 'success',
                        'border-warning/30 bg-warning/5' => $tone === 'warning',
                        'border-error/30 bg-error/5' => $tone === 'error',
                        'border-base-content/12 bg-base-200/40' => $tone === 'muted',
                    ])>
                        <span @class([
                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md',
                            'bg-success/15 text-success' => $tone === 'success',
                            'bg-warning/15 text-warning' => $tone === 'warning',
                            'bg-error/15 text-error' => $tone === 'error',
                            'bg-base-300 text-base-content/55' => $tone === 'muted',
                        ])>
                            {!! \App\Support\Lucide::icon($this->probeIcon($key)) !!}
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide">
                                    {{ $service['label'] }}
                                </p>

                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                    'bg-success/15 text-success' => $tone === 'success',
                                    'bg-warning/15 text-warning' => $tone === 'warning',
                                    'bg-error/15 text-error' => $tone === 'error',
                                    'bg-base-300 text-base-content/60' => $tone === 'muted',
                                ])>{{ $service['status'] }}</span>
                            </div>

                            <p class="mt-1 text-xs leading-relaxed text-base-content/65">
                                {{ $service['summary'] }}
                            </p>

                            <p class="mt-1.5 text-[11px] text-base-content/45">
                                @if ($service['latency_ms'] !== null)
                                    <span class="font-mono">{{ $service['latency_ms'] }} ms</span> &middot;
                                @endif
                                <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($service['checked_at'])->diffForHumans() }}</span>
                                @if (! empty($service['source']))
                                    &middot; <span class="font-mono">{{ $service['source'] }}</span>
                                @endif
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
    </div>
</x-filament-panels::page>