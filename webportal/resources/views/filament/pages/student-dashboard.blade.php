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
    <aside class="im-v4-sidebar" aria-label="Student sidebar">
        <a class="im-v4-brand" href="{{ url('/student') }}"><span class="im-v4-logo">{!! $svg('box') !!}</span><span><strong>IMNEHNET</strong><small>Lab-as-a-Service</small></span></a>
        <nav class="im-v4-menu menu" aria-label="Main navigation">
            @foreach($links as $i => $link)
                <a class="im-v4-nav-link {{ $i === 0 ? 'is-active' : '' }}" href="{{ $link[1] }}">{!! $svg($link[2]) !!}<span>{{ $link[0] }}</span></a>
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

        <span class="im-v4-user-avatar">@if($user?->getFilamentAvatarUrl())<img src="{{ $user->getFilamentAvatarUrl() }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">@else{{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}@endif</span>

        <span class="im-student-v6-identity">

            <strong>{{ $user?->name ?? 'Student' }}</strong>

            <small>Student</small>

        </span>

        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>

    </button>

    <div class="im-student-menu im-student-profile-menu" hidden>

        <div class="im-student-v6-profile-heading">

            <span class="im-student-v6-large-avatar">@if($user?->getFilamentAvatarUrl())<img src="{{ $user->getFilamentAvatarUrl() }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">@else{{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}@endif</span>

            <div>

                <strong>{{ $user?->name ?? 'Student' }}</strong>

                <small>{{ $user?->email ?? '' }}</small>

            </div>

        </div>

        <div class="im-student-v6-links">

            <a href="{{ url('/student') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg> <span>My Dashboard</span></a>

            <a href="{{ url('/student/my-labs') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/></svg> <span>My Labs</span></a>

        </div>

        <div class="im-student-v6-links"><a href="{{ url('/student/profile') }}">{!! $svg('user-round') !!}<span>My Profile</span></a></div>
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
            <section class="im-v4-welcome"><span class="im-v4-welcome-icon">{!! $svg('sun') !!}</span><div><h1>Welcome back, {{ $user?->name ?? 'Student' }}!</h1><p>Continue your cybersecurity learning journey.</p></div></section>
            <section class="im-v4-stats" aria-label="Your lab statistics">
                <article class="card im-v4-stat"><span class="im-v4-stat-icon teal">{!! $svg('server') !!}</span><p>Total Labs</p><strong>{{ $d['total'] }}</strong><small>Aktif &amp; sudah dihapus</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon blue">{!! $svg('activity') !!}</span><p>Active Lab</p><strong>{{ $d['active'] }}</strong><small>{{ $d['active'] > 0 ? 'Running / Ready' : 'No active labs' }}</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon violet">{!! $svg('circle-check') !!}</span><p>CTF Progress</p><strong>{{ $completed }} / {{ $objectives }}</strong><small>Objectives completed</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon amber">{!! $svg('clock') !!}</span><p>Total Lab Time</p><strong>{{ number_format($d['hours'], 1) }} <em>hours</em></strong><small>Across all sessions</small></article>
            </section>
            <div class="im-v4-section-head"><h2>Usage Statistics</h2><span class="im-v4-period badge">Last 7 Days</span></div>
            <section class="im-v4-charts" aria-label="Usage charts">
                <article class="card im-v4-chart"><h3>Lab Session Activity</h3><div class="im-v4-chart-area" id="im-v4-activity" aria-label="Activity in the last 7 days"></div></article>
                <article class="card im-v4-chart"><h3>Course Completion</h3><div class="im-v4-chart-area" id="im-v4-completion" aria-label="Completed objectives"></div><p class="im-v4-chart-caption">{{ $completed }} / {{ $objectives }} objectives</p></article>
                <article class="card im-v4-chart"><h3>Lab Usage (Hours)</h3><div class="im-v4-chart-area" id="im-v4-usage" aria-label="Usage hours in the last 7 days"></div></article>
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
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">@foreach(array_slice($links, 0, 5) as $i => $link)<a href="{{ $link[1] }}" class="{{ $i === 0 ? 'is-active' : '' }}">{!! $svg($link[2]) !!}<span>{{ $i === 0 ? 'Home' : ($i === 4 ? 'Docs' : $link[0]) }}</span></a>@endforeach</nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    <script type="application/json" id="im-v4-chart-data">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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




<style id="imnehnet-header-v44-theme">

/* IMNEHNET_HEADER_V44_PORTAL_THEME */



/* Menargetkan dropdown yang dipindahkan langsung ke body */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating {

    background: #1c2942 !important;

    color: #edf4ff !important;

    border-color: #354764 !important;

    box-shadow: 0 20px 55px rgba(0,0,0,.38) !important;

}



/* Warna teks dan ikon */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  :is(a, button, strong, h3, h4, svg) {

    color: #edf4ff !important;

}



/* Teks sekunder */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  :is(small, p) {

    color: #aebed6 !important;

}



/* Garis pemisah */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  :is(hr) {

    border-color: #354764 !important;

}



/* Warna item saat hover */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  a:hover {

    background: #293b59 !important;

}



/* Tombol logout tetap merah */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  :is(a[href*="logout"], button[type="submit"]) {

    background: rgba(239,68,68,.12) !important;

    color: #ff8e9e !important;

}

</style>




<style id="imnehnet-header-v45-polish">

/* IMNEHNET_HEADER_V45_POLISH */



/* Softer separation lines throughout both dropdowns */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  :is(header, footer, section, hr) {

    border-color: rgba(139,169,218,.15) !important;

}



/* Override bright dividers without affecting the layout */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  > * {

    border-color: rgba(139,169,218,.15) !important;

}



/* Notification empty-state icon: preserve visibility */

body:has(#im-v4[data-theme="dark"])

  > .im-student-notification-menu.im-student-v6-floating

  svg {

    color: #60d7cf !important;

    stroke: currentColor !important;

    opacity: 1 !important;

}



/* Restore icon contrast in pale icon containers */

body:has(#im-v4[data-theme="dark"])

  > .im-student-notification-menu.im-student-v6-floating

  :is([class*="empty"], [class*="icon"]) {

    color: #59cfc7 !important;

}



/* Muted description and footer */

body:has(#im-v4[data-theme="dark"])

  > .im-student-notification-menu.im-student-v6-floating

  :is(small, p, footer) {

    color: #a8b9d4 !important;

}



/* Smooth item interactions */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating

  a {

    transition: background-color .18s ease, color .18s ease;

}



/* Profile avatar remains visually distinct */

body:has(#im-v4[data-theme="dark"])

  > .im-student-profile-menu.im-student-v6-floating

  :is([class*="avatar"], [class*="initial"]) {

    color: #86a8ff !important;

}



/* Subtle menu border */

body:has(#im-v4[data-theme="dark"])

  > .im-student-menu.im-student-v6-floating {

    border: 1px solid rgba(139,169,218,.20) !important;

}

</style>


<!-- IMNEHNET_STUDENT_PROFILE_V1 -->
