<x-filament-panels::page>
    @php
        $summary = $this->statusSummary();
        $groups = [
            'management_plane' => 'Planes',
            'compute_plane' => 'Planes',
            'automation' => 'Planes',
            'monitoring' => 'Monitoring',
            'security' => 'Security',
        ];
    @endphp

    <div wire:poll.10s class="space-y-6">
        @foreach ($groups as $group => $heading)
            @php($entries = collect($summary)->where('group', $group))

            @if ($entries->isEmpty())
                @continue
            @endif

            <section>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $heading }}
                </h2>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($entries as $service)
                        <article @class([
                            'rounded-xl border bg-white p-4 shadow-sm transition dark:bg-gray-900',
                            'border-teal-500/40' => $service['status'] === 'healthy',
                            'border-amber-500/40' => $service['status'] === 'degraded',
                            'border-rose-500/40' => $service['status'] === 'offline',
                            'border-gray-200 dark:border-gray-800' => ! in_array($service['status'], ['healthy', 'degraded', 'offline']),
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $service['label'] }}</h3>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    'bg-teal-500/15 text-teal-700 dark:text-teal-300' => $service['status'] === 'healthy',
                                    'bg-amber-500/15 text-amber-700 dark:text-amber-300' => $service['status'] === 'degraded',
                                    'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $service['status'] === 'offline',
                                    'bg-gray-500/15 text-gray-600 dark:text-gray-300' => ! in_array($service['status'], ['healthy', 'degraded', 'offline']),
                                ])>{{ $service['status'] }}</span>
                            </div>

                            <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                {{ $service['summary'] }}
                            </p>

                            <dl class="mt-3 space-y-1 border-t border-gray-100 pt-2 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                @if ($service['latency_ms'] !== null)
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-gray-600 dark:text-gray-300">Latency</dt>
                                        <dd class="font-mono">{{ $service['latency_ms'] }} ms</dd>
                                    </div>
                                @endif
                                @if (! empty($service['version']))
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-gray-600 dark:text-gray-300">Version</dt>
                                        <dd class="truncate font-mono">{{ $service['version'] }}</dd>
                                    </div>
                                @endif
                                <div class="flex gap-2">
                                    <dt class="font-medium text-gray-600 dark:text-gray-300">Source</dt>
                                    <dd class="truncate font-mono">{{ $service['source'] }}</dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="font-medium text-gray-600 dark:text-gray-300">Checked</dt>
                                    <dd>{{ \Illuminate\Support\Carbon::parse($service['checked_at'])->diffForHumans() }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Ungrouped services</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (collect($summary)->reject(fn (array $s): bool => isset($groups[$s['group']])) as $service)
                    <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $service['label'] }}</h3>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                {{ $service['status'] }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $service['summary'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
