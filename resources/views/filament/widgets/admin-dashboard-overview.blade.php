@php
    $maxActivity = max(1, max($labActivity['values']));

    $chartPoints = collect($labActivity['values'])->map(function ($value, $index) use ($maxActivity, $labActivity) {
        $x = count($labActivity['values']) > 1
            ? ($index / (count($labActivity['values']) - 1)) * 100
            : 0;

        $y = 92 - (($value / $maxActivity) * 76);

        return number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    })->implode(' ');

    $courseTotal = max(1, $courseStatus['total']);

    $completedPercentage = ($courseStatus['completed'] / $courseTotal) * 100;
    $progressPercentage = ($courseStatus['inProgress'] / $courseTotal) * 100;
    $notStartedPercentage = ($courseStatus['notStarted'] / $courseTotal) * 100;

    $donut = sprintf(
        'conic-gradient(#0ea5a4 0 %.2f%%, #3b82f6 %.2f%% %.2f%%, #f59e0b %.2f%% 100%%)',
        $completedPercentage,
        $completedPercentage,
        $completedPercentage + $progressPercentage,
        $completedPercentage + $progressPercentage
    );
@endphp

<x-filament-widgets::widget>
    <div class="im-admin-dashboard">
        <div class="im-kpi-grid">
            <section class="im-kpi-card">
                <div class="im-kpi-icon im-kpi-icon--teal">
                    <x-heroicon-o-cube-transparent />
                </div>

                <div class="im-kpi-content">
                    <span class="im-kpi-label">Active Labs</span>
                    <strong>{{ number_format($activeLabs) }}</strong>
                    <span class="im-kpi-caption">Ready or running lab sessions</span>
                </div>

                <div class="im-mini-bars" aria-hidden="true">
                    @foreach ([7, 13, 9, 19, 12, 23, 16, 28, 18] as $height)
                        <span style="height: {{ $height }}px"></span>
                    @endforeach
                </div>
            </section>

            <section class="im-kpi-card">
                <div class="im-kpi-icon im-kpi-icon--blue">
                    <x-heroicon-o-users />
                </div>

                <div class="im-kpi-content">
                    <span class="im-kpi-label">Total Users</span>
                    <strong>{{ number_format($totalUsers) }}</strong>
                    <span class="im-kpi-caption">Registered portal accounts</span>
                </div>

                <div class="im-mini-bars im-mini-bars--blue" aria-hidden="true">
                    @foreach ([9, 14, 12, 20, 17, 25, 21, 29, 24] as $height)
                        <span style="height: {{ $height }}px"></span>
                    @endforeach
                </div>
            </section>

            <section class="im-kpi-card">
                <div class="im-kpi-icon im-kpi-icon--teal">
                    <x-heroicon-o-heart />
                </div>

                <div class="im-kpi-content">
                    <span class="im-kpi-label">System Health</span>
                    <strong>
                        {{ $systemHealth['percentage'] === null ? '—' : $systemHealth['percentage'].'%' }}
                    </strong>
                    <span class="im-kpi-caption">{{ $systemHealth['label'] }}</span>
                </div>

                <div class="im-mini-bars" aria-hidden="true">
                    @foreach ([8, 11, 15, 21, 17, 25, 20, 27, 18] as $height)
                        <span style="height: {{ $height }}px"></span>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="im-dashboard-middle">
            <section class="im-dashboard-panel im-activity-panel">
                <div class="im-panel-heading">
                    <div>
                        <h2>Lab Activity</h2>
                        <p>Total lab sessions started over the last {{ $activityDays }} days</p>
                    </div>

                    <select
                        wire:model.live="activityDays"
                        class="im-period-select"
                        aria-label="Lab activity period"
                    >
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>

                <div class="im-line-chart">
                    <div class="im-chart-y">
                        <span>{{ $maxActivity }}</span>
                        <span>{{ (int) round($maxActivity / 2) }}</span>
                        <span>0</span>
                    </div>

                    <div class="im-chart-canvas">
                        <div class="im-grid-line im-grid-line--one"></div>
                        <div class="im-grid-line im-grid-line--two"></div>
                        <div class="im-grid-line im-grid-line--three"></div>

                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-label="Lab activity chart">
                            <polyline
                                points="{{ $chartPoints }}"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>

                        <div class="im-chart-labels">
                            @php
                                $lastLabelIndex = count($labActivity['labels']) - 1;
                                $labelIndexes = array_values(array_unique([
                                    0,
                                    (int) round($lastLabelIndex * 0.25),
                                    (int) round($lastLabelIndex * 0.50),
                                    (int) round($lastLabelIndex * 0.75),
                                    $lastLabelIndex,
                                ]));
                            @endphp

                            @foreach ($labelIndexes as $labelIndex)
                                <span>{{ $labActivity['labels'][$labelIndex] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="im-dashboard-panel">
                <div class="im-panel-heading">
                    <div>
                        <h2>Course Status</h2>
                        <p>Distribution of guided CTF students</p>
                    </div>
                </div>

                <div class="im-course-layout">
                    <div class="im-donut" style="background: {{ $donut }}">
                        <div class="im-donut-hole">
                            <strong>{{ number_format($courseStatus['total']) }}</strong>
                            <span>Total Students</span>
                        </div>
                    </div>

                    <div class="im-course-legend">
                        <div>
                            <span class="im-dot im-dot--green"></span>
                            <span>Completed</span>
                            <strong>{{ $courseStatus['completed'] }}</strong>
                        </div>

                        <div>
                            <span class="im-dot im-dot--blue"></span>
                            <span>In Progress</span>
                            <strong>{{ $courseStatus['inProgress'] }}</strong>
                        </div>

                        <div>
                            <span class="im-dot im-dot--orange"></span>
                            <span>Not Started</span>
                            <strong>{{ $courseStatus['notStarted'] }}</strong>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="im-dashboard-panel im-recent-panel">
            <div class="im-panel-heading">
                <div>
                    <h2>Recent Activity</h2>
                    <p>Latest events across labs, users, and system</p>
                </div>
            </div>

            <div class="im-recent-table-wrap">
                <table class="im-recent-table">
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
                            <tr>
                                <td>{{ $activity->created_at?->diffForHumans() ?? '—' }}</td>
                                <td>{{ $activity->action instanceof \App\Enums\ActivityAction ? $activity->action->label() : str($activity->action)->replace('_', ' ')->title() }}</td>
                                <td>{{ $activity->description ?: 'Activity recorded' }}</td>
                                <td>{{ $activity->actor?->name ?? 'System' }}</td>
                                <td>
                                    <span class="im-type-badge">
                                        {{ $activity->resource_type ?: 'System' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="im-empty-state">
                                    No recent activity recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
