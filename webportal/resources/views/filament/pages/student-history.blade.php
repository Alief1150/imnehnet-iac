<x-filament-panels::page>
@php
    $d = ['completed' => 0, 'objectives' => 0, 'days' => [], 'activity' => [], 'usage' => []];
    $user = auth()->user();
    $completed = (int) $d['completed'];
    $objectives = (int) $d['objectives'];
    $percent = $objectives > 0 ? min(100, round(100 * $completed / $objectives)) : 0;
    $links = [
        ['Dashboard', url('/student'), 'layout-dashboard'],
        ['My Labs', url('/student/my-labs'), 'server'],
        ['Pentest', url('/student/guided-ctf'), 'terminal'],
        ['History', url('/student/history'), 'history'],
        ['Documentation', url('/student/documentation'), 'book-open'],
    ];
    $icons = [
        'logo' => 'box', 'search' => 'search', 'theme' => 'sun', 'notification' => 'bell',
        'total' => 'server', 'active' => 'activity', 'progress' => 'circle-check', 'time' => 'clock',
    ];
    $svg = function (string $name): string {
        $map = [
            'box' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="m3 9 9 5 9-5M12 14v7"/>',
            'layout-dashboard' => '<rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/>',
            'server' => '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01"/>',
            'terminal' => '<path d="m4 6 6 6-6 6M12 18h8"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
            'book-open' => '<path d="M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3z"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
            'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
            'circle-check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
            'arrow-right' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'x' => '<path d="M5 5l14 14M19 5 5 19"/>',
        ];
        $path = $map[$name] ?? $map['box'];
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    };
    $chartPayload = ['days' => $d['days'] ?? [], 'activity' => $d['activity'] ?? [], 'usage' => $d['usage'] ?? [], 'completed' => $completed, 'objectives' => $objectives];
@endphp
<link rel="stylesheet" href="{{ asset('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-DB3fqm2y.css') }}">
<script type="module" src="{{ asset('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-DgQKp-cH.js') }}" defer></script>
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    <aside class="im-v4-sidebar" aria-label="Student sidebar">
        <a class="im-v4-brand" href="{{ url('/student') }}"><span class="im-v4-logo">{!! $svg('box') !!}</span><span><strong>IMNEHNET</strong><small>Lab-as-a-Service</small></span></a>
        <nav class="im-v4-menu menu" aria-label="Main navigation">
            @foreach($links as $i => $link)
                <a class="im-v4-nav-link {{ $i === 3 ? 'is-active' : '' }}" href="{{ $link[1] }}">{!! $svg($link[2]) !!}<span>{{ $link[0] }}</span></a>
            @endforeach
        </nav>
        <div class="im-v4-sidebar-note card"><span class="im-v4-note-icon">{!! $svg('box') !!}</span><strong>Learn. Explore. Secure.</strong><p>“Above all, don't lie to yourself.”<br>— Fyodor Dostoyevsky</p></div>
    </aside>
    <div class="im-v4-workspace">
        <header class="im-v4-topbar">
            <a class="im-v4-mobile-brand" href="{{ url('/student') }}">{!! $svg('box') !!}<strong>IMNEHNET</strong></a>
            <button class="im-v4-search-btn btn" type="button" data-im-v4-search aria-label="Open search">{!! $svg('search') !!}<span>Search anything in IMNEHNET...</span><kbd>Ctrl K</kbd></button>
            <div class="im-v4-top-actions">
                <button type="button" class="btn btn-square im-v4-icon-btn" data-im-v4-theme aria-label="Toggle light and dark mode">{!! $svg('sun') !!}</button>


<!-- IMNEHNET_STUDENT_HEADER_V6 -->

<div class="im-student-dropdown" data-im-student-notifications>

    <button type="button" class="btn btn-square im-v4-icon-btn im-student-v6-bell"

        aria-label="Notifications" aria-expanded="false" aria-haspopup="true">

        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>

    </button>

    <div class="im-student-menu im-student-notification-menu" hidden>

        <div class="im-student-v6-heading">

            <div><strong>Notifications</strong><small>Stay updated with your labs</small></div>

        </div>

        <div class="im-student-v6-empty">

            <div class="im-student-v6-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 6-7 7-3-3"/><path d="m22 10-7 7-3-3"/><path d="m2 12 4 4 4-4"/></svg></div>

            <strong>All caught up!</strong>

            <p>No new notifications right now.</p>

        </div>

        <div class="im-student-v6-footer">IMNEHNET Student Portal</div>

    </div>

