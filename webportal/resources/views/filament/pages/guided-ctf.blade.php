<x-filament-panels::page>
<style>
/* IMNEHNET_PROGRESS_PENTEST_V1 */
.pc-progress,.pc-pentest,.pc-completed{font-family:inherit;color:#172b60;box-sizing:border-box}.pc-progress *,.pc-pentest *,.pc-completed *{box-sizing:border-box}.pc-progress-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}.pc-progress h2{font-size:22px;font-weight:800;margin:0}.pc-progress p,.pc-progress small{color:#7c94be}.pc-progress p{font-size:14px;margin:5px 0 0}.pc-counters{display:flex;gap:24px;text-align:center}.pc-counters span{font-size:11px;color:#8399bf;display:grid;gap:5px}.pc-counters strong{font-size:25px;color:#008eff}.pc-counters span:first-child strong{color:#05b99e}.pc-counters span:last-child strong{color:#8095b9}.pc-progress-bar{margin-top:19px;background:#dce5f5;height:11px;border-radius:50px;overflow:hidden}.pc-progress-bar div{height:100%;background:#0b94fa;border-radius:50px;transition:width .3s}.pc-progress-percent{text-align:right;margin:5px 0 12px;font-size:13px}.pc-progress-list{display:grid;gap:7px}.pc-progress-item{background:rgba(255,255,255,.52);border:1px solid rgba(255,255,255,.8);border-radius:11px;min-height:60px;display:flex;align-items:center;gap:13px;padding:12px 15px}.pc-progress-item.is-current{background:rgba(227,242,255,.64);border-color:#add7ff}.pc-progress-icon{flex-shrink:0;width:31px;height:31px;border-radius:50%;background:#e2f8f3;color:#02b998;display:grid;place-items:center}.pc-progress-item.is-current .pc-progress-icon{background:#d4edff;color:#008eff}.pc-progress-item>div{min-width:0;flex:1}.pc-progress-item strong{font-size:13px;display:block}.pc-progress-item small{display:block;font-size:11px;margin-top:3px}.pc-progress-tag{font-size:11px;background:#e3f7ef;color:#00a985;padding:5px 9px;border-radius:6px;white-space:nowrap}.pc-progress-item.is-current .pc-progress-tag{background:#deeeff;color:#008eff}.pc-progress-item:not(.is-current):has(.pc-progress-icon:not(:first-child)){}.pc-progress-next{display:flex;align-items:center;gap:13px;padding:16px;background:rgba(236,247,255,.64);border-radius:12px;margin-top:17px}.pc-progress-next>span{font-size:23px;color:#008eff}.pc-progress-next>div{flex:1;min-width:0}.pc-progress-next strong,.pc-progress-next small{display:block}.pc-progress-next strong{font-size:13px}.pc-progress-next small{font-size:11px;margin-top:4px}.pc-progress-next button,.pc-primary{background:#078fff;color:#fff;border:0;border-radius:8px;padding:12px 17px;font-size:13px;font-weight:700;cursor:pointer}.pc-progress-next button:disabled,.pc-primary:disabled{opacity:.45;cursor:wait}
.pc-pentest{width:100%;min-width:0}.pc-pentest-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:0 0 18px}.pc-crumb{font-size:13px;color:#7890bd}.pc-state{font-size:12px;color:#03a88a}.pc-state a{margin-left:12px;color:#008eff}.pc-columns{display:grid;grid-template-columns:minmax(185px,1fr) minmax(230px,1.35fr) minmax(340px,2fr);gap:13px;align-items:stretch}.pc-glass{min-width:0;background:rgba(255,255,255,.54);border:1px solid rgba(255,255,255,.78);border-radius:16px;padding:19px;backdrop-filter:blur(12px)}.pc-objectives h3,.pc-glass h3{font-size:16px;font-weight:750;margin:0 0 17px}.pc-objective{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.52);border:1px solid rgba(160,188,229,.16);border-radius:10px;padding:13px 10px;margin-bottom:10px;font-size:12px;min-height:66px}.pc-objective>span:nth-child(2){flex:1;min-width:0}.pc-objective small{font-size:10px;color:#7d92b6}.pc-objective .pc-mark{color:#8ba4c8}.pc-objective.is-done .pc-mark{color:#02b89c}.pc-objective.is-current{border-color:#a7d5ff;background:#edf6ff}.pc-objective.is-current .pc-mark{color:#008eff}.pc-detail>small{color:#7b93bf}.pc-detail h2{font-size:22px;line-height:1.25;margin:14px 0;color:#172b60}.pc-detail p{font-size:13px;line-height:1.65;color:#7890b9}.pc-detail h4{font-size:14px;margin:22px 0 8px}.pc-tabs{display:flex;justify-content:space-between;gap:2px;border-bottom:1px solid #dce5f4;margin:23px 0}.pc-tabs button{flex:1;background:none;border:0;border-bottom:3px solid transparent;color:#8297bc;padding:10px 4px;cursor:pointer;font-size:12px}.pc-tabs button.selected{border-color:#0096ff;color:#008fff;font-weight:700}.pc-target{background:rgba(255,255,255,.62);border-radius:10px;padding:14px;color:#6482b6;font-size:13px}.pc-target strong{display:block;margin-top:5px}.pc-hint{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;background:#e7effb;padding:14px;border-radius:8px}.pc-flag-row{display:flex;gap:9px;margin-top:12px}.pc-flag-row input{width:100%;min-width:0;border:1px solid #d4e4f7;border-radius:8px;padding:11px;background:rgba(255,255,255,.7);color:#172b60}.pc-terminal{display:flex;flex-direction:column}.pc-terminal-title{text-align:center;font-size:13px;font-weight:750;border-bottom:3px solid #008eff;padding:0 0 14px}.pc-terminal-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;font-size:11px;color:#8aa1c6}.pc-terminal-meta button{background:rgba(255,255,255,.6);border:0;border-radius:6px;padding:6px 9px;color:#4e76b0;cursor:pointer}.pc-frame{flex:1;min-height:480px;overflow:hidden;border-radius:13px;background:#263243}.pc-frame iframe{display:block;width:100%;height:100%;min-height:480px;border:0}.pc-terminal-wait{display:grid;place-items:center;min-height:480px;background:#263243;color:#fff;padding:20px;text-align:center;border-radius:13px;font-size:13px}.pc-terminal:fullscreen{background:#111827;border-radius:0;padding:16px;width:100vw;height:100vh}.pc-terminal:fullscreen .pc-frame{min-height:0}.pc-terminal:fullscreen .pc-frame iframe{min-height:0;height:100%}.pc-completed{max-width:1020px;margin:0 auto;padding:30px 0;text-align:center}.pc-check{margin:0 auto 15px;display:grid;place-items:center;width:80px;height:80px;border-radius:50%;background:#e0f8f3;color:#05b99b;font-size:48px}.pc-completed h1{font-size:36px;margin:0}.pc-completed>p{font-size:17px;line-height:1.6;color:#8096be}.pc-complete-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;text-align:left;margin:42px 0 22px}.pc-complete-row{display:flex;gap:12px;align-items:center;margin:18px 0;font-size:14px;color:#6383b7}.pc-complete-row span{color:#05b99b}.pc-details{display:grid;grid-template-columns:1fr 1fr;gap:16px 12px;font-size:13px}.pc-details dt{color:#8096be}.pc-details dd{text-align:right;margin:0;overflow-wrap:anywhere}.pc-complete-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.pc-complete-actions>a,.pc-complete-actions>button:not(.pc-primary){background:rgba(255,255,255,.55);border:1px solid #dce8fa;border-radius:8px;padding:12px;text-align:center;color:#35578c;font-size:13px}
@media(max-width:1250px){.pc-columns{grid-template-columns:minmax(175px,.8fr) minmax(220px,1.2fr)}.pc-terminal{grid-column:1/-1}.pc-frame,.pc-frame iframe{min-height:380px}}@media(max-width:760px){.pc-columns{grid-template-columns:minmax(0,1fr)}.pc-terminal{grid-column:auto}.pc-progress-head{flex-direction:column}.pc-counters{width:100%;justify-content:space-around}.pc-progress-next{flex-wrap:wrap}.pc-progress-next button{width:100%}.pc-progress-item{gap:8px;padding:10px}.pc-progress-tag{font-size:9px;padding:4px}.pc-complete-grid{grid-template-columns:1fr}.pc-complete-actions{grid-template-columns:1fr}.pc-completed h1{font-size:27px}.pc-pentest-head{flex-wrap:wrap}.pc-frame,.pc-frame iframe{min-height:340px}}

.imnehnet-ctf > .flex.flex-wrap.items-center.justify-between{display:none!important}

</style>
{{--
    The guided lab: a course card before the lab exists, the workspace after.

    Follows the supplied design: objectives on the left with their state, the objective in
    play spelled out, real terminals on the right, the target and the session controls
    along the bottom.

    Two things are deliberately not here. The target's service list, because the student
    is meant to find it. And the evidence value of any locked objective, because
    Objective::toPublicArray withholds it and this view is given only what that returns.

    The terminal reuses the existing TerminalService for its embedded session.
    Previously the terminal was a link rather than an embed. The terminal page already owns opening a
    session, resolving the shell uid and rendering the frame; reproducing that here would
    be a second copy of it, and two copies of terminal handling are two places for them to
    disagree about who a session belongs to.
--}}
@php
    $course = $this->course();
    $session = $this->session();
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


    $course = $this->course();
    $session = $this->session();
    $objectives = $this->objectiveList();
    $progress = $session?->progressCount() ?? ['completed' => 0, 'total' => 0];
    $percent = $progress['total'] > 0 ? (int) round($progress['completed'] / $progress['total'] * 100) : 0;
    $active = $objectives->firstWhere('status', \App\Enums\ObjectiveStatus::Active);
    $selected = $objectives->first(fn ($row) => (int) $row['objective']->sequence === (int) $this->viewObjective && in_array($row['status'], [\App\Enums\ObjectiveStatus::Active, \App\Enums\ObjectiveStatus::Completed], true));
    $displayObjective = $selected ?: $active;
    $attacker = $this->attackerEnvironment();
    $victim = $this->victimEnvironment();
    $finished = $session !== null && $session->status === \App\Enums\LabSessionStatus::Completed;
    $building = $session !== null && in_array(
        $session->status,
        [\App\Enums\LabSessionStatus::Creating, \App\Enums\LabSessionStatus::Provisioning, \App\Enums\LabSessionStatus::Configuring, \App\Enums\LabSessionStatus::Resetting],
        true,
    );
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
<style>

/* IMNEHNET_PENTEST_V12_UI */
#im-v4 .pc-objective-button{width:100%;font:inherit;text-align:left;color:inherit;cursor:pointer}
#im-v4 .pc-objective-button:hover{border-color:#70baff;background:rgba(230,244,255,.85)}
#im-v4 .pc-objective-button.is-selected{border-color:#229dff;box-shadow:inset 3px 0 0 #229dff}
#im-v4 .pc-objective-button:focus-visible{outline:2px solid #078fff;outline-offset:2px}
#im-v4 .pc-hint-toggle{border:0;border-radius:7px;padding:9px 13px;background:#e2f1ff;color:#087bd4;font-weight:700;cursor:pointer;margin:8px 0}
#im-v4 .pc-hint-card{min-width:0}
#im-v4 .pc-hint{overflow-x:auto;white-space:pre-wrap;word-break:break-word}


/* IMNEHNET_PENTEST_V13_HINTS */
#im-v4 .pc-hint-intro{font-size:12px;line-height:1.6;color:#7991b8;margin:0 0 15px}
#im-v4 .pc-hint-stack{display:grid;gap:13px}
#im-v4 .pc-hint-step{border:1px solid rgba(145,183,231,.28);border-radius:13px;background:rgba(255,255,255,.46);padding:14px;min-width:0}
#im-v4 .pc-hint-top{display:flex;align-items:center;gap:10px;margin-bottom:11px}
#im-v4 .pc-hint-number{display:grid;place-items:center;flex:none;width:30px;height:30px;border-radius:9px;background:#e0f1ff;color:#0589e9;font-size:12px;font-weight:800}
#im-v4 .pc-hint-top strong{display:block;font-size:13px;color:#233e71}
#im-v4 .pc-hint-top small{display:block;font-size:11px;line-height:1.5;color:#8297bb;margin-top:3px}
#im-v4 .pc-hint-reveal{position:relative;max-height:70px;overflow:hidden;border-radius:9px;background:rgba(225,236,251,.66);padding:10px 12px;transition:max-height .25s ease}
#im-v4 .pc-hint-reveal:not(.is-revealed)>*{filter:blur(5px);user-select:none;pointer-events:none}
#im-v4 .pc-hint-reveal.is-revealed{max-height:600px}
#im-v4 .pc-hint-reveal p{margin:0;font-size:12px;line-height:1.65;color:#49638f}
#im-v4 .pc-hint-icon{display:inline-flex;align-items:center;gap:7px;margin-top:10px;border:1px solid rgba(22,145,242,.13);background:#e0f1ff;color:#067fce;border-radius:8px;padding:8px 11px;font-size:12px;font-weight:750;cursor:pointer}
#im-v4 .pc-hint-icon svg{width:17px;height:17px}
#im-v4 .pc-hint-icon:hover,#im-v4 .pc-copy-icon:hover{background:#c8e7ff}
#im-v4 .pc-hint-command{display:flex;align-items:center;gap:8px;margin-top:10px;border:1px solid rgba(138,173,218,.3);background:rgba(235,243,254,.86);padding:8px;border-radius:8px}
#im-v4 .pc-hint-command code{flex:1;min-width:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:11px;color:#203968}
#im-v4 .pc-copy-icon{display:grid;place-items:center;flex:none;width:32px;height:32px;border:0;border-radius:7px;background:#d8ecff;color:#067fce;cursor:pointer;transition:transform .16s,background .16s}
#im-v4 .pc-copy-icon:active{transform:scale(.85)}
#im-v4 .pc-copy-icon svg{width:18px;height:18px}
#im-v4 .pc-toast{position:fixed;right:24px;top:22px;z-index:99999;display:flex;align-items:center;gap:9px;max-width:min(360px,calc(100vw - 30px));padding:13px 16px;background:#15365f;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:11px;box-shadow:0 12px 32px rgba(14,43,85,.24);font-size:12px;font-weight:650}
#im-v4 .pc-toast svg{width:18px;height:18px;color:#5be0b0}
#im-v4[data-theme="dark"] .pc-hint-step{background:rgba(37,51,80,.8);border-color:rgba(156,181,225,.18)}
#im-v4[data-theme="dark"] .pc-hint-top strong{color:#eaf1ff}
#im-v4[data-theme="dark"] .pc-hint-reveal{background:rgba(55,73,107,.7)}
#im-v4[data-theme="dark"] .pc-hint-reveal p,#im-v4[data-theme="dark"] .pc-hint-command code{color:#d5e3fa}
#im-v4[data-theme="dark"] .pc-hint-command{background:rgba(30,43,69,.9)}
@media(max-width:760px){#im-v4 .pc-toast{top:12px;right:12px}#im-v4 .pc-hint-step{padding:12px}}


/* IMNEHNET_PENTEST_V15_MOBILE_FULLSCREEN */
#im-v4 .pc-terminal-expanded{position:fixed!important;inset:0!important;width:100vw!important;height:100dvh!important;z-index:999999!important;border-radius:0!important;padding:12px!important;background:#121c2c!important;display:flex!important;flex-direction:column!important}
#im-v4 .pc-terminal-expanded .pc-frame,#im-v4 .pc-terminal-expanded .pc-frame iframe{flex:1!important;min-height:0!important;height:100%!important}
#im-v4 .pc-terminal:fullscreen,#im-v4 .pc-terminal:-webkit-full-screen{display:flex!important;flex-direction:column!important;width:100vw!important;height:100dvh!important;background:#121c2c!important}
#im-v4 .pc-terminal:fullscreen .pc-frame,#im-v4 .pc-terminal:fullscreen .pc-frame iframe{flex:1!important;min-height:0!important;height:100%!important}
@media(max-width:760px){#im-v4 .pc-terminal-expanded{padding:7px!important}#im-v4 .pc-terminal-expanded .pc-terminal-meta{padding:5px 0!important}}

/* IMNEHNET_STUDENT_PENTEST_SHELL_V2 */
#im-v4 .im-student-pentest-content{width:100%;max-width:none;min-width:0;overflow-x:hidden;overflow-y:auto;padding:22px 26px 70px!important}
#im-v4 .im-student-pentest{width:100%;max-width:none;min-width:0}
#im-v4 .pc-columns{grid-template-columns:minmax(180px,1fr) minmax(230px,1.3fr) minmax(340px,2fr)}
#im-v4 .pc-pentest{width:100%}
#im-v4 .pc-glass{min-width:0}
#im-v4 .pc-terminal{min-height:610px}
#im-v4 .pc-frame,#im-v4 .pc-frame iframe{min-height:490px}
#im-v4 .pc-completed{max-width:1020px;margin-inline:auto}
#im-v4 .pc-glass,#im-v4 .pc-objective{box-shadow:none}
#im-v4[data-theme="dark"] .pc-glass{background:rgba(32,42,67,.78);border-color:rgba(180,197,235,.12)}
#im-v4[data-theme="dark"] .pc-pentest,#im-v4[data-theme="dark"] .pc-completed,#im-v4[data-theme="dark"] .pc-detail h2{color:#f1f5ff}
#im-v4[data-theme="dark"] .pc-objective{background:rgba(35,47,76,.78)}
@media(max-width:1300px){#im-v4 .pc-columns{grid-template-columns:minmax(185px,1fr) minmax(260px,1.45fr)}#im-v4 .pc-terminal{grid-column:1/-1;min-height:460px}}
@media(max-width:760px){#im-v4 .im-student-pentest-content{padding:18px 14px 105px!important}#im-v4 .pc-columns{grid-template-columns:minmax(0,1fr)}#im-v4 .pc-terminal{grid-column:auto;min-height:390px}#im-v4 .pc-frame,#im-v4 .pc-frame iframe{min-height:350px}#im-v4 .pc-complete-grid{grid-template-columns:1fr}#im-v4 .pc-complete-actions{grid-template-columns:1fr}#im-v4 .pc-pentest-head{flex-wrap:wrap}}
</style>
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    <aside class="im-v4-sidebar" aria-label="Student sidebar">
        <a class="im-v4-brand" href="{{ url('/student') }}"><span class="im-v4-logo">{!! $svg('box') !!}</span><span><strong>IMNEHNET</strong><small>Lab-as-a-Service</small></span></a>
        <nav class="im-v4-menu menu" aria-label="Main navigation">
            @foreach($links as $i => $link)
                <a class="im-v4-nav-link {{ $i === 2 ? 'is-active' : '' }}" href="{{ $link[1] }}">{!! $svg($link[2]) !!}<span>{{ $link[0] }}</span></a>
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
        <main class="im-v4-content im-v5-content im-student-pentest-content" id="im-v4-main">
            <div class="im-student-pentest" wire:init="pollWorkspace" wire:poll.5s="pollWorkspace">
                @if ($course === null)
                    <section class="pc-glass"><h2>Course belum tersedia</h2><p>Hubungi administrator.</p></section>
                @elseif ($session === null)
                    <section class="pc-glass"><h2>{{ $course->title }}</h2><p>{{ $course->summary }}</p><a class="pc-primary" href="{{ url('/student/my-labs') }}">Buka My Labs untuk membuat lab</a></section>
                @elseif ($building)
                    <section class="pc-glass"><h2>{{ $this->headline($session) }}</h2><p>Environment sedang diproses. Pantau status melalui My Labs.</p><a class="pc-primary" href="{{ url('/student/my-labs') }}">Lihat Status Lab</a></section>
                @elseif ($session->status === \App\Enums\LabSessionStatus::Failed)
                    <section class="pc-glass"><h2>Operasi lab gagal</h2><p>Periksa log provisioning di My Labs.</p><a class="pc-primary" href="{{ url('/student/my-labs') }}">Buka My Labs</a></section>
                @else
                    @include('filament.pages.partials.imnehnet-pentest-workspace')
                @endif
            </div>
            {{-- Lab Process hanya ditampilkan pada alur pembuatan lab di My Labs. --}}
        </main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">@foreach($links as $i => $link)<a href="{{ $link[1] }}" class="{{ $i === 2 ? 'is-active' : '' }}">{!! $svg($link[2]) !!}<span>{{ $i === 0 ? 'Home' : ($i === 4 ? 'Docs' : $link[0]) }}</span></a>@endforeach</nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
  </div>
</x-filament-panels::page>
