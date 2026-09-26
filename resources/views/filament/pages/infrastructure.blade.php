<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->statusSummary() as $service)
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-950 dark:text-white">{{ $service['label'] }}</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ $service['status'] }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $service['summary'] }}</p>
                <dl class="mt-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <div><dt class="inline font-medium">Source:</dt> <dd class="inline">{{ $service['source'] }}</dd></div>
                    <div><dt class="inline font-medium">Checked:</dt> <dd class="inline">{{ $service['checked_at'] }}</dd></div>
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
