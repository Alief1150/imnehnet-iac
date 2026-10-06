<x-filament-widgets::widget>
    <div class="grid gap-5">

        {{-- =====================================================
             KPI / FLYONUI STATISTICS
             ===================================================== --}}
        <div class="grid gap-4 md:grid-cols-3">

            <div class="card border border-base-content/10 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-base-content/70">Active Labs</p>
                            <p class="mt-1 text-3xl font-semibold text-base-content">
                                {{ number_format($activeLabs) }}
                            </p>
                        </div>

                        <div class="flex size-11 items-center justify-center rounded-lg bg-success/10 text-success">
                            <x-heroicon-o-cube-transparent class="size-6" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($labActivityTrend['direction'] === 'up')
                            <span class="badge badge-soft badge-success badge-sm">
                                ↑ {{ $labActivityTrend['value'] }}%
                            </span>
                        @elseif ($labActivityTrend['direction'] === 'down')
                            <span class="badge badge-soft badge-error badge-sm">
                                ↓ {{ $labActivityTrend['value'] }}%
                            </span>
                        @elseif ($labActivityTrend['direction'] === 'new')
                            <span class="badge badge-soft badge-info badge-sm">
                                New
                            </span>
                        @else
                            <span class="badge badge-soft badge-neutral badge-sm">
                                0%
                            </span>
                        @endif

                        <span class="text-base-content/55">
                            {{ $labActivityTrend['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card border border-base-content/10 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-base-content/70">Total Users</p>
                            <p class="mt-1 text-3xl font-semibold text-base-content">
                                {{ number_format($totalUsers) }}
                            </p>
                        </div>

                        <div class="flex size-11 items-center justify-center rounded-lg bg-info/10 text-info">
                            <x-heroicon-o-users class="size-6" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($userGrowthTrend['direction'] === 'up')
                            <span class="badge badge-soft badge-success badge-sm">
                                ↑ {{ $userGrowthTrend['value'] }}%
                            </span>
                        @elseif ($userGrowthTrend['direction'] === 'down')
                            <span class="badge badge-soft badge-error badge-sm">
                                ↓ {{ $userGrowthTrend['value'] }}%
                            </span>
                        @elseif ($userGrowthTrend['direction'] === 'new')
                            <span class="badge badge-soft badge-info badge-sm">
                                New
                            </span>
                        @else
                            <span class="badge badge-soft badge-neutral badge-sm">
                                0%
                            </span>
                        @endif

                        <span class="text-base-content/55">
                            {{ $userGrowthTrend['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card border border-base-content/10 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-base-content/70">System Health</p>

                            <p class="mt-1 text-3xl font-semibold text-base-content">
                                {{ $systemHealth['percentage'] === null ? '—' : $systemHealth['percentage'].'%' }}
                            </p>
                        </div>

                        <div class="flex size-11 items-center justify-center rounded-lg bg-success/10 text-success">
                            <x-heroicon-o-heart class="size-6" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        @if ($systemHealth['percentage'] === null)
                            <span class="badge badge-soft badge-neutral badge-sm">
                                Unknown
                            </span>
                        @elseif ($systemHealth['percentage'] === 100)
                            <span class="badge badge-soft badge-success badge-sm">
                                Healthy
                            </span>
                        @else
                            <span class="badge badge-soft badge-warning badge-sm">
                                Attention
                            </span>
                        @endif

                        <span class="text-base-content/55">
                            {{ $systemHealth['label'] }}
                        </span>
                    </div>
                </div>
            </div>
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
                            Distribution of guided CTF students
                        </p>
                    </div>

                    <div class="mt-3 grid items-center gap-3 sm:grid-cols-[minmax(180px,1fr)_minmax(150px,0.8fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(180px,1fr)_minmax(150px,0.8fr)]">
                        <div
                            wire:key="course-status-chart-{{ $courseStatus['completed'] }}-{{ $courseStatus['inProgress'] }}-{{ $courseStatus['notStarted'] }}"
                            x-data
                            x-init="
                                const instance = window.createImnehnetCourseChart(
                                    $refs.chart,
                                    @js([
                                        $courseStatus['completed'],
                                        $courseStatus['inProgress'],
                                        $courseStatus['notStarted'],
                                    ])
                                );
                                $cleanup(() => instance?.destroy());
                            "
                            class="relative"
                        >
                            <div x-ref="chart" class="min-h-[250px]"></div>

                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-2xl font-semibold text-base-content">
                                        {{ number_format($courseStatus['total']) }}
                                    </div>
                                    <div class="text-xs text-base-content/55">
                                        Total Students
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2.5">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="size-2.5 rounded-full" style="background-color: #0ea5a4"></span>
                                <span class="flex-1 text-base-content/65">Completed</span>
                                <strong>{{ $courseStatus['completed'] }}</strong>
                            </div>

                            <div class="flex items-center gap-2 text-sm">
                                <span class="size-2.5 rounded-full bg-info"></span>
                                <span class="flex-1 text-base-content/65">In Progress</span>
                                <strong>{{ $courseStatus['inProgress'] }}</strong>
                            </div>

                            <div class="flex items-center gap-2 text-sm">
                                <span class="size-2.5 rounded-full bg-warning"></span>
                                <span class="flex-1 text-base-content/65">Not Started</span>
                                <strong>{{ $courseStatus['notStarted'] }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- =====================================================
             RECENT ACTIVITY / FLYONUI BADGES
             ===================================================== --}}
        <div class="card border border-base-content/10 shadow-sm">
            <div class="card-body gap-4">
                <div>
                    <h2 class="card-title text-base">Recent Activity</h2>
                    <p class="mt-1 text-sm text-base-content/60">
                        Latest events across labs, users, and system
                    </p>
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
