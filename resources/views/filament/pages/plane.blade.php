<x-filament-panels::page>
    @php
        $sections = $this->healthSections();
        $failed = $this->healthProbeFailed();
    @endphp

    <div wire:poll.10s class="space-y-6">
        <section>
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ $this->healthHeading() }}
            </h2>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                {{ $this->healthDescription() }}
            </p>
        </section>

        {{-- A failed probe and a healthy host both produce an empty grid, and
             they mean opposite things. Saying which one this is matters more than
             the cards. --}}
        @if ($failed)
            <section class="rounded-xl border border-rose-500/40 bg-rose-500/5 p-5 dark:bg-rose-500/10">
                <h3 class="text-sm font-semibold text-rose-900 dark:text-rose-200">NOT CONFIGURED</h3>
                <p class="mt-1 text-xs text-rose-800/90 dark:text-rose-200/80">
                    The infrastructure probe could not be completed, so nothing on this page can be
                    claimed to be healthy or unhealthy. This is a failure to read the state, not a
                    report that the state is empty.
                </p>
            </section>
        @else
            @foreach ($sections as $group => $entries)
                <section>
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ str_replace('_', ' ', $group) }}
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
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $service['label'] }}
                                    </h3>
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
        @endif

        <p class="text-[11px] text-gray-500 dark:text-gray-400">
            This page reads the same probes as
            <a href="{{ \App\Filament\Pages\Infrastructure::getUrl(panel: 'admin') }}"
               class="underline hover:text-gray-700 dark:hover:text-gray-200">Infrastructure</a>.
            Opening both cannot make them disagree, because neither re-probes.
        </p>
    </div>
</x-filament-panels::page>