<x-filament-panels::page>
    @php
        $resources = $this->resources();
        $trends = $this->trends();
        $health = collect($this->health())->whereIn('group', ['monitoring', 'automation']);
        $refreshed = $this->refreshedAt();
        $keys = ['cpu', 'memory', 'storage', 'containers'];
    @endphp

    <div wire:poll.10s class="space-y-6">

        {{-- Honest freshness statement. This is polling, not a live stream. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 text-teal-500" />
                <span>
                    @if ($refreshed)
                        Probes last refreshed {{ $refreshed->diffForHumans() }}
                    @else
                        Probes have not completed yet
                    @endif
                </span>
            </div>
            <span class="text-xs uppercase tracking-wide text-gray-400">
                Polling every 10s &middot; not real-time
            </span>
        </div>

        {{-- Resource capacity --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($keys as $key)
                @php
                    $usage = $resources[$key] ?? null;
                @endphp

                @if (! $usage)
                    <article class="rounded-xl border border-dashed border-gray-300 p-5 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ ucfirst(str_replace('_', ' ', $key)) }} is not reported by the metrics backend.
                        </p>
                    </article>
                @endif

                @if ($usage)
                    @php
                        $usagePercent = $usage['used_percent'];
                        $usageCount = $usage['count'];
                        $tone = match ($usage['status']) {
                            'healthy' => 'bg-teal-500',
                            'degraded' => 'bg-amber-500',
                            'offline' => 'bg-rose-500',
                            default => 'bg-gray-400',
                        };
                        $stroke = match ($usage['status']) {
                            'healthy' => '#14b8a6',
                            'degraded' => '#f59e0b',
                            'offline' => '#f43f5e',
                            default => '#94a3b8',
                        };
                        $badge = match ($usage['status']) {
                            'healthy' => 'bg-teal-500/15 text-teal-700 dark:text-teal-300',
                            'degraded' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
                            'offline' => 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
                            default => 'bg-gray-500/15 text-gray-600 dark:text-gray-300',
                        };
                        $samples = $trends[$key]['samples'] ?? [];
                    @endphp

                    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $usage['label'] }}</h3>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $badge }}">
                                {{ $usage['status'] }}
                            </span>
                        </div>

                        <p class="mt-3 font-mono text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            @if ($usagePercent !== null)
                                {{ $usagePercent }}%
                            @elseif ($usageCount !== null)
                                {{ $usageCount }}
                            @else
                                n/a
                            @endif
                        </p>

                        @if ($usagePercent !== null)
                            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-full rounded-full {{ $tone }}" style="width: {{ min(100, max(0, $usagePercent)) }}%"></div>
                            </div>
                        @endif

                        @if (is_array($samples) && $samples !== [])
                            @php
                                $low = min($samples);
                                $high = max($samples);
                                $range = $high - $low ?: 1;
                                $points = collect($samples)
                                    ->map(fn (float $value, int $index): string => sprintf(
                                        '%d,%.1f',
                                        (int) round($index / max(1, count($samples) - 1) * 100),
                                        30 - (($value - $low) / $range) * 28,
                                    ))
                                    ->implode(' ');
                            @endphp
                            <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="mt-4 h-10 w-full">
                                <polyline points="{{ $points }}" fill="none" stroke="{{ $stroke }}" stroke-width="1.5"
                                         vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                            </svg>
                            <p class="mt-1 text-[11px] text-gray-400">
                                {{ count($samples) }} samples over {{ (int) config('infrastructure.resource_trend_minutes') }} minutes
                            </p>
                        @endif

                        <p class="mt-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $usage['summary'] }}</p>
                    </article>
                @endif
            @endforeach
        </section>

        {{-- Monitoring and automation stack --}}
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Monitoring &amp; Automation Stack
            </h2>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($health as $service)
                    <article @class([
                        'rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-900',
                        'border-teal-500/40' => $service['status'] === 'healthy',
                        'border-amber-500/40' => $service['status'] === 'degraded',
                        'border-rose-500/40' => $service['status'] === 'offline',
                        'border-gray-200 dark:border-gray-800' => ! in_array($service['status'], ['healthy', 'degraded', 'offline']),
                    ])>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $service['label'] }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $service['status'] }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $service['summary'] }}</p>
                        @if ($service['latency_ms'] !== null)
                            <p class="mt-2 font-mono text-[11px] text-gray-400">{{ $service['latency_ms'] }} ms</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Provenance: where each number came from --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Data Sources</h2>
            <dl class="mt-3 space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                @foreach ($resources as $usage)
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                        <dt class="font-medium text-gray-700 dark:text-gray-300 sm:w-28">{{ $usage['label'] }}</dt>
                        <dd class="break-all font-mono">{{ $usage['source'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</x-filament-panels::page>
