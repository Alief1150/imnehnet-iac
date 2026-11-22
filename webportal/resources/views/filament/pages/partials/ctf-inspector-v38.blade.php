{{-- IMNEHNET_INSPECTOR_V38 --}}
@if ($data['selected'])
    @php
        $session = $data['selected'];
        $steps = collect($data['steps'] ?? []);
        $events = collect($data['events'] ?? []);

        $status = $session->status instanceof \BackedEnum
            ? $session->status->value
            : (string) $session->status;

        $done = $steps->where('status', 'COMPLETED')->count();
        $total = $steps->count();
        $percent = $total > 0 ? (int) round($done / $total * 100) : 0;

        $started = $session->started_at;
        $completed = $session->completed_at;

        $duration = $started
            ? (int) round(abs($started->diffInSeconds($completed ?? now())))
            : null;

        $durationLabel = $duration === null
            ? 'Not available'
            : sprintf(
                '%dh %02dm %02ds',
                intdiv($duration, 3600),
                intdiv($duration % 3600, 60),
                $duration % 60
            );

        $intervals = [];
        $previous = $started;

        foreach ($events as $event) {
            try {
                $time = \Carbon\Carbon::parse($event['completed_at']);

                if ($previous) {
                    $intervals[] = [
                        'name' => 'Objective '.($event['sequence'] ?? '?'),
                        'title' => $event['title'] ?? 'Objective',
                        'seconds' => max(
                            0,
                            (int) round(abs($previous->diffInSeconds($time)))
                        ),
                    ];
                }

                $previous = $time;
            } catch (\Throwable $ignored) {
                // Do not invent missing timestamps.
            }
        }

        $maxInterval = max(1, ...array_column($intervals, 'seconds'));

        $averageInterval = count($intervals)
            ? (int) round(
                array_sum(array_column($intervals, 'seconds'))
                / count($intervals)
            )
            : null;

        $attacker = $session->attacker_environment_id
            ? \App\Models\LabEnvironment::query()
                ->find($session->attacker_environment_id)
            : null;

        $victim = $session->victim_environment_id
            ? \App\Models\LabEnvironment::query()
                ->find($session->victim_environment_id)
            : null;
    @endphp

    @teleport('body')
        <div
            class="ctf38-overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Student Inspector"
        >
            <div class="ctf38-modal">

                <header class="ctf38-header">
                    <div class="ctf38-title-wrap">
                        <div class="ctf38-avatar">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round">
                                <circle cx="9" cy="8" r="3"/>
                                <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
                                <path d="M17 5a3 3 0 0 1 0 6"/>
                                <path d="M18 14a5 5 0 0 1 3 5"/>
                            </svg>
                        </div>

                        <div>
                            <h2>Student Inspector</h2>
                            <p>
                                {{ $data['selectedUser']?->name ?? 'Student unavailable' }}
                                · {{ $data['selectedUser']?->email ?? '—' }}
                                · Session #{{ $session->id }}
                            </p>
                        </div>
                    </div>

                    <div class="ctf38-header-actions">
                        <span class="ctf38-status">{{ $status }}</span>

                        <button
                            type="button"
                            wire:click="closeInspector"
                            class="ctf38-close"
                        >
                            × Close
                        </button>
                    </div>
                </header>

                <nav class="ctf38-tabs" aria-label="Inspector tabs">
                    @foreach ([
                        'overview' => 'Overview',
                        'assessment' => 'Assessment',
                        'diagnostics' => 'Diagnostics'
                    ] as $key => $label)
                        <button
                            type="button"
                            wire:click="selectInspectorTab('{{ $key }}')"
                            class="ctf38-tab {{ $inspectorTab === $key ? 'active' : '' }}"
                            @if ($inspectorTab === $key)
                                aria-current="page"
                            @endif
                        >
                            @switch($key)
                                @case('overview')
                                    <span>◫</span>
                                    @break
                                @case('assessment')
                                    <span>▥</span>
                                    @break
                                @default
                                    <span>◇</span>
                            @endswitch

                            {{ $label }}
                        </button>
                    @endforeach
                </nav>

                @if ($inspectorTab === 'overview')

                    <div class="ctf38-metrics">
                        <article class="ctf38-card">
                            <small>Started At</small>
                            <strong>
                                {{ $started?->format('d M Y · H:i:s') ?? 'Not recorded' }}
                            </strong>
                        </article>

                        <article class="ctf38-card">
                            <small>Completed At</small>
                            <strong>
                                {{ $completed?->format('d M Y · H:i:s') ?? 'Not completed' }}
                            </strong>
                        </article>

                        <article class="ctf38-card">
                            <small>
                                {{ $completed ? 'Total Duration' : 'Elapsed Since Start' }}
                            </small>
                            <strong>{{ $durationLabel }}</strong>
                        </article>

                        <article class="ctf38-card">
                            <small>Result</small>
                            <strong class="ctf38-accent">
                                {{ $done }} / {{ $total }} Objectives
                            </strong>
                        </article>
                    </div>

                    <section class="ctf38-summary">
                        <small>SESSION SUMMARY</small>

                        <h3>
                            {{ $status === 'COMPLETED'
                                ? 'CTF Completed Successfully'
                                : 'Session Snapshot' }}
                        </h3>

                        <p>
                            {{ $data['selectedCourse']?->title ?? 'Course unavailable' }}
                        </p>

                        <div class="ctf38-summary-stats">
                            <div>
                                <strong>{{ $done }}</strong>
                                <small>Objectives Completed</small>
                            </div>

                            <div>
                                <strong>{{ $percent }}%</strong>
                                <small>Completion Rate</small>
                            </div>

                            <div>
                                <strong>{{ $status }}</strong>
                                <small>Session Status</small>
                            </div>
                        </div>

                        <p class="ctf38-note">
                            Duration is calculated from the recorded session start and completion timestamps.
                        </p>
                    </section>

                @elseif ($inspectorTab === 'assessment')

                    <section class="ctf38-feature">
                        <div class="ctf38-feature-icon">◷</div>
                        <div>
                            <h3>Objective Bottleneck Analysis</h3>
                            <p>
                                Analyze intervals between objectives using verified completion timestamps.
                            </p>
                        </div>
                    </section>

                    <div class="ctf38-metrics ctf38-three">
                        <article class="ctf38-card">
                            <small>Recorded Milestones</small>
                            <strong>{{ count($intervals) }}</strong>
                        </article>

                        <article class="ctf38-card">
                            <small>Average Interval</small>
                            <strong>
                                {{ $averageInterval === null
                                    ? 'N/A'
                                    : gmdate('i:s', $averageInterval) }}
                            </strong>
                        </article>

                        <article class="ctf38-card">
                            <small>Longest Interval</small>
                            <strong>
                                {{ count($intervals)
                                    ? gmdate(
                                        'i:s',
                                        max(array_column($intervals, 'seconds'))
                                    )
                                    : 'N/A' }}
                            </strong>
                        </article>
                    </div>

                    <section class="ctf38-chart">
                        <h3>Time to Each Milestone</h3>

                        @forelse ($intervals as $point)
                            <div class="ctf38-chart-row">
                                <span title="{{ $point['title'] }}">
                                    {{ $point['name'] }}
                                </span>

                                <div class="ctf38-chart-track">
                                    <div
                                        class="ctf38-chart-fill"
                                        style="width: {{ round(
                                            $point['seconds'] * 100 / $maxInterval,
                                            2
                                        ) }}%"
                                    ></div>
                                </div>

                                <strong>
                                    {{ gmdate('i:s', $point['seconds']) }}
                                </strong>
                            </div>
                        @empty
                            <p class="ctf38-note">
                                Insufficient timestamp data for analysis.
                            </p>
                        @endforelse

                        <p class="ctf38-note">
                            The first interval begins at session start. Subsequent intervals use objective completion timestamps. These values do not represent active terminal usage or flag submission attempts.
                        </p>
                    </section>

                @else

                    <section class="ctf38-feature">
                        <div class="ctf38-feature-icon">◇</div>
                        <div>
                            <h3>Session & Infrastructure Diagnostics</h3>
                            <p>
                                Displays recorded database metadata and statuses. This panel does not perform live Incus runtime or OpenTofu state verification.
                            </p>
                        </div>
                    </section>

                    <div class="ctf38-diag-list">
                        <article class="ctf38-diag">
                            <div>
                                <strong>LabSession Integrity</strong>
                                <small>Session ID and lifecycle status</small>
                            </div>

                            <span>#{{ $session->id }} · {{ $status }}</span>
                        </article>

                        @foreach ([
                            ['Attacker Environment', $attacker],
                            ['Victim Environment', $victim]
                        ] as [$name, $environment])
                            <article class="ctf38-diag">
                                <div>
                                    <strong>{{ $name }}</strong>
                                    <small>
                                        {{ $environment
                                            ? $environment->incus_remote.':'.$environment->container_name
                                            : 'Environment record not available' }}
                                    </small>
                                </div>

                                <span>
                                    @if ($environment)
                                        {{ $environment->status instanceof \BackedEnum
                                            ? $environment->status->value
                                            : $environment->status }}
                                        /
                                        {{ $environment->runtime_status instanceof \BackedEnum
                                            ? $environment->runtime_status->value
                                            : $environment->runtime_status }}
                                    @else
                                        Unavailable
                                    @endif
                                </span>
                            </article>
                        @endforeach

                        <article class="ctf38-diag">
                            <div>
                                <strong>OpenTofu Workspace Metadata</strong>
                                <small>Recorded workspace names</small>
                            </div>

                            <span>
                                {{ $attacker?->tofu_workspace ?? '—' }}
                                /
                                {{ $victim?->tofu_workspace ?? '—' }}
                            </span>
                        </article>

                        <article class="ctf38-diag">
                            <div>
                                <strong>Last Lifecycle Error</strong>
                                <small class="ctf38-error">
                                    {{ $session->last_error
                                        ?: 'No error recorded on this session.' }}
                                </small>
                            </div>

                            <span>
                                {{ filled($session->last_error)
                                    ? 'Recorded'
                                    : 'None recorded' }}
                            </span>
                        </article>
                    </div>

                @endif
            </div>
        </div>
    @endteleport
