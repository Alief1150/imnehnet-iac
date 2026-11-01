<x-filament-panels::page>

<style>
@keyframes im-start-spin {
    to { transform: rotate(360deg); }
}
.im-v6-start[disabled] {
    cursor: wait;
    opacity: .85;
}
</style>


<style>
/* IMNEHNET MY LABS VIEWPORT FIX */

/* Restore scrolling inside the student content viewport. */
#im-v4 .im-v4-main,
#im-v4 .im-v4-content {
    min-height: 0;
    overflow-y: auto;
}

/* Avoid clipping the course grid. */
#im-v4 .im-v4-main,
#im-v4 .im-v4-content,
#im-v4 .im-v4-courses,
#im-v4 .im-v4-course-grid {
    max-height: none;
}

/* Keep the page content reachable on desktop and mobile. */
@media (max-width: 768px) {
    #im-v4 .im-v4-main,
    #im-v4 .im-v4-content {
        padding-bottom: 90px;
    }
}
</style>

@php
    $course = $this->course();
    $session = $this->currentSession();
    $used = $session !== null ? 1 : 0;
    $status = $session?->status?->value ?? 'AVAILABLE';
    $progress = $session ? $session->progressCount() : ['completed' => 0, 'total' => ($course?->objectives()->count() ?? 0)];
    $progressPct = $progress['total'] > 0 ? min(100, round($progress['completed'] * 100 / $progress['total'])) : 0;
    $seconds = $session?->durationSeconds();
    $duration = $seconds === null ? 'Not started' : sprintf('%02d:%02d:%02d', intdiv((int) $seconds, 3600), intdiv((int) $seconds % 3600, 60), (int) $seconds % 60);
    $assetCss = collect(glob(public_path('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-*.css')) ?: [])->first();
    $assetJs = collect(glob(public_path('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-*.js')) ?: [])->first();
    $user = auth()->user();
    $links = [
        ['Dashboard', url('/student'), 'layout-dashboard'],
        ['My Labs', url('/student/my-labs'), 'server'],
        ['Pentest', url('/student/guided-ctf'), 'terminal'],
        ['History', url('/student') . '#im-v4-history', 'history'],
        ['Documentation', url('/student/guided-ctf'), 'book-open'],
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
            'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'x' => '<path d="M5 5l14 14M19 5 5 19"/>',
        ];
        $path = $map[$name] ?? $map['box'];
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    };
@endphp
@if ($assetCss)
<link rel="stylesheet" href="{{ asset('imnehnet-v4-assets/assets/' . basename($assetCss)) }}?v={{ filemtime($assetCss) }}">
@endif
<style>
/* V7 isolated self-contained course grid: no separate CSS request */
#im-v4 .im-v6-courses{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:20px!important}
#im-v4 .im-v6-course{display:flex!important;flex-direction:column!important;align-items:center!important;min-height:365px!important;box-sizing:border-box!important}
#im-v4 .im-v6-course .im-v6-btn{display:flex!important}
@media(max-width:650px){#im-v4 .im-v6-courses{grid-template-columns:1fr!important}}
/* IMNEHNET My Labs V6 — export-aligned course selection */
#im-v4 .im-v5-content {max-width:none!important;width:100%!important;padding:22px 28px 60px!important;min-height:calc(100vh - 74px);}
#im-v4 .im-v6-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:0 0 24px;}
#im-v4 .im-v6-heading-title{display:flex;align-items:center;gap:17px;}
#im-v4 .im-v6-heading-icon{width:54px;height:54px;display:grid;place-items:center;background:rgba(220,243,255,.7);border-radius:14px;color:#079af5;}
#im-v4 .im-v6-heading-icon svg{width:30px;height:30px;}
#im-v4 .im-v6-heading h1{font-size:26px;line-height:1.25;font-weight:800;color:#152756;margin:0;}
#im-v4 .im-v6-heading p{font-size:14px;color:#8298c2;margin:4px 0 0;}
#im-v4 .im-v6-quota{background:rgba(255,255,255,.56);border:1px solid rgba(255,255,255,.7);border-radius:10px;padding:10px 17px;font-size:21px;font-weight:800;color:#172b59;white-space:nowrap;}
#im-v4 .im-v6-courses{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-items:stretch;}
#im-v4 .im-v6-course{min-height:365px;display:flex;flex-direction:column;align-items:center;text-align:center;padding:22px 22px 20px;background:rgba(255,255,255,.47);border:1px solid rgba(255,255,255,.82);box-shadow:0 8px 24px rgba(73,103,152,.055);backdrop-filter:blur(12px);border-radius:16px;color:#152756;min-width:0;}
#im-v4 .im-v6-course-icon{width:76px;height:76px;border-radius:17px;display:grid;place-items:center;background:rgba(255,255,255,.52);color:#7e99c8;flex-shrink:0;}
#im-v4 .im-v6-course-icon svg{width:38px;height:38px;}
#im-v4 .im-v6-primary-icon{background:rgba(226,245,255,.7);color:#009dff;}
#im-v4 .im-v6-course h2{font-size:18px;font-weight:750;line-height:1.35;margin:12px 0 0;color:inherit;}
#im-v4 .im-v6-desc{font-size:13px;line-height:1.5;color:#7d96bf;margin:12px 0 0;max-width:440px;}
#im-v4 .im-v6-player-note{display:flex;align-items:center;justify-content:center;gap:5px;color:#8199c1;font-size:12px;margin:10px 0 0;}
#im-v4 .im-v6-player-note svg{width:13px;height:13px;}
#im-v4 .im-v6-spacer{flex:1;min-height:20px;}
#im-v4 .im-v6-meta{display:flex;gap:17px;justify-content:center;align-items:center;color:#8199c1;font-size:12px;margin-bottom:13px;flex-wrap:wrap;}
#im-v4 .im-v6-difficulty{background:rgba(255,255,255,.54);color:#7094b8;border-radius:5px;padding:4px 9px;font-size:11px;}
#im-v4 .im-v6-primary-icon~h2+.im-v6-desc~.im-v6-player-note{color:#718ab7;}
#im-v4 .im-v6-course .im-v6-btn{min-height:40px;width:100%;border-radius:8px;display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:650;padding:9px 12px;cursor:pointer;}
#im-v4 .im-v6-btn-primary{background:#1199fb;color:white;border:1px solid #1199fb;}
#im-v4 .im-v6-btn-primary:hover{background:#087bd4;}
#im-v4 .im-v6-btn-muted{background:rgba(230,239,251,.65);color:#365f90;border:1px solid rgba(151,177,210,.35);}
#im-v4 .im-v6-btn-danger{background:rgba(244,63,94,.1);color:#d94a61;border:1px solid rgba(244,63,94,.3);}
#im-v4 .im-v6-btn-locked{background:rgba(255,255,255,.46);color:#7a96c1;border:1px solid rgba(135,163,201,.14);cursor:not-allowed;}
#im-v4 .im-v6-btn-locked svg{width:15px;height:15px;}
#im-v4 .im-v6-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%;gap:8px;}
#im-v4 .im-v6-actions>*:first-child:nth-last-child(1){grid-column:1/-1;}
#im-v4 .im-v6-session-summary{display:flex;flex-direction:column;align-items:center;gap:5px;margin-bottom:12px;font-size:12px;color:#7893b9;}
#im-v4 .im-v6-state{font-weight:750;color:#c28c15;}
#im-v4 .im-v6-state[data-status="READY"],#im-v4 .im-v6-state[data-status="RUNNING"]{color:#149b72;}
#im-v4 .im-v6-state[data-status="FAILED"],#im-v4 .im-v6-state[data-status="DELETING"]{color:#df5367;}
#im-v4 .im-v6-error{margin:0 0 20px;border:1px solid #f5b0b9;border-radius:12px;padding:14px;background:rgba(255,220,225,.55);color:#a72e45;}
#im-v4 .im-v6-detail{margin-top:22px;padding:24px;border:1px solid rgba(255,255,255,.75);border-radius:16px;background:rgba(255,255,255,.42);}
#im-v4 .im-v6-detail h2{font-size:20px;font-weight:750;margin:0 0 18px;color:#152756;}
#im-v4 .im-v6-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;}
#im-v4 .im-v6-detail-grid>div{display:flex;flex-direction:column;gap:6px;min-width:0;}
#im-v4 .im-v6-detail-grid small{font-size:12px;color:#8298c2;}
#im-v4 .im-v6-detail-grid strong{font-size:13px;color:#152756;overflow-wrap:anywhere;}
#im-v4 .im-v6-detail-note{font-size:11px;color:#8298c2;margin:14px 0 0;}
#im-v4[data-theme="dark"] .im-v6-course{background:rgba(34,43,71,.58);border-color:rgba(160,177,224,.12);color:#eef3ff;}
#im-v4[data-theme="dark"] .im-v6-heading h1,#im-v4[data-theme="dark"] .im-v6-detail h2,#im-v4[data-theme="dark"] .im-v6-detail-grid strong{color:#eef3ff;}
#im-v4[data-theme="dark"] .im-v6-quota{background:rgba(36,45,72,.76);border-color:rgba(170,188,229,.12);color:#eef3ff;}
#im-v4[data-theme="dark"] .im-v6-heading-icon,#im-v4[data-theme="dark"] .im-v6-course-icon{background:rgba(64,85,124,.32);}
#im-v4[data-theme="dark"] .im-v6-difficulty,#im-v4[data-theme="dark"] .im-v6-btn-locked{background:rgba(61,77,112,.35);color:#b0c4e3;}
#im-v4[data-theme="dark"] .im-v6-detail{background:rgba(34,43,71,.58);border-color:rgba(160,177,224,.12);}
@media(max-width:900px){#im-v4 .im-v6-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:650px){#im-v4 .im-v5-content{padding:20px 16px 90px!important;}#im-v4 .im-v6-heading{align-items:flex-start;}#im-v4 .im-v6-heading h1{font-size:23px;}#im-v4 .im-v6-heading-icon{width:45px;height:45px;}#im-v4 .im-v6-courses{grid-template-columns:1fr;gap:14px;}#im-v4 .im-v6-course{min-height:325px;}#im-v4 .im-v6-quota{font-size:16px;padding:8px 10px;}#im-v4 .im-v6-detail-grid{grid-template-columns:1fr 1fr;}}

</style>
<link rel="stylesheet" href="{{ asset('imnehnet-create-lab-v1-1.css') }}?v=1.1">
@if ($assetJs)
<script type="module" src="{{ asset('imnehnet-v4-assets/assets/' . basename($assetJs)) }}?v={{ filemtime($assetJs) }}"></script>
@endif
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    <aside class="im-v4-sidebar" aria-label="Student sidebar">
        <a class="im-v4-brand" href="{{ url('/student') }}"><span class="im-v4-logo">{!! $svg('box') !!}</span><span><strong>IMNEHNET</strong><small>Lab-as-a-Service</small></span></a>
        <nav class="im-v4-menu menu" aria-label="Main navigation">
            @foreach($links as $i => $link)
                <a class="im-v4-nav-link {{ $i === 1 ? 'is-active' : '' }}" href="{{ $link[1] }}">{!! $svg($link[2]) !!}<span>{{ $link[0] }}</span></a>
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
                <a class="btn btn-square im-v4-icon-btn" href="{{ url('/student/my-labs') }}" aria-label="My Labs">{!! $svg('bell') !!}</a>
                <div class="im-v4-user-avatar" aria-hidden="true">{{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}</div>
                <div class="im-v4-user"><strong>{{ $user?->name ?? 'Student' }}</strong><small>User</small></div>
            </div>
        </header>
        <main class="im-v4-content im-v5-content" id="im-v4-main" wire:poll.5s>
            {{-- IMNEHNET CREATE LAB V1.1 --}}
            @if ($session && in_array($session->status, [\App\Enums\LabSessionStatus::Creating, \App\Enums\LabSessionStatus::Provisioning, \App\Enums\LabSessionStatus::Configuring, \App\Enums\LabSessionStatus::Failed], true))
                @include('filament.pages.partials.create-lab-progress')
            @elseif ($session && in_array($session->status, [\App\Enums\LabSessionStatus::Ready, \App\Enums\LabSessionStatus::Running, \App\Enums\LabSessionStatus::Stopped, \App\Enums\LabSessionStatus::Completed], true))
                @include('filament.pages.partials.my-labs-active')
            @else
            <section class="im-v6-heading">
                <div class="im-v6-heading-title"><span class="im-v6-heading-icon">{!! $svg('server') !!}</span><div><h1>My Labs</h1><p>Pilih course untuk membuat lab environment baru.</p></div></div>
                <div class="im-v6-quota">{{ $used }} / 1</div>
            </section>
            @if ($session?->last_error)
                <div class="im-v6-error" role="alert"><strong>Operasi lab membutuhkan perhatian</strong><p>{{ $session->last_error }}</p></div>
            @endif
            <section class="im-v6-courses" aria-label="Course selection">
                <article class="im-v6-course">
                    <div class="im-v6-course-icon im-v6-primary-icon">{!! $svg('server') !!}</div>
                    <h2>Web Server Breach</h2>
                    <p class="im-v6-desc">Network discovery, service reconnaissance, web exploitation.</p>
                    <p class="im-v6-player-note">{!! $svg('activity') !!} Guided CTF · Isolated environment</p>
                    <div class="im-v6-spacer"></div>
                    <div class="im-v6-meta"><span>♨ {{ $progress['total'] }} Objectives</span><span class="im-v6-difficulty">Beginner</span></div>
                    @if ($session)
                        <div class="im-v6-session-summary"><span class="im-v6-state" data-status="{{ $status }}">{{ ucwords(strtolower(str_replace('_', ' ', $status))) }}</span><span>Session #{{ $session->id }} · {{ $progress['completed'] }}/{{ $progress['total'] }} completed</span></div>
                        <div class="im-v6-actions">
                            @if ($session->status->allowsTerminal())<button type="button" class="im-v6-btn im-v6-btn-primary" wire:click="openWorkspace" wire:loading.attr="disabled" wire:target="openWorkspace">Open Workspace</button>@endif
                            @if (in_array($session->status, [\App\Enums\LabSessionStatus::Ready, \App\Enums\LabSessionStatus::Running], true))<button type="button" class="im-v6-btn im-v6-btn-muted" wire:click="requestStop">Stop Lab</button>@endif
                            @if ($session->status === \App\Enums\LabSessionStatus::Stopped)<button type="button" class="im-v6-btn im-v6-btn-primary" wire:click="resumeLab" wire:loading.attr="disabled" wire:target="resumeLab">Resume Lab</button>@endif
                            @if (in_array($session->status, [\App\Enums\LabSessionStatus::Ready, \App\Enums\LabSessionStatus::Running, \App\Enums\LabSessionStatus::Completed], true))<button type="button" class="im-v6-btn im-v6-btn-muted" wire:click="requestReset">Reset Lab</button>@endif
                            <button type="button" class="im-v6-btn im-v6-btn-danger" wire:click="requestDelete" wire:loading.attr="disabled" wire:target="requestDelete" @disabled($session->status === \App\Enums\LabSessionStatus::Deleting)>{{ $session->status === \App\Enums\LabSessionStatus::Deleting ? 'Deleting…' : 'Delete Lab' }}</button>
                        </div>
                    @elseif ($course)
                        <button
    type="button"
    class="im-v6-btn im-v6-btn-primary im-v6-start"
    wire:click="startLab"
    wire:loading.attr="disabled"
    wire:target="startLab"
    aria-label="Start Lab"
>
    <span wire:loading.remove wire:target="startLab">Start Lab</span>
    <span wire:loading.inline-flex wire:target="startLab"
          style="align-items:center;justify-content:center;gap:10px;">
        <svg width="17" height="17" viewBox="0 0 24 24"
             fill="none" aria-hidden="true"
             style="animation:im-start-spin .8s linear infinite;">
            <circle cx="12" cy="12" r="9"
                    stroke="currentColor" stroke-width="3"
                    opacity=".25"/>
            <path d="M21 12a9 9 0 0 0-9-9"
                  stroke="currentColor" stroke-width="3"
                  stroke-linecap="round"/>
        </svg>
        <span>Memproses permintaan...</span>
    </span>
</button>
                    @else
                        <div class="im-v6-unavailable">Course unavailable</div>
                    @endif
                </article>
                @foreach ([
                    ['Reverse Engineering', 'Analisis binary, reverse engineering techniques.', 'Coming Soon'],
                    ['Pwn Challenge', 'Memory corruption, buffer overflow, dan binary exploitation.', 'Coming Soon'],
                    ['Cryptography', 'Klasik & modern cryptography challenges.', 'Coming Soon'],
                    ['Forensics', 'Analisis artefak digital, investigasi file, dan network forensics.', 'Coming Soon'],
                    ['OSINT', 'Penelusuran sumber terbuka, pengumpulan informasi, dan investigasi digital.', 'Coming Soon'],
                    ['Miscellaneous (Misc)', 'Tantangan beragam: scripting, encoding, dan pemecahan puzzle.', 'Coming Soon'],
                ] as $locked)
                    <article class="im-v6-course im-v6-locked">
                        <div class="im-v6-course-icon">{!! $svg('lock') !!}</div>
                        <h2>{{ $locked[0] }}</h2>
                        <p class="im-v6-desc">{{ $locked[1] }}</p>
                        <p class="im-v6-player-note">Coming soon · Not available yet</p>
                        <div class="im-v6-spacer"></div>
                        <div class="im-v6-meta"><span>Objectives unavailable</span><span class="im-v6-difficulty">{{ $locked[2] }}</span></div>
                        <div class="im-v6-btn im-v6-btn-locked" aria-disabled="true">{!! $svg('lock') !!} Locked</div>
                    </article>
                @endforeach
            </section>
            @if ($session)
                <section class="im-v6-detail" aria-label="Your lab information">
                    <h2>Your Lab Information</h2>
                    <div class="im-v6-detail-grid">
                        <div><small>Player</small><strong>{{ $user?->name ?? 'Unknown' }}</strong></div>
                        <div><small>Session ID</small><strong>#{{ $session->id }}</strong></div>
                        <div><small>Lab Status</small><strong>{{ $status }}</strong></div>
                        <div><small>Started At</small><strong>{{ $session->started_at?->format('d M Y, H:i') ?? '—' }}</strong></div>
                        <div><small>Attacker / LAB-ATK</small><strong>{{ $session->attacker_container ?: 'Not assigned' }}</strong></div>
                        <div><small>Victim / LAB-ENV</small><strong>{{ $session->victim_container ?: 'Not assigned' }}</strong></div>
                        <div><small>Objective Progress</small><strong>{{ $progress['completed'] }} / {{ $progress['total'] }}</strong></div>
                        <div><small>Elapsed Since Start</small><strong>{{ $duration }}</strong></div>
                    </div>
                    <p class="im-v6-detail-note">Elapsed time berasal dari timestamp sesi, bukan waktu aktif container.</p>
                </section>
            @endif
            @endif
        </main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">@foreach($links as $i => $link)<a href="{{ $link[1] }}" class="{{ $i === 1 ? 'is-active' : '' }}">{!! $svg($link[2]) !!}<span>{{ $i === 0 ? 'Home' : ($i === 4 ? 'Docs' : $link[0]) }}</span></a>@endforeach</nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    @if ($showDeleteConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="delete-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <div class="mb-4 text-rose-400">
                    {!! \App\Support\Lucide::html('triangle-alert') !!}
                </div>
                <h2 id="delete-lab-title" class="text-xl font-bold">Delete Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Kedua container (victim dan attacker) akan dihapus permanen.
                    Riwayat objective tetap tersimpan. Kuota baru tersedia setelah
                    penghapusan infrastruktur berhasil diverifikasi.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDelete"
                            class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmDelete" wire:loading.class="opacity-50 cursor-wait" wire:target="confirmDelete"
                            wire:loading.attr="disabled" wire:target="confirmDelete"
                            class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950 hover:bg-gray-200">
                        <span wire:loading.remove wire:target="confirmDelete">Confirm Delete</span><span wire:loading.inline wire:target="confirmDelete">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showStopConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="stop-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <h2 id="stop-lab-title" class="text-xl font-bold">Stop Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Akses jaringan lab akan dinonaktifkan sementara.
                    Kedua container dihentikan dan progres objective dipertahankan.
                    Gunakan Resume untuk melanjutkan.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelStop"
                        class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="stopLab" wire:loading.class="opacity-50 cursor-wait" wire:target="stopLab"
                        wire:loading.attr="disabled" wire:target="stopLab"
                        class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950">
                        <span wire:loading.remove wire:target="stopLab">Confirm Stop</span><span wire:loading.inline wire:target="stopLab">Stopping...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showResetConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="reset-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <h2 id="reset-lab-title" class="text-xl font-bold">Reset Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Skenario akan dipulihkan dan progres objective kembali menjadi 0/6.
                    Evidence penyelesaian sebelumnya akan dibersihkan.
                    Kedua container tidak dihapus oleh aksi ini.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelReset"
                        class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmReset" wire:loading.class="opacity-50 cursor-wait" wire:target="confirmReset"
                        wire:loading.attr="disabled" wire:target="confirmReset"
                        class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950">
                        <span wire:loading.remove wire:target="confirmReset">Confirm Reset</span><span wire:loading.inline wire:target="confirmReset">Resetting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    
</div>
</x-filament-panels::page>
