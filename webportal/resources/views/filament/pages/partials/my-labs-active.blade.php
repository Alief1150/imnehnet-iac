<style>
/* IMNEHNET_PROGRESS_PENTEST_V1 */
.pc-progress,.pc-pentest,.pc-completed{font-family:inherit;color:#172b60;box-sizing:border-box}.pc-progress *,.pc-pentest *,.pc-completed *{box-sizing:border-box}.pc-progress-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}.pc-progress h2{font-size:22px;font-weight:800;margin:0}.pc-progress p,.pc-progress small{color:#7c94be}.pc-progress p{font-size:14px;margin:5px 0 0}.pc-counters{display:flex;gap:24px;text-align:center}.pc-counters span{font-size:11px;color:#8399bf;display:grid;gap:5px}.pc-counters strong{font-size:25px;color:#008eff}.pc-counters span:first-child strong{color:#05b99e}.pc-counters span:last-child strong{color:#8095b9}.pc-progress-bar{margin-top:19px;background:#dce5f5;height:11px;border-radius:50px;overflow:hidden}.pc-progress-bar div{height:100%;background:#0b94fa;border-radius:50px;transition:width .3s}.pc-progress-percent{text-align:right;margin:5px 0 12px;font-size:13px}.pc-progress-list{display:grid;gap:7px}.pc-progress-item{background:rgba(255,255,255,.52);border:1px solid rgba(255,255,255,.8);border-radius:11px;min-height:60px;display:flex;align-items:center;gap:13px;padding:12px 15px}.pc-progress-item.is-current{background:rgba(227,242,255,.64);border-color:#add7ff}.pc-progress-icon{flex-shrink:0;width:31px;height:31px;border-radius:50%;background:#e2f8f3;color:#02b998;display:grid;place-items:center}.pc-progress-item.is-current .pc-progress-icon{background:#d4edff;color:#008eff}.pc-progress-item>div{min-width:0;flex:1}.pc-progress-item strong{font-size:13px;display:block}.pc-progress-item small{display:block;font-size:11px;margin-top:3px}.pc-progress-tag{font-size:11px;background:#e3f7ef;color:#00a985;padding:5px 9px;border-radius:6px;white-space:nowrap}.pc-progress-item.is-current .pc-progress-tag{background:#deeeff;color:#008eff}.pc-progress-item:not(.is-current):has(.pc-progress-icon:not(:first-child)){}.pc-progress-next{display:flex;align-items:center;gap:13px;padding:16px;background:rgba(236,247,255,.64);border-radius:12px;margin-top:17px}.pc-progress-next>span{font-size:23px;color:#008eff}.pc-progress-next>div{flex:1;min-width:0}.pc-progress-next strong,.pc-progress-next small{display:block}.pc-progress-next strong{font-size:13px}.pc-progress-next small{font-size:11px;margin-top:4px}.pc-progress-next button,.pc-primary{background:#078fff;color:#fff;border:0;border-radius:8px;padding:12px 17px;font-size:13px;font-weight:700;cursor:pointer}.pc-progress-next button:disabled,.pc-primary:disabled{opacity:.45;cursor:wait}
.pc-pentest{width:100%;min-width:0}.pc-pentest-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:0 0 18px}.pc-crumb{font-size:13px;color:#7890bd}.pc-state{font-size:12px;color:#03a88a}.pc-state a{margin-left:12px;color:#008eff}.pc-columns{display:grid;grid-template-columns:minmax(185px,1fr) minmax(230px,1.35fr) minmax(340px,2fr);gap:13px;align-items:stretch}.pc-glass{min-width:0;background:rgba(255,255,255,.54);border:1px solid rgba(255,255,255,.78);border-radius:16px;padding:19px;backdrop-filter:blur(12px)}.pc-objectives h3,.pc-glass h3{font-size:16px;font-weight:750;margin:0 0 17px}.pc-objective{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.52);border:1px solid rgba(160,188,229,.16);border-radius:10px;padding:13px 10px;margin-bottom:10px;font-size:12px;min-height:66px}.pc-objective>span:nth-child(2){flex:1;min-width:0}.pc-objective small{font-size:10px;color:#7d92b6}.pc-objective .pc-mark{color:#8ba4c8}.pc-objective.is-done .pc-mark{color:#02b89c}.pc-objective.is-current{border-color:#a7d5ff;background:#edf6ff}.pc-objective.is-current .pc-mark{color:#008eff}.pc-detail>small{color:#7b93bf}.pc-detail h2{font-size:22px;line-height:1.25;margin:14px 0;color:#172b60}.pc-detail p{font-size:13px;line-height:1.65;color:#7890b9}.pc-detail h4{font-size:14px;margin:22px 0 8px}.pc-tabs{display:flex;justify-content:space-between;gap:2px;border-bottom:1px solid #dce5f4;margin:23px 0}.pc-tabs button{flex:1;background:none;border:0;border-bottom:3px solid transparent;color:#8297bc;padding:10px 4px;cursor:pointer;font-size:12px}.pc-tabs button.selected{border-color:#0096ff;color:#008fff;font-weight:700}.pc-target{background:rgba(255,255,255,.62);border-radius:10px;padding:14px;color:#6482b6;font-size:13px}.pc-target strong{display:block;margin-top:5px}.pc-hint{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;background:#e7effb;padding:14px;border-radius:8px}.pc-flag-row{display:flex;gap:9px;margin-top:12px}.pc-flag-row input{width:100%;min-width:0;border:1px solid #d4e4f7;border-radius:8px;padding:11px;background:rgba(255,255,255,.7);color:#172b60}.pc-terminal{display:flex;flex-direction:column}.pc-terminal-title{text-align:center;font-size:13px;font-weight:750;border-bottom:3px solid #008eff;padding:0 0 14px}.pc-terminal-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;font-size:11px;color:#8aa1c6}.pc-terminal-meta button{background:rgba(255,255,255,.6);border:0;border-radius:6px;padding:6px 9px;color:#4e76b0;cursor:pointer}.pc-frame{flex:1;min-height:480px;overflow:hidden;border-radius:13px;background:#263243}.pc-frame iframe{display:block;width:100%;height:100%;min-height:480px;border:0}.pc-terminal-wait{display:grid;place-items:center;min-height:480px;background:#263243;color:#fff;padding:20px;text-align:center;border-radius:13px;font-size:13px}.pc-terminal:fullscreen{background:#111827;border-radius:0;padding:16px;width:100vw;height:100vh}.pc-terminal:fullscreen .pc-frame{min-height:0}.pc-terminal:fullscreen .pc-frame iframe{min-height:0;height:100%}.pc-completed{max-width:1020px;margin:0 auto;padding:30px 0;text-align:center}.pc-check{margin:0 auto 15px;display:grid;place-items:center;width:80px;height:80px;border-radius:50%;background:#e0f8f3;color:#05b99b;font-size:48px}.pc-completed h1{font-size:36px;margin:0}.pc-completed>p{font-size:17px;line-height:1.6;color:#8096be}.pc-complete-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;text-align:left;margin:42px 0 22px}.pc-complete-row{display:flex;gap:12px;align-items:center;margin:18px 0;font-size:14px;color:#6383b7}.pc-complete-row span{color:#05b99b}.pc-details{display:grid;grid-template-columns:1fr 1fr;gap:16px 12px;font-size:13px}.pc-details dt{color:#8096be}.pc-details dd{text-align:right;margin:0;overflow-wrap:anywhere}.pc-complete-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.pc-complete-actions>a,.pc-complete-actions>button:not(.pc-primary){background:rgba(255,255,255,.55);border:1px solid #dce8fa;border-radius:8px;padding:12px;text-align:center;color:#35578c;font-size:13px}
@media(max-width:1250px){.pc-columns{grid-template-columns:minmax(175px,.8fr) minmax(220px,1.2fr)}.pc-terminal{grid-column:1/-1}.pc-frame,.pc-frame iframe{min-height:380px}}@media(max-width:760px){.pc-columns{grid-template-columns:minmax(0,1fr)}.pc-terminal{grid-column:auto}.pc-progress-head{flex-direction:column}.pc-counters{width:100%;justify-content:space-around}.pc-progress-next{flex-wrap:wrap}.pc-progress-next button{width:100%}.pc-progress-item{gap:8px;padding:10px}.pc-progress-tag{font-size:9px;padding:4px}.pc-complete-grid{grid-template-columns:1fr}.pc-complete-actions{grid-template-columns:1fr}.pc-completed h1{font-size:27px}.pc-pentest-head{flex-wrap:wrap}.pc-frame,.pc-frame iframe{min-height:340px}}

.imnehnet-ctf > .flex.flex-wrap.items-center.justify-between{display:none!important}

</style>

<style>
/* IMNEHNET_FULLSCREEN_FIX_V3 */

#im-v4 .la-terminal-frame {
    min-width: 0;
    overflow: hidden;
    border-radius: 8px;
}

