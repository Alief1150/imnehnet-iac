<x-filament-panels::page>
    @php($summary = $this->summary())

    <div wire:poll.15s class="space-y-6">

        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Security posture is reported by the SIEM and by portal state. Nothing on this page is
                simulated: an unconnected backend is shown as unconnected.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($summary as $entry)
                <article @class([
                    'rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-900',
                    'border-teal-500/40' => $entry['status'] === 'healthy',
                    'border-amber-500/40' => $entry['status'] === 'degraded',
                    'border-rose-500/40' => $entry['status'] === 'offline',
                    'border-gray-200 dark:border-gray-800' => ! in_array($entry['status'], ['healthy', 'degraded', 'offline']),
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $entry['label'] }}</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $entry['summary'] }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide',
                            'bg-teal-500/15 text-teal-700 dark:text-teal-300' => $entry['status'] === 'healthy',
                            'bg-amber-500/15 text-amber-700 dark:text-amber-300' => $entry['status'] === 'degraded',
                            'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $entry['status'] === 'offline',
                            'bg-gray-500/15 text-gray-600 dark:text-gray-300' => ! in_array($entry['status'], ['healthy', 'degraded', 'offline']),
                        ])>{{ $entry['status'] }}</span>
                    </div>

                    @if ($entry['key'] === 'wazuh' && $entry['alert_count'] > 0)
                        <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-gray-200 pt-4 text-center dark:border-gray-800">
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-gray-500">Active</dt>
                                <dd class="font-mono text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $entry['alert_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-gray-500">Critical</dt>
                                <dd class="font-mono text-xl font-semibold text-rose-600 dark:text-rose-400">{{ $entry['critical_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-gray-500">Warning</dt>
                                <dd class="font-mono text-xl font-semibold text-amber-600 dark:text-amber-400">{{ $entry['warning_count'] }}</dd>
                            </div>
                        </dl>
                    @endif

                    <dl class="mt-4 space-y-1 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <div class="flex gap-2">
                            <dt class="font-medium text-gray-600 dark:text-gray-300">Source</dt>
                            <dd class="break-all font-mono">{{ $entry['source'] }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="font-medium text-gray-600 dark:text-gray-300">Checked</dt>
                            <dd>{{ \Illuminate\Support\Carbon::parse($entry['checked_at'])->diffForHumans() }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>

        <section class="rounded-xl border border-dashed border-gray-300 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Integration boundary</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                This portal is a presentation and control plane only. Students can never reach
                <span class="font-mono">LAB-ENV</span> or change firewall policy from here; micro-segmentation is applied
                on the compute plane and only its resulting state is shown.
            </p>
        </section>
    </div>
</x-filament-panels::page>
