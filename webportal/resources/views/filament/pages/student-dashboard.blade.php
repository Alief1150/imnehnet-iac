<x-filament-panels::page>
@php
    $d = $this->dashboardData();
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
        ['Profile', url('/student/profile'), 'user-round'],
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
            'user-round' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
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
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
        <main class="im-v4-content" id="im-v4-main">
            <section class="im-v4-welcome"><span class="im-v4-welcome-icon">{!! $svg('sun') !!}</span><div><h1>Welcome back, {{ $user?->name ?? 'Student' }}!</h1><p>Continue your cybersecurity learning journey.</p></div></section>
            <section class="im-v4-stats" aria-label="Your lab statistics">
                <article class="card im-v4-stat"><span class="im-v4-stat-icon teal">{!! $svg('server') !!}</span><p>Total Labs</p><strong>{{ $d['total'] }}</strong><small>Aktif &amp; sudah dihapus</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon blue">{!! $svg('activity') !!}</span><p>Active Lab</p><strong>{{ $d['active'] }}</strong><small>{{ $d['active'] > 0 ? 'Running / Ready' : 'No active labs' }}</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon violet">{!! $svg('circle-check') !!}</span><p>CTF Progress</p><strong>{{ $completed }} / {{ $objectives }}</strong><small>{{ $d['course_name'] }} · latest attempt</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon amber">{!! $svg('clock') !!}</span><p>Total Lab Time</p><strong id="imnehnet-lab-time-v3" data-seconds="{{ (int) ($d['lab_time_seconds'] ?? 0) }}" data-live="{{ (int) ($d['lab_time_active_count'] ?? 0) }}">{{ intdiv(max(0, (int) ($d['lab_time_seconds'] ?? 0)), 60) }}</strong><small>Akumulasi waktu lab</small></article>
            </section>
            <div class="im-v4-section-head"><h2>Usage Statistics</h2><span class="im-v4-period badge">Last 7 Days</span></div>
            <section class="im-v4-charts" aria-label="Usage charts">
                <article class="card im-v4-chart"><h3>Lab Session Activity (Daily)</h3><div class="im-v4-chart-area" id="im-v4-activity" aria-label="Activity in the last 7 days"></div></article>
                <article class="card im-v4-chart"><h3>Pentest Objective Progress</h3><div class="im-v4-chart-area" id="im-v4-completion" aria-label="Completed objectives"></div><p class="im-v4-chart-caption">{{ $completed }} / {{ $objectives }} objectives</p></article>
                <article class="card im-v4-chart"><h3>Pentest Terminal Usage (Hours)</h3><div class="im-v4-chart-area" id="im-v4-usage" aria-label="Usage hours in the last 7 days"></div></article>
            </section>
            <section class="im-v4-history" id="im-v4-history"><div class="im-v4-section-head"><div><h2>History</h2><p class="im-v4-history-sub">Tiga sesi lab terbaru</p></div><a href="{{ url('/student/my-labs') }}" class="im-v4-see-all">Lihat Semua {!! $svg('arrow-right') !!}</a></div>
                <div class="im-v4-table-wrap"><table class="table im-v4-table"><thead><tr><th>Course</th><th>Status</th><th>Started At</th><th>Duration</th></tr></thead><tbody>
                    @forelse($d['history']->take(3) as $session)
                        @php $status = $session->status?->value ?? (string) $session->status; $seconds = $session->durationSeconds(); $duration = $seconds === null ? '—' : sprintf('%02d:%02d:%02d', intdiv((int)$seconds,3600), intdiv((int)$seconds%3600,60), (int)$seconds%60); @endphp
                        <tr><td>{{ $session->course?->title ?? 'Lab Session #' . $session->id }}</td><td><span class="badge im-v4-status" data-status="{{ $status }}">{{ ucwords(strtolower(str_replace('_', ' ', $status))) }}</span></td><td>{{ $session->started_at?->format('d M Y H:i') ?? 'Not started' }}</td><td>{{ $duration }}</td></tr>
                    @empty<tr><td colspan="4">Belum ada riwayat sesi lab.</td></tr>@endforelse
                </tbody></table></div>
                <div class="im-v4-history-cards">
                    @forelse($d['history']->take(3) as $session)
                        @php $status = $session->status?->value ?? (string) $session->status; $seconds = $session->durationSeconds(); @endphp
                        <article class="card im-v4-history-card"><div><strong>{{ $session->course?->title ?? 'Lab Session #' . $session->id }}</strong><span class="badge im-v4-status" data-status="{{ $status }}">{{ ucwords(strtolower(str_replace('_',' ',$status))) }}</span></div><p>{{ $seconds === null ? '—' : sprintf('%02d:%02d:%02d', intdiv((int)$seconds,3600), intdiv((int)$seconds%3600,60), (int)$seconds%60) }}</p><small>Started at · {{ $session->started_at?->format('d M Y H:i') ?? 'Not started' }}</small></article>
                    @empty<p class="im-v4-empty">Belum ada riwayat sesi lab.</p>@endforelse
                </div>
            </section>
        </main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">
        @foreach (\App\Support\StudentNavigation::items() as $bottomItem)
            @if (in_array($bottomItem['key'], ['dashboard', 'labs', 'pentest', 'history', 'documentation'], true))
                <a href="{{ $bottomItem['url'] }}" @if($bottomItem['active']) aria-current="page" class="is-active" @endif>{!! \App\Support\StudentNavigation::icon($bottomItem['icon']) !!}<span>{{ $bottomItem['label'] }}</span></a>
            @endif
        @endforeach
    </nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    <script type="application/json" id="im-v4-chart-data">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
    <!-- IMNEHNET_LAB_TIME_V3: only visual ticker; duration calculation stays server-side. -->
    <script data-imnehnet-lab-time-v3>
(function(){
 function mount(){const el=document.getElementById('imnehnet-lab-time-v3');if(!el||el.dataset.minuteMounted==='1')return;
   el.dataset.minuteMounted='1';const base=Math.max(0,Number(el.dataset.seconds)||0),active=Math.max(0,Number(el.dataset.live)||0),start=performance.now();
   function update(){if(!el.isConnected){clearInterval(t);return}const seconds=Math.floor(base+active*(performance.now()-start)/1000);
     const minutes=Math.floor(seconds/60);el.textContent=minutes===0?'0':minutes>=60?(Math.floor(minutes/60)+' jam'):(minutes+' menit');}
   const t=setInterval(update,5000);update();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
 document.addEventListener('livewire:navigated',mount);
})();
</script>
@include('filament.pages.partials.student-shell-global')
</x-filament-panels::page>











































<!-- IMNEHNET_STUDENT_PROFILE_V1 -->
