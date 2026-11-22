<x-filament-panels::page>
    @php($data = $this->dashboard())
    <div id="ctf-ui-v31" class="space-y-6" style="--ctf-accent:#10b981">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>

                <p class="text-sm text-gray-500 dark:text-gray-400">Verified student progress and course oversight.</p>
            </div>
            <span class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">● Live monitoring · 15s</span>
        </header>
        <div wire:poll.15s="refreshDashboard" class="space-y-4">
            <div class="ctf-v31-kpis grid grid-cols-2 gap-3 xl:grid-cols-4">
                @foreach ([['Current Students',$data['summary']['current_students'],'👥'],['Ready',$data['summary']['ready'],'▶'],['In Progress',$data['summary']['in_progress'],'◷'],['Completed',$data['summary']['completed'],'✓']] as $metric)
                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400"><span class="ctf32-kpi-icon">@switch($metric[0])@case('Current Students')<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>@break @case('Ready')<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>@break @case('In Progress')<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>@break @default <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>@endswitch</span><span class="ctf32-kpi-label">{{ $metric[0] }}</span></div>
                        <div class="mt-2 text-3xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric[1]) }}</div>
                    </article>
                @endforeach
            </div>
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex gap-2">
                        <button type="button" wire:click="switchView('current')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $viewMode === 'current' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">Current Students</button>
                        <button type="button" wire:click="switchView('history')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $viewMode === 'history' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">History · {{ $data['summary']['history'] }}</button>
                    </div>


<div class="flex items-center gap-2">
    <span class="text-xs text-gray-500">
        {{ $data['matched'] }} matching sessions
    </span>
</div>


                </div>
                <div class="grid gap-2 p-4 md:grid-cols-3">
                    <input type="search" wire:model.live.debounce.500ms="search" placeholder="Search student, email, course…" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    <select wire:model.live="statusFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All statuses</option>
                        @foreach (['CREATING','PROVISIONING','CONFIGURING','READY','RUNNING','STOPPED','RESETTING','COMPLETED','FAILED','DELETING','DELETED'] as $status)
                            <option value="{{ $status }}">{{ ucwords(strtolower($status)) }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="courseFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All courses</option>
                        @foreach ($data['courses'] as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr>
                            <th class="px-4 py-3">Student</th><th class="px-4 py-3">Course</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last update</th><th class="px-4 py-3">Action</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['rows'] as $row)
                            <tr wire:key="ctf-row-{{ $row['id'] }}" class="hover:bg-teal-500/5">
                                <td class="px-4 py-3"><div class="font-semibold">{{ $row['student'] }}</div><div class="text-xs text-gray-500">{{ $row['email'] }}</div></td>
                                <td class="px-4 py-3">{{ $row['course'] }}</td>

<td class="px-4 py-3">
    <div class="ctf34-progress">
        <div class="ctf34-progress-head">
            <span class="ctf34-progress-count">
                {{ $row['done'] }} / {{ $row['total'] }} objectives
            </span>
            <span class="ctf34-progress-percent">
                {{ $row['percent'] }}%
            </span>
        </div>

        <div
            class="ctf34-progress-track"
            role="progressbar"
            aria-label="Challenge progress for {{ $row['student'] }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $row['percent'] }}"
        >
            <div
                class="ctf34-progress-fill"
                style="--ctf-progress: {{ $row['percent'] }}%;"
            >
                @if ($row['percent'] > 0)
                    <span class="ctf34-progress-shine"></span>
                @endif
            </div>
        </div>
    </div>
</td>

                                <td class="px-4 py-3"><span class="rounded-md bg-teal-500/10 px-2 py-1 text-xs font-semibold text-teal-600 dark:text-teal-400">{{ $row['display_status'] }}</span></td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $row['updated'] }}</td>
                                <td class="px-4 py-3"><div class="flex flex-wrap items-center gap-2"><button type="button" wire:click="inspect({{ $row['id'] }})" class="rounded-lg bg-teal-500/10 px-3 py-2 text-xs font-semibold text-teal-600 dark:text-teal-400"><span class="ctf32-inline-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.1 12a10.9 10.9 0 0 1 19.8 0 10.9 10.9 0 0 1-19.8 0"/><circle cx="12" cy="12" r="3"/></svg></span>Inspect</button>