</div>



<div class="im-student-dropdown im-student-profile-dropdown">

    <button type="button" class="im-student-v6-trigger"

        aria-label="Open profile menu" aria-expanded="false" aria-haspopup="true">

        <span class="im-v4-user-avatar">{{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}</span>

        <span class="im-student-v6-identity">

            <strong>{{ $user?->name ?? 'Student' }}</strong>

            <small>Student</small>

        </span>

        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>

    </button>

    <div class="im-student-menu im-student-profile-menu" hidden>

        <div class="im-student-v6-profile-heading">

            <span class="im-student-v6-large-avatar">{{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}</span>

            <div>

                <strong>{{ $user?->name ?? 'Student' }}</strong>

                <small>{{ $user?->email ?? '' }}</small>

            </div>

        </div>

        <div class="im-student-v6-links">

            <a href="{{ url('/student') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg> <span>My Dashboard</span></a>

            <a href="{{ url('/student/my-labs') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/></svg> <span>My Labs</span></a>

        </div>

        <div class="im-student-v6-signout-wrap">

            <form method="POST" action="{{ filament()->getLogoutUrl() }}">

                @csrf

                <button type="submit" class="im-student-v6-signout"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> <span>Sign Out</span></button>

            </form>

        </div>

    </div>

</div>


</div>
</header></header>
        <main class="im-v4-content" id="im-v4-main">
<!-- IMNEHNET_STUDENT_LAYOUT_V4 -->
<div class="im-v4-student-page im-v4-student-enter">
{{-- IMNEHNET_STUDENT_HD_V3 --}}

@php

    $user = auth()->user();

    $filter = request()->query('status', 'all');

    $allowed = ['all','completed','running','stopped','failed'];

    if (!in_array($filter, $allowed, true)) $filter = 'all';



    $rows = collect();

    $error = null;



    try {

        $model = new \App\Models\LabSession;

        $table = $model->getTable();

        $schema = \Illuminate\Support\Facades\Schema::connection(

            $model->getConnectionName()

        );



        $owner = collect(['user_id','owner_id','student_id'])

            ->first(fn($c) => $schema->hasColumn($table, $c));



        if (!$user || !$owner) {

            $error = 'Riwayat belum tersedia atau kepemilikan sesi tidak dapat diverifikasi.';

        } else {

            $query = \App\Models\LabSession::query()

                ->where($owner, $user->getAuthIdentifier());



            if ($schema->hasColumn($table, 'course_id')) {

                $query->with('course');

            }



            $sort = collect(['started_at','created_at','id'])

                ->first(fn($c) => $schema->hasColumn($table, $c));



            if ($sort) $query->orderByDesc($sort);



            $rows = $query->limit(300)->get();

        }

    } catch (\Throwable $e) {

        $error = 'Riwayat belum dapat dimuat. Hubungi pengelola.';

        \Illuminate\Support\Facades\Log::warning(

            'Student History error', ['error' => $e->getMessage()]

        );

    }



    $state = function($s) {

        $rawStatus = $s->status ?? null;

        $statusValue = $rawStatus instanceof \BackedEnum

            ? $rawStatus->value

            : ($rawStatus instanceof \UnitEnum

                ? $rawStatus->name

                : $rawStatus);



        return match(strtolower((string)($statusValue ?? ''))) {

            'completed','finished','success' => 'completed',

            'running','active','ready','in_progress' => 'running',

            'stopped','deleted','terminated','cancelled' => 'stopped',

            'failed','error' => 'failed',

            default => 'other'

        };

    };



    if ($filter !== 'all') {

        $rows = $rows->filter(fn($s) => $state($s) === $filter)->values();

    }



    $total = $rows->count();

    $perPage = 8;

    $last = max(1, (int)ceil($total / $perPage));

    $current = min($last, max(1, (int)request()->query('page',1)));

    $items = $rows->slice(($current-1)*$perPage, $perPage);



    $labels = [

        'completed'=>'Completed',

        'running'=>'In Progress',

        'stopped'=>'Stopped',

        'failed'=>'Failed',

        'other'=>'Unknown'

    ];



    $duration = function($s) {

        try {

            $seconds = $s->durationSeconds();

            if ($seconds === null || $seconds < 0) return '—';

            $seconds = (int)$seconds;

            return sprintf(

                '%02d:%02d:%02d',

                intdiv($seconds,3600),

                intdiv($seconds%3600,60),

                $seconds%60

            );

        } catch (\Throwable $e) {

            return '—';

        }

    };

@endphp





<div class="imh">

    <div class="imh-head">

        <div>

            <span class="imh-kicker">STUDENT / LAB HISTORY</span>

            <h1>Lab History</h1>

            <p>Riwayat lab dan status penyelesaian Anda.</p>

        </div>

        <form method="GET" class="imh-filter">

            <svg width="17" height="17" viewBox="0 0 24 24"

                 fill="none" stroke="currentColor" stroke-width="1.8">

                <path d="M4 7h16M7 12h10M10 17h4"/>

            </svg>

            <select name="status" aria-label="Filter status"

                    onchange="this.form.submit()">

                <option value="all" @selected($filter==='all')>All Status</option>

                <option value="completed" @selected($filter==='completed')>Completed</option>

                <option value="running" @selected($filter==='running')>In Progress</option>

                <option value="stopped" @selected($filter==='stopped')>Stopped</option>

                <option value="failed" @selected($filter==='failed')>Failed</option>

            </select>

        </form>

    </div>



    @if($error)

        <div class="imh-error">{{ $error }}</div>

    @endif



    <div class="imh-tablebox">

        <table>

            <thead>

                <tr>

                    <th>Course</th>

                    <th>Status</th>

                    <th>Started At</th>

                    <th>Duration</th>

                </tr>

            </thead>

            <tbody>

                @forelse($items as $session)

                    @php

                        $st = $state($session);

                        $name = $session->course?->title

                            ?? $session->course?->name

                            ?? 'Lab #'.$session->getKey();

                        $started = $session->started_at

                            ?? $session->created_at;

                    @endphp

                    <tr>

                        <td><strong>{{ $name }}</strong></td>

                        <td>

                            <span class="imh-status imh-{{ $st }}">

                                {{ $labels[$st] }}

                            </span>

                        </td>

                        <td>

                            {{ $started

                                ? \Illuminate\Support\Carbon::parse($started)->format('d M Y H:i')

                                : '—' }}

                        </td>

                        <td>{{ $duration($session) }}</td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="4" class="imh-empty">

                            Belum ada riwayat lab untuk filter ini.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>



    <div class="imh-footer">

        <span>

            Showing {{ $total ? ($current-1)*$perPage+1 : 0 }}

            –{{ min($current*$perPage,$total) }}

            of {{ $total }} sessions

        </span>

        <div class="imh-pagination">

            @if($current>1)

                <a href="{{ request()->fullUrlWithQuery(['page'=>$current-1]) }}">‹</a>

            @endif

            @for($i=max(1,$current-2);$i<=min($last,$current+2);$i++)

                <a class="{{ $i===$current ? 'active' : '' }}"

                   href="{{ request()->fullUrlWithQuery(['page'=>$i]) }}">

                    {{ $i }}

                </a>

            @endfor

            @if($current<$last)

                <a href="{{ request()->fullUrlWithQuery(['page'=>$current+1]) }}">›</a>

            @endif

        </div>

    </div>

</div>



<style>

.imh{padding:18px 5px 40px;color:#203459;min-height:70vh}

.imh-head{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}

.imh-kicker{font-size:10px;letter-spacing:1.4px;font-weight:800;color:#0797c8}

.imh h1{font-size:30px;font-weight:800;margin:9px 0}

.imh-head p{font-size:14px;color:#8498b9}

.imh-filter{display:flex;align-items:center;gap:9px;background:#ffffffb8;border:1px solid #dbe7fa;border-radius:11px;padding:11px;color:#6483b3}

.imh-filter select{border:0;background:transparent;outline:none;color:#5675a4;font-size:13px}

.imh-tablebox{background:#ffffffa6;backdrop-filter:blur(15px);border:1px solid #ffffffbd;border-radius:18px;overflow-x:auto;box-shadow:0 16px 38px #4c70ae12}

.imh table{width:100%;min-width:600px;border-collapse:collapse;text-align:left}

.imh th{padding:23px 22px;color:#6a83aa;font-size:12px;border-bottom:1px solid #e1e9f7}

.imh td{padding:25px 22px;color:#6b85ad;font-size:13px;border-bottom:1px solid #e8eef9}

.imh tr:last-child td{border-bottom:0}

.imh td strong{color:#456b9e;font-weight:600}

.imh-status{display:inline-block;padding:6px 10px;border-radius:7px;font-size:11px;font-weight:650;background:#eef4fc}

.imh-completed{background:#e4fbf3;color:#009d80}

.imh-running{background:#e6f4ff;color:#0089ed}

.imh-stopped,.imh-failed{background:#fff0f1;color:#e65b6d}

.imh-footer{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:22px 2px;color:#8599b8;font-size:12px}

.imh-pagination{display:flex;gap:7px}

.imh-pagination a{min-width:33px;height:33px;display:grid;place-items:center;background:#ffffffb5;border:1px solid #e2eafb;border-radius:8px;text-decoration:none;color:#6483b6}

.imh-pagination a.active{background:#1597f4;border-color:#1597f4;color:white}

.imh-empty{text-align:center;padding:55px!important}

.imh-error{padding:15px;margin-bottom:15px;border-radius:12px;background:#fff7ed;color:#925b22}

@media(max-width:650px){.imh-head{align-items:flex-start;flex-direction:column}.imh h1{font-size:25px}}

</style>
</div>


<style>

/* IMNEHNET Student Layout V4 */

.im-v4-student-page {

    width: 100%;

    min-width: 0;

    padding-bottom: 35px;

}



.im-v4-student-enter {

    animation: imStudentEnter .42s ease-out both;

}



@keyframes imStudentEnter {

    from { opacity: 0; transform: translateY(12px); }

    to { opacity: 1; transform: translateY(0); }

}



.im-v4-student-page .imh,

.im-v4-student-page .imd {

    background: rgba(245, 249, 255, .97);

    border: 1px solid rgba(255, 255, 255, .65);

    border-radius: 20px;

    padding: clamp(18px, 2.5vw, 35px);

    color: #243c61;

    box-shadow: 0 20px 60px rgba(7, 26, 54, .10);

}



.im-v4-student-page .imh {

    min-height: 65vh;

}



.im-v4-student-page .imd {

    min-height: 80vh;

}



.im-v4-student-page .imh-tablebox {

    transition: box-shadow .25s, transform .25s;

}



.im-v4-student-page .imh-tablebox:hover {

    box-shadow: 0 20px 50px rgba(33, 80, 150, .10);

}



.im-v4-student-page .imh tbody tr {

    transition: background .2s;

}



.im-v4-student-page .imh tbody tr:hover {

    background: rgba(208, 229, 255, .35);

}



.im-v4-student-page .imd-grid {

    grid-template-columns: 205px minmax(0, 1fr) 155px;

    gap: clamp(15px, 2vw, 30px);

}



.im-v4-student-page .imd-side {

    max-height: calc(100vh - 110px);

    overflow-y: auto;

    scrollbar-width: thin;

}



.im-v4-student-page .imd-side a,

.im-v4-student-page .imd-toc a {

    transition: color .2s, background .2s, transform .2s;

}



.im-v4-student-page .imd-side a:hover {

    transform: translateX(3px);

}



.im-v4-student-page .imd-toc a.im-doc-current {

    color: #0289e5;

    font-weight: 750;

}



.im-doc-search {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 10px;

    background: #fff;

    border: 1px solid #dae7f7;

    border-radius: 9px;

    color: #7e98b8;

    margin-bottom: 12px;

}



.im-doc-search input {

    width: 100%;

    min-width: 0;

    outline: none;

    border: 0;

    background: transparent;

    color: #294a73;

    font-size: 11px;

}



.im-doc-search input::placeholder {

    color: #91a4bc;

}



.im-v4-student-page .imd-side a[hidden] {

    display: none !important;

}



.im-v4-student-page .imd-content {

    line-height: 1.75;

}



.im-v4-student-page .imd-content section {

    scroll-margin-top: 35px;

}



.im-v4-student-page .imd-cards > div {

    transition: transform .2s, box-shadow .2s;

}



.im-v4-student-page .imd-cards > div:hover {

    transform: translateY(-3px);

    box-shadow: 0 10px 24px rgba(35, 87, 149, .08);

}



html {

    scroll-behavior: smooth;

}



@media (max-width: 1150px) {

    .im-v4-student-page .imd-grid {

        grid-template-columns: 175px minmax(0, 1fr);

    }

}



@media (max-width: 750px) {

    .im-v4-student-page .imd-grid {

        display: block;

    }

    .im-v4-student-page .imd-side {

        position: static;

        max-height: none;

        margin-bottom: 24px;

    }

    .im-doc-search {

        min-width: 175px;

    }

    .im-v4-student-page .imh,

    .im-v4-student-page .imd {

        padding: 16px;

        border-radius: 13px;

    }

}



@media (prefers-reduced-motion: reduce) {

    .im-v4-student-enter {

        animation: none;

    }

    html {

        scroll-behavior: auto;

    }

}

</style>


</main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">@foreach($links as $i => $link)<a href="{{ $link[1] }}" class="{{ $i === 3 ? 'is-active' : '' }}">{!! $svg($link[2]) !!}<span>{{ $i === 3 ? 'Home' : ($i === 4 ? 'Docs' : $link[0]) }}</span></a>@endforeach</nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    
</div>
</x-filament-panels::page>






























<style id="imnehnet-student-header-v6">

.im-student-dropdown{position:relative;display:inline-flex;align-items:center}

.im-student-v6-trigger{display:flex;align-items:center;gap:10px;background:transparent;border:0;padding:3px;cursor:pointer;color:inherit;font:inherit;text-align:left}

.im-student-v6-trigger>svg{width:14px;height:14px;color:#8a96aa;transition:transform .2s}

.im-student-v6-trigger[aria-expanded="true"]>svg{transform:rotate(180deg)}

.im-student-v6-identity{display:flex;flex-direction:column;gap:3px;max-width:175px}

.im-student-v6-identity strong{font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.im-student-v6-identity small{font-size:11px;color:#8996ac}

.im-student-menu.im-student-v6-floating{

    position:fixed!important;z-index:2147483647!important;

    right:auto!important;left:0;top:0;margin:0!important;

    width:310px;max-width:calc(100vw - 24px);

    padding:0;background:#fff;color:#26334d;

    border:1px solid #e5eaf2;border-radius:16px;

    box-shadow:0 18px 48px rgba(23,42,77,.17);

    overflow:hidden;

}

.im-student-menu.im-student-v6-floating[hidden]{display:none!important}

.im-student-menu.im-student-v6-floating.im-student-v6-open{

    animation:imStudentV6In .22s cubic-bezier(.2,.8,.2,1) both;

}

@keyframes imStudentV6In{

    from{opacity:0;translate:0 -9px;scale:.97}

    to{opacity:1;translate:0 0;scale:1}

}

.im-student-v6-heading{padding:18px 20px;border-bottom:1px solid #edf0f5}

.im-student-v6-heading strong{display:block;font-size:14px}

.im-student-v6-heading small{display:block;font-size:11px;color:#8b98ad;margin-top:5px}

.im-student-v6-empty{padding:32px 15px;display:flex;align-items:center;flex-direction:column;text-align:center}

.im-student-v6-empty-icon{display:grid;place-items:center;width:48px;height:48px;background:#ecfdf9;color:#18a89c;border-radius:15px;margin-bottom:12px}

.im-student-v6-empty strong{font-size:13px}

.im-student-v6-empty p{font-size:11px;color:#94a0b3;margin:7px 0 0}

.im-student-v6-footer{padding:12px;text-align:center;border-top:1px solid #edf0f5;color:#a0aabb;font-size:10px}

.im-student-v6-profile-heading{display:flex;align-items:center;gap:12px;padding:18px;border-bottom:1px solid #edf0f5}

.im-student-v6-large-avatar{width:44px;height:44px;flex-shrink:0;border-radius:50%;display:grid;place-items:center;background:#e8eeff;color:#546ce0;font-size:17px;font-weight:750}

.im-student-v6-profile-heading>div{min-width:0}

.im-student-v6-profile-heading strong{display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.im-student-v6-profile-heading small{display:block;font-size:11px;color:#94a0b3;margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.im-student-v6-links{padding:9px}

.im-student-v6-links a{display:flex;align-items:center;gap:12px;padding:12px;border-radius:9px;text-decoration:none;color:#51617b;font-size:12px;font-weight:600;transition:background .16s,color .16s}

.im-student-v6-links a:hover{background:#f1f6ff;color:#4165c7}

.im-student-v6-links svg{width:17px;height:17px}

.im-student-v6-signout-wrap{border-top:1px solid #edf0f5;padding:12px}

.im-student-v6-signout{display:flex;justify-content:center;align-items:center;gap:10px;width:100%;border:0;border-radius:9px;padding:12px;background:#fff0f0;color:#e34b56;font-size:12px;font-weight:700;cursor:pointer;transition:background .16s}

.im-student-v6-signout:hover{background:#ffe0e4}

.im-student-v6-signout svg{width:17px;height:17px}

@media(max-width:640px){.im-student-v6-identity{display:none}}

@media(prefers-reduced-motion:reduce){.im-student-menu.im-student-v6-open{animation:none}}

</style>

<script>

document.addEventListener('DOMContentLoaded',function(){

    const items=[];

    document.querySelectorAll('.im-student-dropdown').forEach(function(wrapper){

        const trigger=wrapper.querySelector('[aria-expanded]');

        const menu=wrapper.querySelector('.im-student-menu');

        if(!trigger||!menu)return;

        menu.hidden=true;

        menu.classList.add('im-student-v6-floating');

        document.body.appendChild(menu);

        items.push({trigger,menu});

        trigger.addEventListener('click',function(e){

            e.preventDefault();e.stopPropagation();

            const open=menu.hidden;

            closeAll();

            if(!open)return;

            menu.hidden=false;

            const r=trigger.getBoundingClientRect();

            const w=Math.min(310,window.innerWidth-24);

            const left=Math.max(12,Math.min(r.right-w,window.innerWidth-w-12));

            menu.style.setProperty('width',w+'px','important');

            menu.style.setProperty('left',left+'px','important');

            menu.style.setProperty('right','auto','important');

            const h=menu.offsetHeight;

            const below=r.bottom+10;

            const top=below+h<=window.innerHeight-12?below:Math.max(12,r.top-h-10);

            menu.style.setProperty('top',top+'px','important');

            void menu.offsetWidth;

            menu.classList.add('im-student-v6-open');

            trigger.setAttribute('aria-expanded','true');

        });

        menu.addEventListener('click',e=>e.stopPropagation());

    });

    function closeAll(){

        items.forEach(function(item){

            item.menu.hidden=true;

            item.menu.classList.remove('im-student-v6-open');

            item.trigger.setAttribute('aria-expanded','false');

        });

    }

    document.addEventListener('click',closeAll);

    document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll()});

    window.addEventListener('resize',closeAll);

    window.addEventListener('scroll',closeAll,true);

});

</script>




<style>

/* IMNEHNET_STUDENT_THEME_V41 */

#im-v4[data-theme="dark"] .im-v4-student-page {

    --sp-bg: #151f35;

    --sp-surface: #1c2a43;

    --sp-surface-hover: #243652;

    --sp-border: rgba(139, 169, 218, .18);

    --sp-text: #eef5ff;

    --sp-muted: #a8b9d4;

    --sp-accent: #66d9e4;

    --sp-shadow: rgba(0, 0, 0, .16);

}



#im-v4[data-theme="dark"] .im-v4-student-page .imh,

#im-v4[data-theme="dark"] .im-v4-student-page .imd {

    background: var(--sp-bg);

    border-color: var(--sp-border);

    color: var(--sp-text);

    box-shadow: 0 20px 55px var(--sp-shadow);

}



#im-v4[data-theme="dark"] .im-v4-student-page h1,

#im-v4[data-theme="dark"] .im-v4-student-page h2,

#im-v4[data-theme="dark"] .im-v4-student-page h3,

#im-v4[data-theme="dark"] .im-v4-student-page .imh h1,

#im-v4[data-theme="dark"] .im-v4-student-page .imd h1,

#im-v4[data-theme="dark"] .im-v4-student-page .imd h2,

#im-v4[data-theme="dark"] .im-v4-student-page .imd-cards strong,

#im-v4[data-theme="dark"] .im-v4-student-page .imd li strong {

    color: var(--sp-text);

}



#im-v4[data-theme="dark"] .im-v4-student-page p,

#im-v4[data-theme="dark"] .im-v4-student-page .imh-head p,

#im-v4[data-theme="dark"] .im-v4-student-page .imd-lead,

#im-v4[data-theme="dark"] .im-v4-student-page .imd section p,

#im-v4[data-theme="dark"] .im-v4-student-page .imd li,

#im-v4[data-theme="dark"] .im-v4-student-page .imh-footer {

    color: var(--sp-muted);

}



#im-v4[data-theme="dark"] .imh-tablebox,

#im-v4[data-theme="dark"] .imd-cards > div,

#im-v4[data-theme="dark"] .imd-note,

#im-v4[data-theme="dark"] .imd-next,

#im-v4[data-theme="dark"] .imd-code,

#im-v4[data-theme="dark"] .imd-side a,

#im-v4[data-theme="dark"] .im-doc-search,

#im-v4[data-theme="dark"] .imh-filter,

#im-v4[data-theme="dark"] .imh-pagination a {

    background: var(--sp-surface);

    border-color: var(--sp-border);

    color: var(--sp-muted);

}



#im-v4[data-theme="dark"] .imh th,

#im-v4[data-theme="dark"] .imh td {

    color: var(--sp-muted);

    border-bottom-color: var(--sp-border);

}



#im-v4[data-theme="dark"] .imh td strong {

    color: var(--sp-text);

}



#im-v4[data-theme="dark"] .imh tbody tr:hover {

    background: var(--sp-surface-hover);

}



#im-v4[data-theme="dark"] .imh-filter select,

#im-v4[data-theme="dark"] .im-doc-search input {

    color: var(--sp-text);

    background: transparent;

}



#im-v4[data-theme="dark"] .imh-filter select option {

    color: #17243b;

    background: #f5f8ff;

}



#im-v4[data-theme="dark"] .imd-side a:hover,

#im-v4[data-theme="dark"] .imd-side a.selected {

    background: var(--sp-surface-hover);

    color: var(--sp-accent);

}



#im-v4[data-theme="dark"] .imd-toc a.im-doc-current,

#im-v4[data-theme="dark"] .imd-breadcrumb,

#im-v4[data-theme="dark"] .imd-bar a,

#im-v4[data-theme="dark"] .imd-next {

    color: var(--sp-accent);

}



#im-v4[data-theme="dark"] .imd-toc {

    border-left-color: var(--sp-border);

}



#im-v4[data-theme="dark"] .imd-note.imd-warning {

    background: rgba(171, 113, 30, .12);

    border-color: rgba(243, 179, 74, .35);

    color: #f3c985;

}



#im-v4[data-theme="dark"] .imh-status.imh-running {

    background: rgba(40, 147, 226, .17);

    color: #7ac7ff;

}



#im-v4[data-theme="dark"] .imh-status.imh-completed {

    background: rgba(34, 197, 142, .16);

    color: #75e6bd;

}



#im-v4[data-theme="dark"] .imh-status.imh-stopped,

#im-v4[data-theme="dark"] .imh-status.imh-failed {

    background: rgba(239, 91, 115, .14);

    color: #ff9eae;

}



#im-v4[data-theme="dark"] .imh-pagination a.active {

    background: #168fd4;

    color: white;

}



#im-v4[data-theme="dark"] .imh-error {

    background: rgba(180, 100, 30, .15);

    color: #f3c985;

}

</style>




<style>

/* IMNEHNET_STUDENT_V42 */



/* Documentation navigation icons */

.imd-side a {

    display: flex;

    align-items: center;

    gap: 10px;

}



.imd-side a::before {

    content: "";

    width: 15px;

    height: 15px;

    flex: 0 0 15px;

    background-color: currentColor;

    opacity: .8;

    mask-repeat: no-repeat;

    mask-position: center;

    mask-size: contain;

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3z'/%3E%3C/svg%3E");

}



.imd-side a[href*="#first"]::before,

.imd-side a[href*="#containers"]::before,

.imd-side a[href*="#session"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='18' height='7' rx='2'/%3E%3Crect x='3' y='14' width='18' height='7' rx='2'/%3E%3C/svg%3E");

}



.imd-side a[href*="#pentest"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 6 6-6 6M12 18h8'/%3E%3C/svg%3E");

}



.imd-side a[href*="#troubleshooting"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3Cpath d='M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01'/%3E%3C/svg%3E");

}



.imd-side a[href*="/terms"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z'/%3E%3Cpath d='m9 12 2 2 4-4'/%3E%3C/svg%3E");

}



/* Manual Book download */

.im-doc-manual {

    margin: 22px 0;

    padding: 18px;

    border: 1px solid #d7e6f7;

    border-radius: 13px;

    background: rgba(255,255,255,.72);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 18px;

}



.im-doc-manual strong {

    display: block;

    font-size: 13px;

    color: #254469;

}



.im-doc-manual p {

    font-size: 11px;

    margin: 5px 0 0;

    color: #7a90af;

}



.im-doc-manual a,

.im-doc-manual .im-doc-unavailable {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 10px 13px;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

    text-decoration: none;

}



.im-doc-manual a {

    background: #0a94d9;

    color: white;

}



.im-doc-manual .im-doc-unavailable {

    background: #e9eef5;

    color: #8191a8;

}



#im-v4[data-theme="dark"] .im-doc-manual {

    background: #1c2a43;

    border-color: rgba(139,169,218,.18);

}



#im-v4[data-theme="dark"] .im-doc-manual strong {

    color: #edf5ff;

}



#im-v4[data-theme="dark"] .im-doc-manual p {

    color: #a8b9d4;

}



#im-v4[data-theme="dark"] .im-doc-unavailable {

    background: #263650;

    color: #a8b9d4;

}



/* Header V6 dropdown theme compatibility */

#im-v4[data-theme="dark"] .im-student-menu,

#im-v4[data-theme="dark"] .im-student-dropdown-menu,

#im-v4[data-theme="dark"] [data-im-student-notifications] .im-student-menu {

    background: #1d2a43 !important;

    border-color: rgba(139,169,218,.20) !important;

    color: #edf5ff !important;

    box-shadow: 0 20px 55px rgba(0,0,0,.3) !important;

}



#im-v4[data-theme="dark"] .im-student-menu a,

#im-v4[data-theme="dark"] .im-student-menu button,

#im-v4[data-theme="dark"] .im-student-menu strong,

#im-v4[data-theme="dark"] .im-student-dropdown-menu a,

#im-v4[data-theme="dark"] .im-student-dropdown-menu button {

    color: #e5efff;

}



#im-v4[data-theme="dark"] .im-student-menu a:hover,

#im-v4[data-theme="dark"] .im-student-dropdown-menu a:hover {

    background: #293a58 !important;

}



#im-v4[data-theme="dark"] .im-student-menu small,

#im-v4[data-theme="dark"] .im-student-menu p,

#im-v4[data-theme="dark"] .im-student-menu span {

    color: #aebfda;

}



@media(max-width:600px) {

    .im-doc-manual {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>




<style>

/* IMNEHNET_DROPDOWN_DARK_V43 */



/* Dark mode: profile & notification dropdown */

#im-v4[data-theme="dark"] .im-student-dropdown,

#im-v4[data-theme="dark"] .im-student-menu,

#im-v4[data-theme="dark"] .im-student-profile-menu,

#im-v4[data-theme="dark"] .im-student-notification-menu,

#im-v4[data-theme="dark"] [data-im-student-profile] [role="menu"],

#im-v4[data-theme="dark"] [data-im-student-notifications] [role="menu"] {

    background-color: #1b2942 !important;

    border-color: #344561 !important;

    color: #edf4ff !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown a,

#im-v4[data-theme="dark"] .im-student-dropdown button,

#im-v4[data-theme="dark"] .im-student-dropdown strong,

#im-v4[data-theme="dark"] .im-student-menu a,

#im-v4[data-theme="dark"] .im-student-menu button {

    color: #edf4ff !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown small,

#im-v4[data-theme="dark"] .im-student-dropdown p,

#im-v4[data-theme="dark"] .im-student-dropdown span {

    color: #aebed6 !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown a:hover,

#im-v4[data-theme="dark"] .im-student-menu a:hover {

    background-color: #293b59 !important;

}



/* Keep sign-out button recognizable */

#im-v4[data-theme="dark"] .im-student-dropdown a[href*="logout"],

#im-v4[data-theme="dark"] .im-student-dropdown button[type="submit"] {

    background-color: rgba(239, 68, 68, .13) !important;

    color: #ff8f9c !important;

}

</style>

