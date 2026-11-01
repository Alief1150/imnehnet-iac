<x-filament-widgets::widget>
    <div class="grid gap-5">

        {{-- =====================================================
             KPI / FLYONUI STATISTICS 6
             ===================================================== --}}
        <div class="grid gap-4 md:grid-cols-3">

            {{-- Running Containers --}}
            <section class="card min-w-0 border border-base-content/10 border-b-4 shadow-sm" style="border-bottom-color: #8b5cf6">
                <div class="card-body gap-3 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-base-content/70">
                                Running Containers
                            </p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-base-content">
                                {{ $runningContainers === null ? '—' : number_format($runningContainers) }}
                            </p>
                        </div>

                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg" style="background-color: #f3edff; color: #8b5cf6">
                            {!! \App\Support\Lucide::html('box') !!}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        @if ($containerPercentage !== null)
                            <span class="font-semibold tabular-nums" style="color: #8b5cf6">
                                {{ number_format($containerPercentage, 1) }}%
                            </span>
                            <span class="text-base-content/55">
                                of containers running
                            </span>
                        @else
                            <span class="font-medium text-base-content/60">
                                Inventory unavailable
                            </span>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Total Users --}}
            <section class="card min-w-0 border border-base-content/10 border-b-4 shadow-sm" style="border-bottom-color: #06b6d4">
                <div class="card-body gap-3 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-base-content/70">
                                Total Users
                            </p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-base-content">
                                {{ number_format($totalUsers) }}
                            </p>
                        </div>

                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg" style="background-color: #e6faff; color: #06b6d4">
                            {!! \App\Support\Lucide::html('users') !!}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        @if (in_array($userGrowthTrend['direction'], ['up', 'down', 'flat'], true) && $userGrowthTrend['value'] !== null)
                            <span class="font-semibold tabular-nums {{ $userGrowthTrend['direction'] === 'up' ? 'text-success' : ($userGrowthTrend['direction'] === 'down' ? 'text-error' : 'text-base-content/70') }}">
                                {{ $userGrowthTrend['direction'] === 'up' ? '↑' : ($userGrowthTrend['direction'] === 'down' ? '↓' : '→') }}
                                {{ number_format($userGrowthTrend['value'], 1) }}%
                            </span>
                            <span class="text-base-content/55">
                                vs previous period
                            </span>
                        @else
                            <span class="font-semibold" style="color: #06b6d4">
                                New
                            </span>
                            <span class="text-base-content/55">
                                no previous baseline
                            </span>
                        @endif
                    </div>
                </div>
            </section>

            {{-- System Health --}}
            <section class="card min-w-0 border border-base-content/10 border-b-4 shadow-sm {{ $systemHealth['percentage'] === 100 ? 'border-b-success' : ($systemHealth['percentage'] === null ? 'border-b-neutral' : 'border-b-warning') }}">
                <div class="card-body gap-3 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-base-content/70">
                                System Health
                            </p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-base-content">
                                {{ $systemHealth['percentage'] === null ? '—' : $systemHealth['percentage'].'%' }}
                            </p>
                        </div>

                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $systemHealth['percentage'] === 100 ? 'bg-success/10 text-success' : ($systemHealth['percentage'] === null ? 'bg-neutral/10 text-neutral' : 'bg-warning/10 text-warning') }}">
                            {!! \App\Support\Lucide::html('activity') !!}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        @if ($systemHealth['percentage'] !== null)
                            <span class="font-semibold tabular-nums {{ $systemHealth['percentage'] === 100 ? 'text-success' : 'text-warning' }}">
                                {{ $systemHealth['percentage'] }}%
                            </span>
                            <span class="text-base-content/55">
                                of services healthy
                            </span>
                        @else
                            <span class="font-medium text-base-content/60">
                                Health not verified
                            </span>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- =====================================================
             FLYONUI + APEXCHARTS
             ===================================================== --}}
        <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">

            <div class="card border border-base-content/10 shadow-sm">
                <div class="card-body">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="card-title text-base">Lab Activity</h2>
                            <p class="mt-1 text-sm text-base-content/60">
                                Total lab sessions started over the last {{ $activityDays }} days
                            </p>
                        </div>

                        <select
                            wire:model.live="activityDays"
                            class="select select-sm w-36"
                            aria-label="Lab activity period"
                        >
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                        </select>
                    </div>

                    <div
                        wire:key="lab-activity-chart-{{ $activityDays }}"
                        x-data
                        x-init="
                            const instance = window.createImnehnetLabChart(
                                $refs.chart,
                                @js($labActivity['labels']),
                                @js($labActivity['values'])
                            );
                            $cleanup(() => instance?.destroy());
                        "
                    >
                        <div
                            wire:ignore
                            x-ref="chart"
                            class="min-h-[260px] w-full"
                            aria-label="Lab activity chart"
                        ></div>
                    </div>
                </div>
            </div>

            <div class="card border border-base-content/10 shadow-sm">
                <div class="card-body">
                    <div>
                        <h2 class="card-title text-base">Course Status</h2>
                        <p class="mt-1 text-sm text-base-content/60">
                            Student progress in the guided CTF course
                        </p>
                    </div>

                    <div class="mt-3 grid items-center gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(150px,0.8fr)]">
                        <div
                            wire:ignore
                            x-data="{ instance: null }"
                            x-init="
                                instance = window.createImnehnetCourseChart(
                                    $refs.chart,
                                    @js([
                                        $courseProgress['completed'],
                                        $courseProgress['inProgress'],
                                        $courseProgress['notStarted'],
                                    ]),
                                    {{ $courseProgress['total'] }}
                                );

                                $cleanup(() => instance?.destroy());
                            "
                            class="min-w-0"
                        >
                            <div
                                x-ref="chart"
                                class="mx-auto min-h-[220px] w-full max-w-[260px]"
                                aria-label="Guided CTF course progress chart"
                            ></div>
                        </div>

                        <div class="grid gap-3">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 text-base-content/65">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color:#0ea5a4"></span>
                                    Completed
                                </span>
                                <strong>{{ $courseProgress['completed'] }}</strong>
                            </div>

                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 text-base-content/65">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color:#3b82f6"></span>
                                    In Progress
                                </span>
                                <strong>{{ $courseProgress['inProgress'] }}</strong>
                            </div>

                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 text-base-content/65">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color:#f59e0b"></span>
                                    Not Started
                                </span>
                                <strong>{{ $courseProgress['notStarted'] }}</strong>
                            </div>

                            <div class="flex items-center justify-between gap-3 border-t border-base-content/10 pt-3 text-sm">
                                <span class="font-medium text-base-content/65">
                                    Total Students
                                </span>
                                <strong>{{ $courseProgress['total'] }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- =====================================================
             RECENT ACTIVITY / FLYONUI BADGES
             ===================================================== --}}
        <div wire:poll.10s class="card border border-base-content/10 shadow-sm">
            <div class="card-body gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title text-base">Recent Activity</h2>
                    <p class="mt-1 text-sm text-base-content/60">
                        Latest events across labs, users, and system
                    </p>
                    </div>
                    <span class="badge badge-soft badge-info badge-sm">
                        Auto-refresh 10s
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Details</th>
                                <th>User</th>
                                <th>Type</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentActivity as $activity)
                                @php
                                    $eventLabel = $activity->action instanceof \App\Enums\ActivityAction
                                        ? $activity->action->label()
                                        : str($activity->action)->replace('_', ' ')->title()->toString();

                                    $eventKey = strtolower($eventLabel);

                                    $eventBadge = match (true) {
                                        str_contains($eventKey, 'completed'),
                                        str_contains($eventKey, 'finished') => 'badge-success',
                                        str_contains($eventKey, 'ready') => 'badge-info',
                                        str_contains($eventKey, 'login') => 'badge-primary',
                                        str_contains($eventKey, 'failed'),
                                        str_contains($eventKey, 'error'),
                                        str_contains($eventKey, 'deleted') => 'badge-error',
                                        str_contains($eventKey, 'reset'),
                                        str_contains($eventKey, 'stop') => 'badge-warning',
                                        default => 'badge-neutral',
                                    };

                                    $type = $activity->resource_type
                                        ? class_basename($activity->resource_type)
                                        : 'System';

                                    $typeLabel = match ($type) {
                                        'LabSession' => 'Lab Session',
                                        'LabEnvironment' => 'Environment',
                                        'ObjectiveProgress' => 'Objective Progress',
                                        default => str($type)->headline()->toString(),
                                    };

                                    $typeBadge = match ($type) {
                                        'User' => 'badge-primary',
                                        'LabSession' => 'badge-success',
                                        'LabEnvironment' => 'badge-warning',
                                        'Objective',
                                        'ObjectiveProgress' => 'badge-info',
                                        default => 'badge-neutral',
                                    };
                                @endphp

                                <tr>
                                    <td class="whitespace-nowrap text-base-content/60">
                                        {{ $activity->created_at?->diffForHumans() ?? '—' }}
                                    </td>

                                    <td>
                                        <span class="badge badge-soft {{ $eventBadge }} badge-sm whitespace-nowrap">
                                            {{ $eventLabel }}
                                        </span>
                                    </td>

                                    <td class="min-w-72 text-base-content/65">
                                        {{ $activity->description ?: 'Activity recorded' }}
                                    </td>

                                    <td class="whitespace-nowrap font-medium">
                                        {{ $activity->actor?->name ?? 'System' }}
                                    </td>

                                    <td>
                                        <span class="badge badge-soft {{ $typeBadge }} badge-sm whitespace-nowrap">
                                            {{ $typeLabel }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-base-content/50">
                                        No recent activity recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