@if (
    $viewMode === 'history' &&
    in_array($row['status'], ['DELETED', 'FAILED'], true)
)
    <button
        type="button"
        wire:click="deleteHistorySession({{ $row['id'] }})"
        wire:confirm="PERMANENT DELETE: Delete this session and its objective progress? This cannot be undone."
        wire:loading.attr="disabled"
        wire:target="deleteHistorySession"
        class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/30"
        title="Delete permanently"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M3 6h18"/>
            <path d="M8 6V4h8v2"/>
            <path d="M19 6l-1 14H6L5 6"/>
            <path d="M10 11v5M14 11v5"/>
        </svg>
        Delete
    </button>
@endif

</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-sm text-gray-500">No student sessions match these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-3 text-xs dark:border-gray-700">
    <span class="text-gray-500">
        Page {{ $data['currentPage'] }} of {{ $data['pageCount'] }}
        · {{ $data['matched'] }} sessions
    </span>
    <div class="flex items-center gap-2">
        <button type="button"
            wire:click="previousHistoryPage"
            @disabled($data['currentPage'] <= 1)
            class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-40 dark:border-gray-700">
            Previous
        </button>
        <button type="button"
            wire:click="nextHistoryPage"
            @disabled($data['currentPage'] >= $data['pageCount'])
            class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-40 dark:border-gray-700">
            Next
        </button>
    </div>
</div>

            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-bold"><span class="ctf32-heading-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>Course Management</h3><span class="text-xs text-gray-500">{{ $data['summary']['courses'] }} courses</span></div>
                @foreach ($data['courses'] as $course)
                    <div class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700"><div><div class="font-semibold">{{ $course->title }}</div><div class="text-xs text-gray-500">{{ $course->difficulty }} · {{ $course->objectives_count }} objectives</div></div><button wire:click="editCourse({{ $course->id }})" class="rounded-lg bg-teal-500/10 px-3 py-2 text-xs font-semibold text-teal-600">Edit</button></div>
                @endforeach
                @if ($editingCourse !== null)
                    <form wire:submit="saveCourse" class="mt-3 space-y-3 rounded-lg border border-teal-500/30 p-3">
                        <h4 class="font-semibold">Edit Course</h4>
                        <label class="block text-xs">Title<input wire:model="courseTitle" required class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800" /></label>@error('courseTitle')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <label class="block text-xs">Difficulty<input wire:model="courseDifficulty" required class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800" /></label>@error('courseDifficulty')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <label class="block text-xs">Summary<textarea wire:model="courseSummary" rows="3" class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800"></textarea></label>@error('courseSummary')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <div class="flex gap-2"><button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-xs text-white">Save Course</button><button type="button" wire:click="cancelEdit" class="rounded-lg border px-4 py-2 text-xs">Cancel</button></div>
                    </form>
                @endif
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="font-bold"><span class="ctf32-heading-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></span>Challenge Management</h3><p class="mb-3 text-xs text-gray-500">Objective editing remains locked for courses with session history.</p>
                @foreach ($data['courses'] as $course)
                    <div class="mb-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700"><div class="mb-2 text-sm font-semibold">{{ $course->title }}</div>
                        @php($locked = \App\Models\LabSession::query()->where('course_id', $course->id)->exists())
                        @foreach ($course->objectives as $objective)
                            <div class="flex items-center justify-between gap-2 py-1 text-xs"><span>{{ $objective->sequence }}. {{ $objective->title }}</span>
                            @if (!$locked)<button wire:click="editObjective({{ $objective->id }})" class="font-semibold text-teal-600">Edit</button>@else<span class="text-gray-400">Locked</span>@endif</div>
                        @endforeach
                    </div>
                @endforeach
                @if ($editingObjective !== null)
                    <form wire:submit="saveObjective" class="space-y-3 rounded-lg border border-teal-500/30 p-3"><h4 class="font-semibold">Edit Challenge</h4>
                        <label class="block text-xs">Title<input wire:model="objectiveTitle" required class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800" /></label>@error('objectiveTitle')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <label class="block text-xs">Description<textarea wire:model="objectiveDescription" required rows="4" class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800"></textarea></label>@error('objectiveDescription')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <label class="block text-xs">Hint<textarea wire:model="objectiveHint" rows="2" class="mt-1 w-full rounded-lg border p-2 dark:bg-gray-800"></textarea></label>@error('objectiveHint')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        <div class="flex gap-2"><button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-xs text-white">Save Challenge</button><button type="button" wire:click="cancelEdit" class="rounded-lg border px-4 py-2 text-xs">Cancel</button></div>
                    </form>
                @endif
            </section>
        </div>

        {{-- IMNEHNET_INSPECTOR_V38 --}}
        @include('filament.pages.partials.ctf-inspector-v38')

    </div>