#im-v4 .la-terminal-frame iframe {
    display: block;
    width: 100%;
    height: 400px;
    border: 0;
}

#im-v4 .la-terminal-card:fullscreen {
    box-sizing: border-box;
    display: flex !important;
    flex-direction: column;
    width: 100vw;
    height: 100vh;
    padding: 16px;
    margin: 0;
    border: 0;
    border-radius: 0;
    background: #111827;
    overflow: hidden;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-head {
    flex: 0 0 auto;
    margin-bottom: 12px;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-frame {
    flex: 1 1 auto;
    min-height: 0;
    height: auto;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-frame iframe {
    display: block;
    flex: 1 1 auto;
    width: 100%;
    height: 100% !important;
    min-height: 0;
    border-radius: 8px;
    background: #0b1118;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-placeholder {
    flex: 1 1 auto;
    height: auto;
    min-height: 0;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-head strong {
    color: #e9efff;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-head small,
#im-v4 .la-terminal-card:fullscreen .la-runtime {
    color: #94a6cb;
}
</style>

<style>
/* IMNEHNET_TERMINAL_TOOLBAR_V2 */
#im-v4 .la-terminal-tools {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

#im-v4 .la-terminal-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--la-border);
    border-radius: 8px;
    background: var(--la-card);
    color: var(--la-accent);
    cursor: pointer;
    transition: background .15s ease, transform .15s ease;
}

#im-v4 .la-terminal-icon-btn:hover {
    background: rgba(0, 145, 255, .12);
}

#im-v4 .la-terminal-icon-btn:active {
    transform: scale(.94);
}