@endif

<style>
/* IMNEHNET_INSPECTOR_V38_THEME */
/* IMNEHNET_INSPECTOR_V382 */
/* IMNEHNET_BACKDROP_V383 */
/* IMNEHNET_BACKDROP_V384 */
.ctf38-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(2, 6, 23, .68) !important;
}

.ctf38-modal {
    --i-bg: #fff;
    --i-panel: #f8fafc;
    --i-border: #dce4ee;
    --i-text: #0f172a;
    --i-muted: #64748b;

    width: 100%;
    max-width: 1150px;
    max-height: 92vh;
    overflow-y: auto;
    padding: 28px;
    border: 1px solid var(--i-border);
    border-radius: 18px;
    background: var(--i-bg);
    color: var(--i-text);
    box-shadow: 0 24px 80px rgba(0, 0, 0, .35);
}

html.dark .ctf38-modal {
    --i-bg: #101827;
    --i-panel: #172235;
    --i-border: #2c3b50;
    --i-text: #f1f5f9;
    --i-muted: #94a3b8;
}

.ctf38-modal * {
    box-sizing: border-box;
}

.ctf38-header,
.ctf38-title-wrap,
.ctf38-header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.ctf38-header {
    justify-content: space-between;
    align-items: flex-start;
}

.ctf38-title-wrap {
    min-width: 0;
}

