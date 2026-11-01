<x-filament-panels::page>
@php
    $d = ['completed'=>0,'objectives'=>0,'days'=>[],'activity'=>[],'usage'=>[]];
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
            'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
            'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
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
                <a class="im-v4-nav-link {{ $i === 5 ? 'is-active' : '' }}" href="{{ $link[1] }}">{!! $svg($link[2]) !!}<span>{{ $link[0] }}</span></a>
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
<section class="im-profile" aria-label="Student account and security">
    <div class="im-profile-heading"><h1>Profile</h1><p>Kelola informasi pribadi, foto profil, dan keamanan akun Anda.</p></div>

    <section class="im-profile-summary card">
        <div class="im-profile-avatar im-profile-avatar-large">
            @if(auth()->user()?->getFilamentAvatarUrl())<img src="{{ auth()->user()->getFilamentAvatarUrl() }}" alt="Foto profil">@else{!! $svg('user-round') !!}@endif
        </div>
        <div class="im-profile-summary-identity"><h2>{{ auth()->user()?->name }}</h2><p>{{ auth()->user()?->email }}</p><div class="im-profile-pills"><span>Student</span><span>Active</span></div></div>
        <div class="im-profile-summary-caption"><strong>PERSONAL ACCOUNT</strong><small>Perubahan hanya untuk akun Anda.</small></div>
    </section>
    <div class="im-profile-grid">
        <section class="im-profile-card card">
            <div class="im-profile-card-head"><div><h2>Profile details</h2><p>Kelola identitas dan foto profil Anda.</p></div>{!! $svg('user-round') !!}</div>
            <div class="im-profile-card-body">
                {{-- IMNEHNET_AVATAR_SECURE_FLYON_V2: FlyonUI-style singleton upload, Livewire-owned request --}}
                <form wire:submit="saveAvatar" class="im-profile-avatar-form" enctype="multipart/form-data">
                    <div class="im-avatar-flyon" data-file-upload='{"acceptedFiles":"image/jpeg,image/png,image/webp","maxFiles":1,"singleton":true}'>
                        <div class="im-avatar-flyon-row">
                            <div class="im-avatar-flyon-preview" data-file-upload-previews>
                                @if($avatarUpload)
                                    <img src="{{ $avatarUpload->temporaryUrl() }}" alt="Preview foto profil" />
                                @elseif(auth()->user()?->getFilamentAvatarUrl())
                                    <img src="{{ auth()->user()->getFilamentAvatarUrl() }}" alt="Foto profil" />
                                @else
                                    {!! $svg('user-round') !!}
                                @endif
                            </div>
                            <div class="im-avatar-flyon-actions">
                                <strong>Foto profil</strong>
                                <div class="im-avatar-flyon-buttons">
                                    <button type="button" class="btn btn-gradient btn-primary im-avatar-flyon-upload" data-file-upload-trigger aria-label="Upload photo">
                                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5"/><path d="M4 16v4h16v-4"/></svg>
                                        Upload photo
                                    </button>
                                    <button type="button" class="btn btn-gradient btn-secondary im-avatar-flyon-delete" data-file-upload-clear>Delete</button>
                                </div>
                                <small>JPG, PNG, atau WebP · maksimal 2 MB · otomatis tersimpan setelah dipilih.</small>
                                <small wire:loading wire:target="avatarUpload,saveAvatar" role="status">Memproses foto...</small>
                                <input id="im-avatar-file" class="im-avatar-flyon-input" type="file" wire:model="avatarUpload" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-label="Pilih gambar avatar" />
                            </div>
                        </div>
                        <p class="im-avatar-flyon-alert" role="alert" aria-live="polite" hidden></p>
                    </div>
                    @error('avatarUpload')<p class="im-profile-error" role="alert">{{ $message }}</p>@enderror
                    
                </form>
                
                <form wire:submit="saveProfile" class="im-profile-form">
                    <label for="im-profile-name">Nama lengkap</label><input id="im-profile-name" type="text" wire:model="name" maxlength="255" autocomplete="name" required>@error('name')<span class="im-profile-error">{{ $message }}</span>@enderror
                    <label for="im-profile-email">Alamat email</label><input id="im-profile-email" type="email" wire:model="email" maxlength="255" autocomplete="email" required>@error('email')<span class="im-profile-error">{{ $message }}</span>@enderror
                    <div class="im-profile-email-confirm"><label for="im-profile-email-pass">Konfirmasi password saat mengubah email</label><input id="im-profile-email-pass" type="password" wire:model="emailPassword" autocomplete="current-password" placeholder="Isi hanya jika mengubah email">@error('emailPassword')<span class="im-profile-error">{{ $message }}</span>@enderror</div>
                    <div class="im-profile-actions"><button class="btn btn-gradient btn-primary im-profile-btn im-profile-primary" type="submit" wire:loading.attr="disabled" wire:target="saveProfile">Simpan profil</button><button class="btn btn-gradient btn-secondary im-profile-btn" type="button" onclick="window.location.reload()">Batal</button></div>
                </form>
            </div>
        </section>
        <section class="im-profile-card card">
            <div class="im-profile-card-head"><div><h2>Change password</h2><p>Konfirmasi password saat ini sebelum menggantinya.</p></div>{!! $svg('shield-check') !!}</div>
            <form wire:submit="changePassword" class="im-profile-card-body im-profile-form">
                <label for="im-old-pass">Password saat ini</label><div class="im-profile-password"><input id="im-old-pass" type="password" wire:model="currentPassword" autocomplete="current-password" required><button type="button" aria-label="Tampilkan password" data-im-profile-eye>{!! $svg('eye') !!}</button></div>@error('currentPassword')<span class="im-profile-error">{{ $message }}</span>@enderror
                <label for="im-new-pass">Password baru</label><div class="im-profile-password"><input id="im-new-pass" type="password" wire:model="newPassword" autocomplete="new-password" required><button type="button" aria-label="Tampilkan password" data-im-profile-eye>{!! $svg('eye') !!}</button></div>@error('newPassword')<span class="im-profile-error">{{ $message }}</span>@enderror
                <label for="im-confirm-pass">Konfirmasi password baru</label><div class="im-profile-password"><input id="im-confirm-pass" type="password" wire:model="newPasswordConfirmation" autocomplete="new-password" required><button type="button" aria-label="Tampilkan password" data-im-profile-eye>{!! $svg('eye') !!}</button></div>@error('newPasswordConfirmation')<span class="im-profile-error">{{ $message }}</span>@enderror
                <div class="im-profile-rules">Password baru harus memenuhi:<span>○ Minimal 8 karakter</span><span>○ Minimal satu huruf besar dan kecil</span><span>○ Minimal satu angka</span><span>○ Minimal satu simbol</span></div>
                <div class="im-profile-actions"><button type="submit" class="btn btn-gradient btn-primary im-profile-btn im-profile-primary" wire:loading.attr="disabled" wire:target="changePassword">Perbarui password</button><button type="reset" class="btn btn-gradient btn-secondary im-profile-btn">Batal</button></div>
            </form>
        </section>
    </div>