#im-v4 .la-terminal-icon-btn:disabled {
    opacity: .4;
    cursor: wait;
}

#im-v4 .la-terminal-card:fullscreen {
    width: 100vw;
    height: 100vh;
    padding: 20px;
    border-radius: 0;
    background: #111827;
    display: flex;
    flex-direction: column;
}

#im-v4 .la-terminal-card:fullscreen .la-terminal-head {
    flex-shrink: 0;
}

#im-v4 .la-terminal-card:fullscreen [wire\\:ignore] {
    flex: 1;
    min-height: 0;
}

#im-v4 .la-terminal-card:fullscreen iframe {
    height: 100% !important;
}

@media (max-width: 600px) {
    #im-v4 .la-terminal-tools {
        gap: 5px;
    }

    #im-v4 .la-terminal-icon-btn {
        width: 30px;
        height: 30px;
    }
}
</style>
<style>
#im-v4 .la-terminal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}
#im-v4 .la-terminal-card{min-width:0;overflow:hidden;background:var(--la-card);border:1px solid var(--la-border);border-radius:15px;padding:14px}
#im-v4 .la-terminal-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;color:var(--la-text)}
#im-v4 .la-terminal-head small{display:block;color:var(--la-muted);margin-top:5px}
#im-v4 .la-terminal-placeholder{height:400px;background:#0b1118;color:#b4c7df;display:flex;align-items:center;justify-content:center;text-align:center;padding:16px;border-radius:8px}
@media(max-width:950px){#im-v4 .la-terminal-grid{grid-template-columns:1fr}}
</style>
<style>
#im-v4 .la-wrap{color:var(--la-text);--la-text:#172b60;--la-muted:#7489b4;--la-panel:rgba(255,255,255,.58);--la-border:rgba(255,255,255,.78);--la-accent:#078aff;--la-card:rgba(255,255,255,.55);padding:4px 0 40px}
#im-v4[data-theme="dark"] .la-wrap{--la-text:#e9efff;--la-muted:#94a6cb;--la-panel:rgba(32,43,72,.82);--la-border:rgba(140,161,210,.17);--la-accent:#52b4ff;--la-card:rgba(43,55,85,.8)}
#im-v4 .la-wrap *{box-sizing:border-box}#im-v4 .la-breadcrumb{color:var(--la-muted);font-size:14px;margin-bottom:22px}#im-v4 .la-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:22px}#im-v4 .la-head h1{font-size:30px;font-weight:800;color:var(--la-text);margin:0}#im-v4 .la-head p{color:var(--la-muted);margin:4px 0 0}#im-v4 .la-status{font-size:12px;padding:5px 12px;border-radius:8px;background:rgba(16,185,129,.14);color:#10b981;vertical-align:middle}#im-v4 .la-quota{padding:13px 18px;border-radius:12px;background:var(--la-panel);color:var(--la-text);font-size:22px;font-weight:800;white-space:nowrap}#im-v4 .la-panel{background:var(--la-panel);border:1px solid var(--la-border);border-radius:18px;overflow:hidden}#im-v4 .la-tabs{display:flex;gap:16px;padding:0 22px;border-bottom:1px solid var(--la-border)}#im-v4 .la-tab{background:none;border:0;border-bottom:3px solid transparent;color:var(--la-muted);padding:19px 10px;cursor:pointer;font-size:15px}#im-v4 .la-tab[aria-selected="true"]{color:var(--la-accent);border-color:var(--la-accent);font-weight:700}#im-v4 .la-body{padding:24px}#im-v4 .la-info-grid{display:grid;grid-template-columns:minmax(200px,1fr) minmax(0,1.7fr);gap:24px}#im-v4 .la-stats{display:grid;gap:20px;border-right:1px solid var(--la-border);padding-right:24px}#im-v4 .la-label{font-size:13px;color:var(--la-muted);display:block;margin-bottom:6px}#im-v4 .la-value{font-size:16px;color:var(--la-text);font-weight:600;overflow-wrap:anywhere}#im-v4 .la-progress-track{height:12px;border-radius:99px;background:rgba(126,157,201,.17);overflow:hidden;margin-top:10px}#im-v4 .la-progress-fill{height:100%;background:#079cff;border-radius:99px}#im-v4 .la-actions-top{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}#im-v4 .la-btn{border:1px solid rgba(42,142,255,.22);border-radius:10px;background:var(--la-card);color:var(--la-accent);padding:12px 16px;cursor:pointer;font-weight:650;text-align:center}#im-v4 .la-btn:disabled{opacity:.45;cursor:not-allowed}#im-v4 .la-section-title{font-size:17px;font-weight:750;color:var(--la-text);margin:14px 0}#im-v4 .la-containers{display:grid;gap:14px}#im-v4 .la-container{background:var(--la-card);border:1px solid var(--la-border);border-radius:16px;padding:20px}#im-v4 .la-container-head{display:flex;justify-content:space-between;gap:12px;align-items:center}#im-v4 .la-container strong{color:var(--la-text)}#im-v4 .la-container small{display:block;color:var(--la-muted);margin-top:6px}#im-v4 .la-runtime{font-size:12px;color:var(--la-muted)}#im-v4 .la-footer{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:22px}#im-v4 .la-danger{color:#f04466;border-color:rgba(240,68,102,.3)}#im-v4 .la-objective-note{color:var(--la-muted);font-size:14px;margin-top:12px}#im-v4 .la-hidden{display:none!important}
@media(max-width:800px){#im-v4 .la-info-grid{grid-template-columns:1fr}#im-v4 .la-stats{border-right:0;border-bottom:1px solid var(--la-border);padding:0 0 20px}#im-v4 .la-body{padding:16px}#im-v4 .la-head h1{font-size:23px}#im-v4 .la-footer{gap:8px}#im-v4 .la-footer .la-btn{padding:11px 5px;font-size:12px}#im-v4 .la-tabs{gap:8px;padding:0 12px}#im-v4 .la-tab{padding:14px 8px;font-size:13px}}
</style>
@php
    $attacker = $session->attackerEnvironment;
    $victim = $session->victimEnvironment;
    $activeStatus = $session->status?->value ?? 'UNKNOWN';
@endphp
<section class="la-wrap" x-data="{tab:'info'}" aria-label="Active lab">
    <div class="la-breadcrumb"><a href="{{ url('/student/my-labs') }}">My Labs</a> &nbsp; › &nbsp; Lab #{{ $session->id }}</div>
    <header class="la-head"><div><h1>Lab #{{ $session->id }} <span class="la-status">{{ ucwords(strtolower(str_replace('_',' ',$activeStatus))) }}</span></h1><p>{{ $course?->title ?? 'Web Server Breach' }}</p></div><div class="la-quota">{{ $used }} / 1</div></header>
    <div class="la-panel">
        <div class="la-tabs" role="tablist" aria-label="Lab details">
            <button type="button" class="la-tab" role="tab" :aria-selected="tab==='info'" @click="tab='info'">Informasi</button>
            <button type="button" class="la-tab" role="tab" :aria-selected="tab==='containers'" @click="tab='containers'">Containers</button>
            <button type="button" class="la-tab" role="tab" :aria-selected="tab==='progress'" @click="tab='progress'">Progress</button>
        </div>
        <div class="la-body" x-show="tab==='info'">
            <div class="la-info-grid"><div class="la-stats">
                <div><span class="la-label">Course</span><span class="la-value">{{ $course?->title ?? '—' }}</span></div>
                <div><span class="la-label">Created At</span><span class="la-value">{{ $session->created_at?->format('d M Y H:i') ?? '—' }}</span></div>
                <div><span class="la-label">Elapsed Since Start</span><span class="la-value">{{ $duration }}</span></div>
            </div><div>
                <div class="la-actions-top"><button type="button" class="la-btn" wire:click="openWorkspace" @disabled(! $session->status->allowsTerminal())>⚑ Pentest</button><button type="button" class="la-btn" @click="tab='containers'">⌘ Containers / Terminal</button></div>
                <h2 class="la-section-title">Container Information</h2>
                <div class="la-containers">
                    @foreach ([['LAB-ATK (Attacker)', $attacker, $session->attacker_container], ['LAB-ENV (Victim)', $victim, $session->victim_container]] as [$label, $environment, $containerName])
                        <div class="la-container"><div class="la-container-head"><strong>{{ $label }}</strong><span class="la-runtime">{{ $environment?->runtime_status?->value ?? 'Unknown' }}</span></div><small>Container: {{ $environment?->container_name ?? $containerName ?? 'Not assigned' }}</small><small>Terminal: {{ $environment?->isTerminalAvailable() ? 'Available' : 'Unavailable' }}</small></div>
                    @endforeach
                </div>
            </div></div>
        </div>
        {{-- IMNEHNET_DUAL_TERMINAL_V1 --}}
        <div class="la-body" x-show="tab==='containers'" x-cloak x-init="$watch('tab', value => { if (value === 'containers') $wire.connectLabTerminals() })">
            <h2 class="la-section-title">Container Terminals</h2>
            <p class="la-objective-note">LAB-ATK dan LAB-ENV terhubung langsung ke container milik sesi ini.</p>
            @if (! empty($this->dualTerminalErrors['general']))
                <p role="alert" class="la-objective-note">{{ $this->dualTerminalErrors['general'] }}</p>
            @endif
            <div class="la-terminal-grid">
                @foreach (['attacker' => ['LAB-ATK (Attacker)', $attacker], 'victim' => ['LAB-ENV (Victim)', $victim]] as $role => [$label, $environment])
                    <div class="la-terminal-card">
                        <div class="la-terminal-head"><div><strong>{{ $label }}</strong><small>{{ $environment?->container_name ?? 'Not assigned' }}</small></div>
                            
                            {{-- IMNEHNET_TERMINAL_TOOLBAR_V2 --}}
                            <div class="la-terminal-tools">
                                <span class="la-runtime">
                                    {{ isset($this->dualTerminal[$role]) ? 'Connected' : 'Not connected' }}
                                </span>

                                <button
                                    type="button"
                                    class="la-terminal-icon-btn"
                                    title="Reconnect {{ $label }}"
                                    aria-label="Reconnect {{ $label }}"
                                    wire:click="connectLabTerminals"
                                    wire:loading.attr="disabled"
                                    wire:target="connectLabTerminals"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         width="17" height="17" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5"/>
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5"/>
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    class="la-terminal-icon-btn"
                                    title="Toggle fullscreen {{ $label }}"
                                    aria-label="Toggle fullscreen {{ $label }}"
                                    onclick="window.imnehnetToggleTerminalFullscreen(this)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         width="17" height="17" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <path d="M8 3H5a2 2 0 0 0-2 2v3"/>
                                        <path d="M16 3h3a2 2 0 0 1 2 2v3"/>
                                        <path d="M21 16v3a2 2 0 0 1-2 2h-3"/>
                                        <path d="M3 16v3a2 2 0 0 0 2 2h3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        @if (isset($this->dualTerminal[$role]['url'], $this->dualTerminal[$role]['id']))
                            <div class="la-terminal-frame" wire:key="my-labs-terminal-{{ $role }}-{{ $this->dualTerminal[$role]['id'] }}" wire:ignore>
                                <iframe src="{{ $this->dualTerminal[$role]['url'] }}" title="{{ $label }} terminal"
                                    data-lenis-prevent allow="clipboard-read; clipboard-write"
                                    style="display:block;width:100%;height:400px;border:0;background:#0b1118"></iframe>
                            </div>
                        @else
                            <div class="la-terminal-placeholder">
                                @if (! empty($this->dualTerminalErrors[$role]))
                                    <span role="alert">{{ $this->dualTerminalErrors[$role] }}</span>
                                @else
                                    <span wire:loading.remove wire:target="connectLabTerminals">Klik tab Containers untuk menghubungkan terminal.</span>
                                    <span wire:loading wire:target="connectLabTerminals">Connecting...</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            
        </div>
        <div x-show="tab === 'progress'" x-cloak>
    @include('filament.pages.partials.imnehnet-progress')
</div>
    <div class="la-footer"><button type="button" class="la-btn" wire:click="requestReset" @disabled(! in_array($session->status, [\App\Enums\LabSessionStatus::Ready, \App\Enums\LabSessionStatus::Running, \App\Enums\LabSessionStatus::Completed], true))>↺ Reset</button>@if($session->status === \App\Enums\LabSessionStatus::Stopped)<button type="button" class="la-btn" wire:click="resumeLab">Resume</button>@else<button type="button" class="la-btn la-danger" wire:click="requestStop" @disabled(! in_array($session->status, [\App\Enums\LabSessionStatus::Ready, \App\Enums\LabSessionStatus::Running], true))>Stop</button>@endif<button type="button" class="la-btn la-danger" wire:click="requestDelete">Hapus Lab</button></div>
</section>

<script>
/* IMNEHNET_TERMINAL_FULLSCREEN_V2 */
window.imnehnetToggleTerminalFullscreen = function(button) {
    const card = button.closest('.la-terminal-card');
    if (!card) return;

    if (document.fullscreenElement === card) {
        document.exitFullscreen().catch(console.error);
        return;
    }

    if (document.fullscreenElement) {
        document.exitFullscreen()
            .then(() => card.requestFullscreen())
            .catch(console.error);
        return;
    }

    card.requestFullscreen().catch(console.error);
};
</script>