<style>
/* IMNEHNET_CTF_V33_FULL_THEME */

/*
 * Neutral design system
 * Light: white / slate
 * Dark: navy / slate
 * Accent: restrained teal
 * Modal: large, sharp backdrop, no blur
 */

#ctf-ui-v31 {
    --ctf-radius: 14px;
    --ctf-accent: #0d9488;
}

/* ======================================
   LIGHT MODE TOKENS
====================================== */

html:not(.dark) #ctf-ui-v31 {
    --ctf-panel: #ffffff;
    --ctf-panel-soft: #f8fafc;
    --ctf-border: #dce4ee;
    --ctf-text: #0f172a;
    --ctf-muted: #64748b;
    --ctf-icon-bg: #edf2f8;
    --ctf-icon-color: #475569;
    --ctf-hover: #f1f5f9;
    --ctf-accent-bg: #ecfdf5;
}

/* ======================================
   DARK MODE TOKENS
====================================== */

html.dark #ctf-ui-v31 {
    --ctf-panel: #101827;
    --ctf-panel-soft: #172235;
    --ctf-border: #2c3b50;
    --ctf-text: #f1f5f9;
    --ctf-muted: #94a3b8;
    --ctf-icon-bg: #24364e;
    --ctf-icon-color: #cbd5e1;
    --ctf-hover: #1b2a40;
    --ctf-accent-bg: #10352f;
}

/* ======================================
   PAGE HEADER
====================================== */

#ctf-ui-v31 > header {
    margin-bottom: 18px;
    padding: 0;
    align-items: center;
}

#ctf-ui-v31 > header > div:empty {
    display: none;
}

#ctf-ui-v31 > header p {
    font-size: 13px;
    line-height: 1.6;
    color: var(--ctf-muted);
}

#ctf-ui-v31 > header > span {
    display: inline-flex;
    align-items: center;
    border-radius: 999px !important;
    padding: 8px 13px !important;
    font-size: 12px !important;
    border: 1px solid rgba(13,148,136,.35) !important;
    box-shadow: none !important;
}

html:not(.dark) #ctf-ui-v31 > header > span {
    background: #ecfdf5 !important;
    color: #047857 !important;
}

html.dark #ctf-ui-v31 > header > span {
    background: #10352f !important;
    color: #5eead4 !important;
}

/* ======================================
   SHARED CARDS / PANELS
====================================== */

#ctf-ui-v31 section {
    border-radius: var(--ctf-radius) !important;
    border: 1px solid var(--ctf-border) !important;
    background: var(--ctf-panel) !important;
    color: var(--ctf-text);
    box-shadow: none !important;
}

#ctf-ui-v31 section h3 {
    color: var(--ctf-text);
    letter-spacing: -.015em;
}