.ctf38-avatar,
.ctf38-feature-icon {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(13, 148, 136, .14);
    color: #14b8a6;
}

.ctf38-avatar svg {
    width: 30px;
    height: 30px;
}

.ctf38-header h2 {
    font-size: 25px;
    font-weight: 800;
    line-height: 1.25;
    color: var(--i-text);
}

.ctf38-header p {
    margin-top: 5px;
    color: var(--i-muted);
    font-size: 13px;
    overflow-wrap: anywhere;
}

.ctf38-status {
    padding: 8px 13px;
    border-radius: 9px;
    border: 1px solid rgba(13, 148, 136, .3);
    background: rgba(13, 148, 136, .13);
    color: #0d9488;
    font-size: 12px;
    font-weight: 800;
}

.ctf38-close {
    padding: 9px 13px;
    border: 1px solid var(--i-border);
    border-radius: 9px;
    color: var(--i-text);
    font-size: 13px;
}

.ctf38-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 24px 0 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--i-border);
}

.ctf38-tab {
    padding: 12px 20px;
    border: 1px solid var(--i-border);
    border-radius: 10px;
    background: var(--i-panel);
    color: var(--i-muted);
    font-size: 13px;
    font-weight: 700;
}

.ctf38-tab.active {
    border-color: #0d9488;
    background: #0d9488;
    color: #fff;
}

