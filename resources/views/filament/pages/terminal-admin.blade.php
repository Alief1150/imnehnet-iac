{{--
    The administrative console.

    A real shell on an infrastructure node, opened by AdminTerminalService and nothing
    else. The frame only appears once a session exists, so there is no state in which the
    page shows a terminal that is not there.

    The target list carries each node's health from InfrastructureTopologyService, so the
    three cards here and the three plane cards on the Infrastructure page cannot disagree
    about whether a node is up.
--}}
<x-filament-panels::page>
    @php
        $targets = collect($this->adminTargets());
        $selected = $targets->firstWhere('key', $this->selectedAdminTarget) ?? $targets->first();
        $session = $this->adminSession();
        $state = $this->adminSessionState();
        $url = $this->adminUrl();
        $embedUrl = $this->adminEmbedUrl();
        $frameSrc = $this->adminEmbedFrameSrc();
        $browserAccess = (bool) config('infrastructure.terminal.browser_access_enabled');
    @endphp

    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Select Target</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the infrastructure node for this administrative terminal.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($session)
                            <button type="button" wire:click="closeAdminSession"
                                    wire:loading.attr="disabled"
                                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 px-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5">
                                {!! \App\Support\Lucide::html('trash') !!}
                                Close Session
                            </button>
                        @endif

                        <button type="button" wire:click="openAdminSession"
                                wire:loading.attr="disabled"
                                wire:target="openAdminSession"
                                class="inline-flex h-9 items-center gap-2 rounded-lg bg-teal-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 disabled:opacity-60">
                            {!! \App\Support\Lucide::html($session ? 'rotate-ccw' : 'plus') !!}
                            {{ $session ? 'Reconnect' : 'New Session' }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($targets as $target)
                        @php
                            $isSelected = $selected && $selected['key'] === $target['key'];
                            $isOnline = $target['health'] === 'healthy';
                            $isDegraded = $target['health'] === 'degraded';
                        @endphp

                        <button type="button" wire:click="selectAdminTarget('{{ $target['key'] }}')"
                                wire:key="admin-terminal-target-{{ $target['key'] }}"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                @class([
                                    'group relative min-h-24 rounded-xl border p-4 text-left transition duration-150',
                                    'border-teal-500 bg-teal-50/70 ring-1 ring-teal-500/15 dark:border-teal-500 dark:bg-teal-500/10' => $isSelected,
                                    'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20 dark:hover:bg-white/[0.03]' => ! $isSelected,
                                ])>
                            <div class="flex items-start gap-3">
                                <span @class([
                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border',
                                    'border-teal-500 bg-teal-500' => $isSelected,
                                    'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900' => ! $isSelected,
                                ])>
                                    @if ($isSelected)
                                        <span class="size-2 rounded-full bg-white"></span>
                                    @endif
                                </span>

                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    {!! \App\Support\Lucide::html($target['key'] === 'lab-env' ? 'database' : ($target['key'] === 'lab-atk' ? 'shield' : 'server')) !!}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $target['name'] }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $target['role'] }}</span>
                                </span>

                                <span @class([
                                    'mt-1 inline-flex items-center gap-1.5 text-xs font-medium',
                                    'text-emerald-600 dark:text-emerald-400' => $isOnline,
                                    'text-amber-600 dark:text-amber-400' => $isDegraded,
                                    'text-rose-600 dark:text-rose-400' => ! $isOnline && ! $isDegraded,
                                    'text-gray-500 dark:text-gray-400' => $target['health'] === 'unknown',
                                ])>
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $target['healthLabel'] }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- What opening a shell actually does, said where the button is.
                     A control that grants root-adjacent access with no statement of what
                     it grants is a control nobody can consent to. --}}
                <p class="mt-4 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                    Opening a session runs a shell on the selected node as a privileged account and is
                    recorded in the activity log. The listener is bound to loopback; the console
                    appears embedded below, and nothing about it is reachable from the lab network.
                    Opening a new session on a node closes the one already there and revokes its ticket.
                </p>
            </div>
        </section>

        @if ($selected)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <header class="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            {!! \App\Support\Lucide::html('terminal') !!}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $selected['name'] }}</h2>
                                {{-- Three states, not two. A shell that is running but that
                                     this deployment will not hand to a browser is neither
                                     Connected nor Disconnected, and collapsing it into
                                     either one sends the operator to look for a dead
                                     listener that is in fact fine. --}}
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $state['connected'] && $state['reachable'],
                                    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $state['connected'] && ! $state['reachable'],
                                    'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' => ! $state['connected'],
                                ])>
                                    <span @class([
                                        'size-1.5 rounded-full',
                                        'bg-emerald-500' => $state['connected'] && $state['reachable'],
                                        'bg-amber-500' => $state['connected'] && ! $state['reachable'],
                                        'bg-gray-400' => ! $state['connected'],
                                    ])></span>
                                    {{ $state['label'] }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $selected['address'] ?: 'Address unavailable' }}
                                @if ($session?->shell_user)
                                    &middot; {{ $session->shell_user }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($session)
                        <span class="inline-flex h-8 items-center gap-2 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-500 dark:border-white/15 dark:text-gray-400"
                              title="The console is embedded below">
                            {!! \App\Support\Lucide::html('terminal') !!}
                            Embedded
                        </span>
                    @endif
                </header>

                @if (! $browserAccess)
                    {{-- The deployment is not handing shells to browsers. Said here rather
                         than implied by an absence: an administrator looking at a blank
                         panel has no way to tell a misconfiguration from a fault. --}}
                    <div class="flex min-h-[31rem] flex-col items-center justify-center bg-[#0b1118] px-6 py-12 text-center">
                        <div class="max-w-md">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-amber-400 shadow-sm">
                                {!! \App\Support\Lucide::html('terminal') !!}
                            </div>
                            <h3 class="mt-4 text-sm font-semibold text-white">Browser access is switched off</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">
                                @if ($session)
                                    The shell on <strong>{{ $selected['name'] }}</strong> is running, but this
                                    deployment does not hand terminal addresses to browsers, so there is nothing
                                    for a browser to open. This is a deployment setting, not a fault in the
                                    session.
                                @else
                                    Sessions can be opened, but this deployment does not hand terminal addresses
                                    to browsers. Set
                                    <code class="rounded bg-slate-800 px-1 py-0.5 font-mono text-xs">infrastructure.terminal.browser_access_enabled</code>
                                    to reach a shell from a page.
                                @endif
                            </p>
                        </div>
                    </div>
                @elseif (! $embedUrl)
                    {{-- No frame is mounted here, and that is deliberate.

                         ttyd's -c is HTTP Basic auth — verified on this host: no credential
                         answers 401 with www-authenticate: Basic realm="ttyd", the right
                         one answers 200 and upgrades the websocket. An iframe cannot send
                         an Authorization header; there is no attribute for it. So an
                         embedded frame here would show a blank 401 that looks like a
                         broken shell, and the only reason to discover that is to open the
                         page and try. The shell opens in its own tab instead, where the
                         browser presents the challenge and the operator answers it. --}}
                    <div class="flex min-h-[31rem] flex-col items-center justify-center bg-[#0b1118] px-6 py-12 text-center">
                        <div class="max-w-md">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-teal-400 shadow-sm">
                                {!! \App\Support\Lucide::html('terminal') !!}
                            </div>
                            <h3 class="mt-4 text-sm font-semibold text-white">
                                {{ match (true) {
                                    $session !== null => 'Shell running, browser cannot attach',
                                    $selected['health'] !== 'healthy' => 'Target not reachable',
                                    default => 'No open session',
                                } }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">
                                @if ($session !== null)
                                    The listener is up on the loopback port, but the session has no address the
                                    browser will be given. Close it and open a new one.
                                @elseif ($selected['health'] !== 'healthy')
                                    The probe for {{ $selected['name'] }} did not report a healthy state, so a shell
                                    would not survive. See the Infrastructure page for what it reports.
                                @else
                                    Choose <strong>{{ $selected['name'] }}</strong> and press
                                    <strong>New Session</strong>. The console opens below, attached to a
                                    shell on the node itself.
                                @endif
                            </p>
                        </div>
                    </div>
                @else
                    {{-- The console, embedded. This branch renders only when the slot is
                         leased for it — embedUrl() is null otherwise — so there is no
                         state in which the page mounts a frame with nothing behind it.
                         The address names only the slot; the ticket travels in a cookie
                         queued when the session opened, which is why there is no
                         password on this page to copy. --}}
                    <div class="bg-[#0b1118]">
                        <iframe src="{{ $frameSrc }}" title="Administrative shell on {{ $selected['name'] }}"
                                class="block w-full border-0 bg-[#0b1118]"
                                style="height: min(72vh, 44rem);"
                                allow="clipboard-read; clipboard-write"></iframe>
                    </div>
                    <div class="border-t border-slate-800 bg-[#0b1118] px-5 py-3">
                        <p class="text-[11px] leading-relaxed text-slate-500">
                            This frame is the shell on {{ $selected['name'] }} itself, as
                            {{ $session?->shell_user ?? 'the session user' }}. Closing the session stops
                            the listener and revokes its ticket at once; closing this page does
                            not — the reaper does, after the session's own window expires.
                        </p>
                    </div>
                @endif

                <footer class="grid border-t border-gray-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-gray-200 dark:border-white/10 dark:bg-gray-900 dark:sm:divide-white/10">
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">{!! \App\Support\Lucide::html('clock') !!}</span>
                        <div><p class="text-[11px] text-gray-400">Session Duration</p><p class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $state['duration'] }}</p></div>
                    </div>
                    <div class="flex items-center gap-3 border-t border-gray-200 px-5 py-3.5 sm:border-t-0 dark:border-white/10">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">{!! \App\Support\Lucide::html('users') !!}</span>
                        <div class="min-w-0"><p class="text-[11px] text-gray-400">User</p><p class="truncate text-xs font-semibold text-gray-700 dark:text-gray-200">{{ auth()->user()?->name ?? 'Administrator' }}</p></div>
                    </div>
                    <div class="flex items-center gap-3 border-t border-gray-200 px-5 py-3.5 sm:border-t-0 dark:border-white/10">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">{!! \App\Support\Lucide::html('server') !!}</span>
                        <div class="min-w-0"><p class="text-[11px] text-gray-400">Target</p><p class="truncate font-mono text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $selected['name'] }}</p></div>
                    </div>
                </footer>
            </section>
        @endif
    </div>
</x-filament-panels::page>