#ctf-ui-v31 section p,
#ctf-ui-v31 section .text-gray-500 {
    color: var(--ctf-muted);
}

#ctf-ui-v31 section > div {
    border-color: var(--ctf-border);
}

/* ======================================
   FOUR STATISTIC CARDS
====================================== */

#ctf-ui-v31 .ctf-v31-kpis {
    gap: 12px;
}

#ctf-ui-v31 .ctf-v31-kpis article,
html.dark #ctf-ui-v31 .ctf-v31-kpis article,
html:not(.dark) #ctf-ui-v31 .ctf-v31-kpis article {
    position: relative;
    min-height: 110px;
    padding: 18px !important;
    overflow: hidden;
    border-radius: var(--ctf-radius) !important;
    border: 1px solid var(--ctf-border) !important;
    background: var(--ctf-panel) !important;
    color: var(--ctf-text) !important;
    box-shadow: none !important;
    transition: border-color .18s ease, background .18s ease;
}

#ctf-ui-v31 .ctf-v31-kpis article:hover {
    border-color: #94a3b8 !important;
}

#ctf-ui-v31 .ctf-v31-kpis article > div:first-child {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--ctf-muted) !important;
    font-size: 12px !important;
    font-weight: 600;
}

#ctf-ui-v31 .ctf32-kpi-icon,
#ctf-ui-v31 .ctf-v31-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 43px;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: var(--ctf-icon-bg) !important;
    color: var(--ctf-icon-color) !important;
}

#ctf-ui-v31 .ctf32-kpi-icon svg,
#ctf-ui-v31 .ctf-v31-stat-icon svg {
    width: 23px;
    height: 23px;
    stroke-width: 1.8;
}

#ctf-ui-v31 .ctf32-kpi-label,
#ctf-ui-v31 .ctf-v31-stat-label {
    color: var(--ctf-muted) !important;
    font-weight: 600;
}

#ctf-ui-v31 .ctf-v31-kpis article > div:last-child {
    margin: -14px 0 0 55px !important;
    font-size: 30px !important;
    font-weight: 750 !important;
    line-height: 1.2;
    color: var(--ctf-text) !important;
}

/* ======================================
   CURRENT / HISTORY TABS
====================================== */

#ctf-ui-v31 button[wire\\:click*="switchView"] {
    border-radius: 9px !important;
    padding: 10px 16px !important;
    font-size: 12px !important;
    font-weight: 650 !important;
    box-shadow: none !important;
}

html:not(.dark) #ctf-ui-v31 button[wire\\:click*="switchView"].bg-teal-600 {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

html.dark #ctf-ui-v31 button[wire\\:click*="switchView"].bg-teal-600 {
    background: #33465f !important;
    color: #ffffff !important;
}

#ctf-ui-v31 button[wire\\:click*="switchView"]:hover {
    filter: brightness(.97);
}

/* ======================================
   SEARCH / SELECT / EDIT FORMS
====================================== */

#ctf-ui-v31 input,
#ctf-ui-v31 select,
#ctf-ui-v31 textarea {
    width: 100%;
    min-height: 40px;
    border-radius: 9px !important;
    border: 1px solid var(--ctf-border) !important;
    background: var(--ctf-panel) !important;
    color: var(--ctf-text) !important;
    font-size: 13px;
    box-shadow: none !important;
}

#ctf-ui-v31 textarea {
    min-height: 90px;
}

#ctf-ui-v31 input::placeholder,
#ctf-ui-v31 textarea::placeholder {
    color: var(--ctf-muted);
}

#ctf-ui-v31 input:focus,
#ctf-ui-v31 select:focus,
#ctf-ui-v31 textarea:focus {
    outline: 2px solid rgba(13,148,136,.2) !important;
    border-color: #0d9488 !important;
    --tw-ring-color: transparent !important;
}

#ctf-ui-v31 label {
    color: var(--ctf-text);
}

/* ======================================
   STUDENT TABLE
====================================== */

