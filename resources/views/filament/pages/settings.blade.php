<x-filament-panels::page>
    @php
        $sections = collect($this->sections())->groupBy('group');
    @endphp

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Read only</h2>
            <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                Everything below is read from configuration, so it cannot drift from what the portal is
                actually doing. Nothing here can be changed from the browser: the settings that matter
                live in the deployment's environment file, and a switch on this page that appeared to
                write them would be lying about whether it worked.
            </p>
        </section>

        @foreach ($sections as $group => $entries)
            <section>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $group }}
                </h2>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($entries as $entry)
                        <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $entry['label'] }}
                            </h3>
                            <p class="mt-1 font-mono text-xs text-gray-700 dark:text-gray-200">
                                {{ $entry['value'] }}
                            </p>
                            <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                {{ $entry['note'] }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>