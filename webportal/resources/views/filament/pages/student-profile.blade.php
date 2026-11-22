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
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
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
@include('filament.pages.partials.student-shell-global')
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