#ctf-ui-v31 table {
    border-collapse: separate;
    border-spacing: 0;
    color: var(--ctf-text);
}

#ctf-ui-v31 thead {
    background: var(--ctf-panel-soft) !important;
    color: var(--ctf-muted) !important;
}

#ctf-ui-v31 th {
    padding-top: 13px;
    padding-bottom: 13px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .045em;
}

#ctf-ui-v31 tbody tr {
    transition: background .15s ease;
}

#ctf-ui-v31 tbody tr:hover {
    background: var(--ctf-hover) !important;
}

#ctf-ui-v31 tbody td {
    padding-top: 15px;
    padding-bottom: 15px;
    border-color: var(--ctf-border);
}

#ctf-ui-v31 tbody .font-semibold {
    color: var(--ctf-text);
}

/* Status badges */
#ctf-ui-v31 tbody span.bg-teal-500\\/10 {
    border-radius: 7px !important;
    background: var(--ctf-accent-bg) !important;
    color: #0d9488 !important;
    font-weight: 700;
}

html.dark #ctf-ui-v31 tbody span.bg-teal-500\\/10 {
    color: #5eead4 !important;
}

/* Progress bar */
#ctf-ui-v31 tbody .bg-teal-500 {
    background: #0d9488 !important;
}

/* ======================================
   BUTTONS / LUCIDE ICONS
====================================== */

#ctf-ui-v31 button {
    transition: background .18s, border-color .18s;
}

#ctf-ui-v31 button.bg-teal-500\\/10 {
    background: var(--ctf-accent-bg) !important;
    color: #0f766e !important;
    border-radius: 9px !important;
}

html.dark #ctf-ui-v31 button.bg-teal-500\\/10 {
    color: #5eead4 !important;
}

#ctf-ui-v31 button.bg-teal-600 {
    background: #0f766e !important;
    color: #fff !important;
}

#ctf-ui-v31 .ctf32-heading-icon,
#ctf-ui-v31 .ctf-v31-section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    color: var(--ctf-icon-color) !important;
    margin-right: 8px;
}

#ctf-ui-v31 .ctf32-heading-icon svg,
#ctf-ui-v31 .ctf-v31-section-icon svg {
    width: 20px;
    height: 20px;
}

#ctf-ui-v31 .ctf32-inline-icon {
    display: inline-flex;
    align-items: center;
}

#ctf-ui-v31 button:has(.ctf32-inline-icon),
#ctf-ui-v31 .ctf-v31-inspect {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

#ctf-ui-v31 button svg {
    flex-shrink: 0;
}

/* ======================================
   COURSE / CHALLENGE MANAGEMENT
====================================== */

#ctf-ui-v31 .ctf-v31-management {
    gap: 16px;
}

#ctf-ui-v31 .ctf-v31-management > section {
    padding: 19px !important;
}

#ctf-ui-v31 .ctf-v31-management h3 {
    font-size: 15px;
    font-weight: 700;
}

#ctf-ui-v31 .ctf-v31-management section > div.rounded-lg {
    background: var(--ctf-panel) !important;
    border: 1px solid var(--ctf-border) !important;
    border-radius: 10px !important;
}

#ctf-ui-v31 .ctf-v31-management section > div.rounded-lg:hover {
    border-color: #94a3b8 !important;
}

#ctf-ui-v31 .ctf-v31-management form {
    border-radius: 11px !important;
    border: 1px solid var(--ctf-border) !important;
    background: var(--ctf-panel-soft);
    padding: 15px !important;
}

#ctf-ui-v31 .ctf-v31-management form button {
    border-radius: 8px !important;
}

#ctf-ui-v31 .ctf-v31-management .text-gray-400 {
    color: var(--ctf-muted) !important;
}

/* ======================================
   STUDENT INSPECTOR MODAL
   Teleported to body: scoped separately
====================================== */

