<x-filament-panels::page>
    @php
        $targets = collect($this->adminTargets());
        $selected = $targets->firstWhere('key', $this->selectedAdminTarget) ?? $targets->first();
    @endphp

    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Select Target</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the infrastructure node for this administrative terminal.</p>
                    </div>

                    <button type="button" disabled class="inline-flex h-9 cursor-not-allowed items-center gap-2 rounded-lg bg-teal-600 px-3.5 text-sm font-semibold text-white opacity-55 shadow-sm">
                        {!! \App\Support\Lucide::html('plus') !!}
                        New Session
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($targets as $target)
                        @php
                            $isSelected = $selected && $selected['key'] === $target['key'];
                            $isOnline = $target['health'] === 'healthy';
                            $isDegraded = $target['health'] === 'degraded';
                        @endphp

                        <button
                            type="button"
                            wire:click="selectAdminTarget('{{ $target['key'] }}')"
                            wire:key="admin-terminal-target-{{ $target['key'] }}"
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                            @class([
                                'group relative min-h-24 rounded-xl border p-4 text-left transition duration-150',
                                'border-teal-500 bg-teal-50/70 ring-1 ring-teal-500/15 dark:border-teal-500 dark:bg-teal-500/10' => $isSelected,
                                'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20 dark:hover:bg-white/[0.03]' => ! $isSelected,
                            ])
                        >
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
                                    {!! \App\Support\Lucide::html($target['key'] === 'lab-env' ? 'database' : 'server') !!}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $target['name'] }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $target['role'] }}</span>
                                </span>

                                <span @class([
                                    'mt-1 inline-flex items-center gap-1.5 text-xs font-medium',
                                    'text-emerald-600 dark:text-emerald-400' => $isOnline,
                                    'text-amber-600 dark:text-amber-400' => $isDegraded,
                                    'text-rose-600 dark:text-rose-400' => ! $isOnline && ! $isDegraded && $target['health'] === 'offline',
                                    'text-gray-500 dark:text-gray-400' => $target['health'] === 'unknown',
                                ])>
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $target['healthLabel'] }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
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
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    <span class="size-1.5 rounded-full bg-gray-400"></span>
                                    Disconnected
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $selected['address'] ?: 'Address unavailable' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" disabled title="Available after terminal transport integration" class="inline-flex size-8 cursor-not-allowed items-center justify-center rounded-lg text-gray-400 opacity-60">
                            {!! \App\Support\Lucide::html('maximize') !!}
                        </button>
                        <button type="button" disabled title="Available after terminal transport integration" class="inline-flex size-8 cursor-not-allowed items-center justify-center rounded-lg text-gray-400 opacity-60">
                            {!! \App\Support\Lucide::html('ellipsis-vertical') !!}
                        </button>
                    </div>
                </header>

                <div class="relative flex min-h-[31rem] items-center justify-center bg-[#0b1118] px-6 py-12 text-center">
                    <div class="max-w-md">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-teal-400 shadow-sm">
                            {!! \App\Support\Lucide::html('terminal') !!}
                        </div>
                        <h3 class="mt-4 text-sm font-semibold text-white">Terminal transport not connected</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">
                            The administrative terminal UI is ready. Real-time shell transport for {{ $selected['name'] }} will be attached in the WebSocket integration phase.
                        </p>
                    </div>
                </div>

                <footer class="grid border-t border-gray-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-gray-200 dark:border-white/10 dark:bg-gray-900 dark:sm:divide-white/10">
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">{!! \App\Support\Lucide::html('clock') !!}</span>
                        <div><p class="text-[11px] text-gray-400">Session Duration</p><p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Not started</p></div>
                    </div>
                    <div class="flex items-center gap-3 border-t border-gray-200 px-5 py-3.5 sm:border-t-0 dark:border-white/10">
                        <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">{!! \App\Support\Lucide::html('user') !!}</span>
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