.ctf38-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.ctf38-metrics.ctf38-three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.ctf38-card,
.ctf38-summary,
.ctf38-feature,
.ctf38-chart,
.ctf38-diag {
    border: 1px solid var(--i-border);
    border-radius: 13px;
    background: var(--i-panel);
}

.ctf38-card {
    padding: 18px;
    min-width: 0;
}

.ctf38-card small,
.ctf38-summary small {
    display: block;
    margin-bottom: 9px;
    color: var(--i-muted);
    font-size: 12px;
}

.ctf38-card strong {
    display: block;
    color: var(--i-text);
    font-size: 15px;
    overflow-wrap: anywhere;
}

.ctf38-accent {
    color: #14b8a6 !important;
}

.ctf38-summary,
.ctf38-feature,
.ctf38-chart {
    padding: 23px;
}

.ctf38-summary h3,
.ctf38-feature h3,
.ctf38-chart h3 {
    margin-bottom: 11px;
    color: var(--i-text);
    font-size: 19px;
    font-weight: 800;
}

.ctf38-summary p,
.ctf38-feature p {
    color: var(--i-muted);
    font-size: 13px;
    line-height: 1.6;
}

.ctf38-summary-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 28px;
    margin-top: 25px;
    padding-top: 18px;
    border-top: 1px solid var(--i-border);
}

.ctf38-summary-stats strong {
    display: block;
    font-size: 20px;
    font-weight: 800;
    color: var(--i-text);
}

.ctf38-summary-stats small {
    margin-top: 5px;
}

.ctf38-note {
    margin-top: 15px;
    color: var(--i-muted);
    font-size: 12px;
    line-height: 1.6;
}

.ctf38-feature {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 16px;
}

.ctf38-feature-icon {
    font-size: 30px;
}

.ctf38-chart-row {
    display: flex;
    align-items: center;
    gap: 13px;
    margin: 16px 0;
}

.ctf38-chart-row > span {
    width: 105px;
    flex-shrink: 0;
    font-size: 12px;
}

.ctf38-chart-track {
    height: 14px;
    flex: 1;
    overflow: hidden;
    border-radius: 9px;
    background: rgba(100, 116, 139, .25);
}

.ctf38-chart-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #0f766e, #14b8a6);
}

.ctf38-chart-row > strong {
    width: 48px;
    text-align: right;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}

.ctf38-diag-list {
    display: grid;
    gap: 10px;
}

.ctf38-diag {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 17px 19px;
}

.ctf38-diag strong {
    display: block;
    font-size: 14px;
    color: var(--i-text);
}

.ctf38-diag small {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--i-muted);
    overflow-wrap: anywhere;
}

.ctf38-diag > span {
    max-width: 50%;
    color: #0d9488;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
    overflow-wrap: anywhere;
}

.ctf38-error {
    white-space: pre-wrap;
}

@media (max-width: 850px) {
    .ctf38-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ctf38-header {
        flex-wrap: wrap;
    }
}

@media (max-width: 550px) {
    .ctf38-modal {
        padding: 17px;
    }

    .ctf38-metrics,
    .ctf38-metrics.ctf38-three {
        grid-template-columns: 1fr;
    }

    .ctf38-diag {
        align-items: flex-start;
        flex-direction: column;
    }

    .ctf38-diag > span {
        max-width: 100%;
        text-align: left;
    }
}
</style>