.ctf-v31-backdrop {
    position: fixed !important;
    inset: 0 !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px !important;
    background: rgba(2, 8, 20, .55) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.ctf-v31-dialog {
    width: min(1050px, calc(100vw - 40px)) !important;
    max-width: 1050px !important;
    max-height: 88vh !important;
    overflow-y: auto !important;
    padding: 28px !important;
    margin: auto !important;
    border-radius: 18px !important;
    box-shadow: 0 25px 70px rgba(0,0,0,.24) !important;
}

/* Modal Light */
html:not(.dark) .ctf-v31-dialog {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}

/* Modal Dark */
html.dark .ctf-v31-dialog {
    background: #101827 !important;
    border: 1px solid #35445a !important;
    color: #f1f5f9 !important;
    box-shadow: 0 25px 70px rgba(0,0,0,.5) !important;
}

.ctf-v31-dialog h3 {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 24px !important;
    font-weight: 750;
    letter-spacing: -.03em;
}

.ctf-v31-dialog h4 {
    font-weight: 700;
}

html:not(.dark) .ctf-v31-dialog h3,
html:not(.dark) .ctf-v31-dialog h4,
html:not(.dark) .ctf-v31-dialog .font-semibold {
    color: #0f172a !important;
}

html.dark .ctf-v31-dialog h3,
html.dark .ctf-v31-dialog h4,
html.dark .ctf-v31-dialog .font-semibold {
    color: #f1f5f9 !important;
}

.ctf-v31-dialog .ctf32-heading-icon,
.ctf-v31-dialog .ctf-v31-avatar {
    display: inline-flex;
    align-items: center;
    color: inherit;
}

.ctf-v31-dialog button {
    border-radius: 9px !important;
    font-size: 13px;
}

.ctf-v31-dialog button svg {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
}

.ctf-v31-dialog button:has(svg) {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

html:not(.dark) .ctf-v31-dialog button[class*="bg-teal-600"] {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

html.dark .ctf-v31-dialog button[class*="bg-teal-600"] {
    background: #33465f !important;
    color: #ffffff !important;
}

html:not(.dark) .ctf-v31-dialog button[class*="bg-gray-100"] {
    background: #f1f5f9 !important;
    color: #475569 !important;
}

html.dark .ctf-v31-dialog button[class*="bg-gray-100"],
html.dark .ctf-v31-dialog button[class*="dark:bg-gray-800"] {
    background: #1e293b !important;
    color: #cbd5e1 !important;
}

html:not(.dark) .ctf-v31-dialog .bg-gray-100 {
    background: #f1f5f9 !important;
}

html.dark .ctf-v31-dialog .bg-gray-100 {
    background: #1e293b !important;
}

/* Modal detail cards */
.ctf-v31-dialog .grid > div.rounded-lg {
    border-radius: 12px !important;
    padding: 18px !important;
}

.ctf-v31-dialog .border-gray-200,
.ctf-v31-dialog .dark\\:border-gray-700 {
    border-color: #cbd5e1;
}

html.dark .ctf-v31-dialog .border-gray-200,
html.dark .ctf-v31-dialog .dark\\:border-gray-700 {
    border-color: #35445a;
}

.ctf-v31-dialog p {
    line-height: 1.6;
}

/* ======================================
   RESPONSIVE
====================================== */

@media (max-width: 767px) {
    #ctf-ui-v31 .ctf-v31-kpis article {
        min-height: 100px;
        padding: 13px !important;
    }

    #ctf-ui-v31 .ctf32-kpi-icon,
    #ctf-ui-v31 .ctf-v31-stat-icon {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
    }

    #ctf-ui-v31 .ctf-v31-kpis article > div:last-child {
        margin: -8px 0 0 48px !important;
        font-size: 25px !important;
    }

    .ctf-v31-backdrop {
        padding: 10px !important;
    }

    .ctf-v31-dialog {
        width: calc(100vw - 20px) !important;
        max-height: 92vh !important;
        padding: 18px !important;
        border-radius: 14px !important;
    }

    .ctf-v31-dialog h3 {
        font-size: 20px !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    #ctf-ui-v31 *,
    .ctf-v31-dialog * {
        transition: none !important;
    }
}
</style>


<style>
/* IMNEHNET_CTF_PROGRESS_V34 */

#ctf-ui-v31 .ctf34-progress {
    width: 100%;
    min-width: 180px;
    max-width: 360px;
}