</section>
        </main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">@foreach(array_slice($links, 0, 5) as $i => $link)<a href="{{ $link[1] }}" class="{{ $i === 5 ? 'is-active' : '' }}">{!! $svg($link[2]) !!}<span>{{ $i === 0 ? 'Home' : ($i === 4 ? 'Docs' : $link[0]) }}</span></a>@endforeach</nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    <script type="application/json" id="im-v4-chart-data">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
</x-filament-panels::page>
<style id="imnehnet-profile-v1">
.im-profile{max-width:1500px;margin:auto;color:var(--im-profile-ink)}
#im-v4{--im-profile-ink:#172747;--im-profile-muted:#637fa7;--im-profile-card:rgba(255,255,255,.53);--im-profile-field:rgba(255,255,255,.52);--im-profile-border:rgba(145,177,221,.3);--im-profile-blue:#078bfa}
#im-v4[data-theme="dark"]{--im-profile-ink:#eaf2ff;--im-profile-muted:#a3b7d3;--im-profile-card:rgba(28,42,69,.84);--im-profile-field:rgba(15,26,47,.75);--im-profile-border:rgba(132,161,207,.22);--im-profile-blue:#4ba2ff}
.im-profile-heading{margin-bottom:24px}.im-profile-heading h1{font-size:30px;font-weight:800;margin:0 0 5px}.im-profile-heading p,.im-profile-card-head p,.im-profile-summary p{color:var(--im-profile-muted);margin:0}
.im-profile-summary,.im-profile-card{background:var(--im-profile-card)!important;border:1px solid var(--im-profile-border)!important;border-radius:18px!important;box-shadow:none!important;color:var(--im-profile-ink)}
.im-profile-summary{display:flex;align-items:center;gap:18px;padding:24px;margin-bottom:22px}.im-profile-avatar{width:60px;height:60px;border-radius:16px;background:rgba(66,169,230,.16);display:flex;align-items:center;justify-content:center;color:var(--im-profile-blue);flex-shrink:0;overflow:hidden}.im-profile-avatar svg{width:30px;height:30px}.im-profile-avatar img{width:100%;height:100%;object-fit:cover}.im-profile-avatar-large{width:70px;height:70px}.im-profile-summary h2{margin:0;font-size:20px}.im-profile-pills{display:flex;gap:8px;margin-top:7px}.im-profile-pills span{background:rgba(30,151,245,.12);color:var(--im-profile-blue);border-radius:6px;padding:3px 10px;font-size:12px}.im-profile-summary-caption{margin-left:auto;text-align:right;display:grid;gap:5px}.im-profile-summary-caption strong{font-size:11px;color:var(--im-profile-blue)}.im-profile-summary-caption small{color:var(--im-profile-muted)}
.im-profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}.im-profile-card{overflow:hidden}.im-profile-card-head{padding:23px;border-bottom:1px solid var(--im-profile-border);display:flex;justify-content:space-between;gap:12px}.im-profile-card-head h2{margin:0 0 5px;font-size:20px}.im-profile-card-head>svg{color:var(--im-profile-blue)}.im-profile-card-body{padding:22px;display:flex;flex-direction:column;gap:13px}.im-profile-upload{display:flex;gap:14px;align-items:center;border:1px solid var(--im-profile-border);border-radius:12px;padding:14px}.im-profile-upload>div:last-child{min-width:0}.im-profile-upload label{display:block;font-weight:600;margin-bottom:8px}.im-profile-upload input{display:block;max-width:100%;font-size:12px}.im-profile-upload small{display:block;margin-top:8px;color:var(--im-profile-muted)}.im-profile-form label{font-weight:600;font-size:13px}.im-profile-form input:not([type=file]){width:100%;background:var(--im-profile-field);color:var(--im-profile-ink);border:1px solid var(--im-profile-border);border-radius:9px;min-height:46px;padding:11px 13px;outline:none}.im-profile-form input:focus{border-color:var(--im-profile-blue);box-shadow:0 0 0 3px rgba(43,152,245,.12)}.im-profile-email-confirm{display:grid;gap:10px}.im-profile-password{position:relative}.im-profile-password input{padding-right:50px!important}.im-profile-password button{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--im-profile-muted);padding:7px}.im-profile-password svg{width:18px;height:18px}.im-profile-btn{border:1px solid var(--im-profile-border);border-radius:9px;padding:10px 15px;background:var(--im-profile-field);color:var(--im-profile-ink);font-weight:600;cursor:pointer}.im-profile-btn:hover{filter:brightness(1.08)}.im-profile-primary{background:#168efa;color:white;border-color:transparent}.im-profile-danger{color:#ff647a}.im-profile-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}.im-profile-rules{display:grid;gap:7px;font-size:12px;color:var(--im-profile-muted);margin-top:5px}.im-profile-rules span{display:block}.im-profile-error{color:#ff647a;font-size:12px}.im-profile-alert{border:1px solid #37bda4;border-radius:10px;padding:12px;margin-bottom:16px;color:var(--im-profile-ink);background:rgba(55,189,164,.1)}
@media(max-width:900px){.im-profile-grid{grid-template-columns:1fr}.im-profile-summary-caption{display:none}}@media(max-width:600px){.im-profile-heading h1{font-size:24px}.im-profile-summary{align-items:flex-start;flex-direction:column}.im-profile-card-body{padding:17px}.im-profile-card-head{padding:17px}}
</style>
<script id="imnehnet-profile-eye-v1">document.addEventListener('click',function(e){const b=e.target.closest('[data-im-profile-eye]');if(!b)return;const i=b.parentElement.querySelector('input');if(!i)return;i.type=i.type==='password'?'text':'password';b.setAttribute('aria-label',i.type==='password'?'Tampilkan password':'Sembunyikan password')});</script>






























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


<style id="imnehnet-avatar-secure-flyon-v2">
.im-avatar-flyon{border:1px solid var(--im-profile-border);border-radius:12px;padding:16px;background:var(--im-profile-field);color:var(--im-profile-ink)}
.im-avatar-flyon-row{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.im-avatar-flyon-preview{width:72px;height:72px;flex:none;border:2px dashed var(--im-profile-border);border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--im-profile-muted)}
.im-avatar-flyon-preview img{width:100%;height:100%;object-fit:cover}.im-avatar-flyon-preview svg{width:34px;height:34px}.im-avatar-flyon-actions{flex:1;min-width:180px;display:grid;gap:9px}.im-avatar-flyon-actions strong{font-size:13px}.im-avatar-flyon-actions small{font-size:12px;color:var(--im-profile-muted)}
.im-avatar-flyon-buttons{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.im-avatar-flyon-buttons button{font:inherit;font-size:13px;font-weight:650;cursor:pointer;border-radius:9px;padding:10px 13px;min-height:39px;display:inline-flex;gap:7px;align-items:center;justify-content:center;transition:transform .15s,background .15s}.im-avatar-flyon-buttons button:hover{transform:translateY(-1px)}
.im-avatar-flyon-upload{background:#7652f5;color:white;border:1px solid #7652f5}.im-avatar-flyon-delete{background:transparent;color:#fb536b;border:1px solid #fb536b}.im-avatar-flyon-input{position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;overflow:hidden!important;pointer-events:none!important}.im-avatar-flyon-alert{margin:12px 0 0;border:1px solid rgba(251,83,107,.4);border-radius:8px;padding:9px 12px;background:rgba(251,83,107,.08);color:#fb536b;font-size:12px}.im-avatar-save{margin-top:12px}.im-avatar-save:disabled{opacity:.55;cursor:not-allowed}
@media(prefers-reduced-motion:reduce){.im-avatar-flyon-buttons button{transition:none}}
</style>
<script id="imnehnet-avatar-secure-flyon-v2-js">
(function(){
 function init(){
  document.querySelectorAll('.im-avatar-flyon').forEach(function(box){
   if(box.dataset.imAvatarBound==='1')return;box.dataset.imAvatarBound='1';
   var input=box.querySelector('input[type=file]'),upload=box.querySelector('[data-file-upload-trigger]'),clear=box.querySelector('[data-file-upload-clear]'),alert=box.querySelector('.im-avatar-flyon-alert');
   if(!input||!upload||!clear)return;
   function show(message){alert.textContent=message;alert.hidden=!message;}
   upload.addEventListener('click',function(){show('');input.click();});
   input.addEventListener('change',function(){
    var f=input.files&&input.files[0];if(!f)return;
    var valid=['image/jpeg','image/png','image/webp'].includes(f.type)&&/\.(jpe?g|png|webp)$/i.test(f.name);
    if(!valid||f.size>2097152||f.size===0){
     input.value='';
     show(!valid?'File ditolak. Hanya JPG, PNG, atau WebP yang diperbolehkan.':'File ditolak. Ukuran maksimum 2 MB.');
     return;
    }
    show('');
   });
   clear.addEventListener('click',function(){
    input.value='';show('');
    // Livewire's upload state is cleared without deleting a previously saved avatar.
    var root=box.closest('[wire\\:id]');
    if(root&&window.Livewire){try{var c=window.Livewire.find(root.getAttribute('wire:id'));if(c)c.set('avatarUpload',null);}catch(e){}}
   });
  });
 }
 document.addEventListener('DOMContentLoaded',init);
 document.addEventListener('livewire:navigated',init);
 document.addEventListener('livewire:initialized',init);
})();
</script>


<style id="imnehnet-student-profile-v3">
/* IMNEHNET_STUDENT_PROFILE_V3 */
#im-v4 .im-profile .btn.btn-gradient{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer;transition:filter .18s,transform .18s,box-shadow .18s}
#im-v4 .im-profile .btn.btn-gradient:hover{filter:brightness(1.09);transform:translateY(-1px)}
#im-v4 .im-profile .btn.btn-gradient:disabled{opacity:.55;cursor:not-allowed;transform:none}
#im-v4 .im-profile .btn.btn-gradient.btn-primary{background:linear-gradient(125deg,#168ffa,#5c65f6)!important;border:1px solid rgba(255,255,255,.12)!important;color:#fff!important;box-shadow:0 4px 15px rgba(41,112,233,.17)}
#im-v4 .im-profile .btn.btn-gradient.btn-secondary{background:linear-gradient(125deg,#6577a5,#465579)!important;border:1px solid rgba(255,255,255,.14)!important;color:#fff!important}
#im-v4 .im-profile .btn.btn-gradient.im-profile-danger,#im-v4 .im-profile .btn.btn-gradient.im-avatar-flyon-delete{background:linear-gradient(125deg,#b84665,#e65c75)!important;color:#fff!important}
#im-v4[data-theme="light"] .im-profile .btn.btn-gradient.btn-secondary{background:linear-gradient(125deg,#7c91c5,#586db0)!important;color:#fff!important}
.im-profile-toast-stack{position:fixed;right:20px;top:90px;z-index:2147483646;display:grid;gap:10px;width:min(390px,calc(100vw - 32px));pointer-events:none}
.im-profile-toast{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:13px;background:#1c2c48;color:#f3f7ff;border:1px solid #3b5d79;box-shadow:0 14px 45px rgba(0,0,0,.25);pointer-events:auto;animation:imProfileToastIn .2s ease-out}
.im-profile-toast[data-type="error"]{border-color:#f26a80}.im-profile-toast-icon{flex:none;display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:rgba(54,203,167,.16);color:#56dfc3;font-size:17px}
.im-profile-toast[data-type="error"] .im-profile-toast-icon{color:#ff8798;background:rgba(255,100,130,.13)}
.im-profile-toast-message{flex:1;font-size:13px;line-height:1.5;font-weight:600}.im-profile-toast-close{border:0;background:transparent;color:inherit;opacity:.75;cursor:pointer;font-size:19px}
@keyframes imProfileToastIn{from{opacity:0;transform:translateY(-9px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:640px){.im-profile-toast-stack{right:12px;top:75px}}
@media(prefers-reduced-motion:reduce){.im-profile-toast{animation:none}}
</style>
<script id="imnehnet-student-profile-v3-js">
(function(){
 function toast(message,type){
   if(!message)return;
   var stack=document.querySelector('.im-profile-toast-stack');
   if(!stack){stack=document.createElement('div');stack.className='im-profile-toast-stack';stack.setAttribute('aria-live','polite');document.body.appendChild(stack)}
   var el=document.createElement('div');el.className='im-profile-toast';el.dataset.type=type==='error'?'error':'success';el.setAttribute('role',type==='error'?'alert':'status');
   var ico=document.createElement('span');ico.className='im-profile-toast-icon';ico.textContent=type==='error'?'!':'✓';
   var body=document.createElement('span');body.className='im-profile-toast-message';body.textContent=String(message);
   var close=document.createElement('button');close.className='im-profile-toast-close';close.type='button';close.textContent='×';close.setAttribute('aria-label','Tutup notifikasi');close.addEventListener('click',function(){el.remove()});
   el.append(ico,body,close);stack.appendChild(el);setTimeout(function(){el.remove()},5000);
 }
 if(!window.__imProfileV3ToastBound){
  window.__imProfileV3ToastBound=true;
  window.addEventListener('imnehnet-profile-toast',function(e){var x=e.detail||{};toast(x.message,x.type)});
  document.addEventListener('click',function(e){if(e.target.closest('[data-file-upload-trigger]'))return;});
 }
 // Livewire validation errors are rendered under their fields; show a toast too.
 function observeErrors(){
  var root=document.querySelector('#im-v4 .im-profile');if(!root||root.dataset.imV3ErrorObserver)return;
  root.dataset.imV3ErrorObserver='1';var last='';
  new MutationObserver(function(){var errors=[...root.querySelectorAll('.im-profile-error')].map(function(n){return n.textContent.trim()}).filter(Boolean);var key=errors.join('|');if(key&&key!==last){toast(errors[0],'error')}last=key}).observe(root,{childList:true,subtree:true,characterData:true});
 }
 document.addEventListener('DOMContentLoaded',observeErrors);document.addEventListener('livewire:navigated',observeErrors);
 if(document.readyState!=='loading')observeErrors();
})();
</script>


<style id="imnehnet-student-profile-v4-ui-fix">
/* IMNEHNET_STUDENT_PROFILE_V4_UI_FIX: toast appended to body, outside #im-v4 */
body .im-profile-toast-stack { border: 0 !important; outline: 0 !important; box-shadow: none !important; background: transparent !important; }
body .im-profile-toast { box-sizing: border-box !important; border: 1px solid rgba(101, 132, 173, .38) !important; outline: 0 !important; outline-offset: 0 !important; box-shadow: 0 12px 32px rgba(0,0,0,.25) !important; background: #1c2c48 !important; color: #f3f7ff !important; }
body .im-profile-toast:focus, body .im-profile-toast:focus-visible { outline: 0 !important; box-shadow: 0 12px 32px rgba(0,0,0,.25) !important; }
body .im-profile-toast::before, body .im-profile-toast::after { content: none !important; display: none !important; }
body .im-profile-toast-close { outline-offset: 2px; }
body .im-profile-toast-close:focus-visible { outline: 2px solid #6bded0 !important; }
</style>