#ctf-ui-v31 .ctf34-progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 9px;
}

#ctf-ui-v31 .ctf34-progress-count {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

#ctf-ui-v31 .ctf34-progress-percent {
    font-size: 12px;
    font-weight: 750;
    color: #0f766e;
    font-variant-numeric: tabular-nums;
}

#ctf-ui-v31 .ctf34-progress-track {
    position: relative;
    width: 100%;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

#ctf-ui-v31 .ctf34-progress-fill {
    position: relative;
    height: 100%;
    width: var(--ctf-progress);
    max-width: 100%;
    overflow: hidden;
    border-radius: inherit;
    background: linear-gradient(
        90deg,
        #0f766e 0%,
        #0d9488 60%,
        #14b8a6 100%
    );
    transition: width 1.5s cubic-bezier(.45,0,.20,1);
}

#ctf-ui-v31 .ctf34-progress-shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        100deg,
        transparent 0%,
        rgba(255,255,255,.05) 35%,
        rgba(255,255,255,.32) 50%,
        rgba(255,255,255,.05) 65%,
        transparent 100%
    );
    transform: translateX(-100%);
    animation: ctf34-shine 2.8s ease-in-out infinite;
}

html.dark #ctf-ui-v31 .ctf34-progress-track {
    background: #304158;
}

html.dark #ctf-ui-v31 .ctf34-progress-count {
    color: #9fb2cb;
}

html.dark #ctf-ui-v31 .ctf34-progress-percent {
    color: #5eead4;
}

@keyframes ctf34-grow {
    from { width: 0; }
    to { width: var(--ctf-progress); }
}

@keyframes ctf34-shine {
    0%, 35% { transform: translateX(-100%); }
    75%, 100% { transform: translateX(100%); }
}

@media (prefers-reduced-motion: reduce) {
    #ctf-ui-v31 .ctf34-progress-fill,
    #ctf-ui-v31 .ctf34-progress-shine {
        animation: none !important;
    }
}

@media (max-width: 767px) {
    #ctf-ui-v31 .ctf34-progress {
        min-width: 150px;
    }
}
</style>


{{-- IMNEHNET_CTF_SMOOTH_V37 --}}
<style>
    #ctf-ui-v31 .ctf34-progress-fill {
        transition:
            width 1.5s cubic-bezier(.45, 0, .20, 1);
    }

    @media (prefers-reduced-motion: reduce) {
        #ctf-ui-v31 .ctf34-progress-fill {
            transition: none !important;
            animation: none !important;
        }
    }
</style>

<script>
(() => {
    if (window.__imnehnetCtfSmoothV37) return;
    window.__imnehnetCtfSmoothV37 = true;

    const initialize = () => {
        const root = document.getElementById('ctf-ui-v31');
        if (!root) return;

        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        root.querySelectorAll('.ctf34-progress-fill')
            .forEach((bar) => {
                if (bar.dataset.ctfSmoothInitialized === '1') {
                    return;
                }

                bar.dataset.ctfSmoothInitialized = '1';

                if (reduceMotion) return;

                // Start at zero only on first initialization.
                // The target stays in --ctf-progress.
                bar.style.width = '0%';

                // Commit the initial layout before transitioning.
                void bar.offsetWidth;

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        if (bar.isConnected) {
                            bar.style.removeProperty('width');
                        }
                    });
                });
            });
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            { once: true }
        );
    } else {
        initialize();
    }

    document.addEventListener(
        'livewire:navigated',
        initialize
    );
})();
</script>

</x-filament-panels::page>
