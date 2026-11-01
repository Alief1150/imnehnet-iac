<x-filament-panels::page>
    <div class="im-settings">
        <nav class="im-settings-tabs" aria-label="System Settings">
            <button type="button" wire:click="selectTab('overview')" wire:loading.attr="disabled" @class(['im-settings-tab', 'is-active' => $tab === 'overview']) aria-current="{{ $tab === 'overview' ? 'page' : 'false' }}">
                <span aria-hidden="true">{!! \App\Support\Lucide::icon('layout-dashboard') !!}</span> Overview
            </button>
            <button type="button" wire:click="selectTab('account-security')" wire:loading.attr="disabled" @class(['im-settings-tab', 'is-active' => $tab === 'account-security']) aria-current="{{ $tab === 'account-security' ? 'page' : 'false' }}">
                <span aria-hidden="true">{!! \App\Support\Lucide::icon('shield-check') !!}</span> Account &amp; Security
            </button>
        </nav>

        @if ($errors->any())
            <div class="im-settings-alert" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if ($tab === 'overview')
            @if ($editingOverview && $canEditOverview)
                <form wire:submit="saveOverview" class="im-settings-stack">
                    <section class="card im-settings-card">
                        <header class="im-settings-card-head"><div><span class="im-settings-eyebrow">PROJECT EDITOR</span><h2>Edit overview</h2><p>Kelola profil proyek dan inventaris teknologi IMNEHNET.</p></div><span class="badge im-settings-badge">Admin only</span></header>
                        <div class="im-settings-card-body im-settings-form-grid">
                            @foreach (['name' => 'Nama platform', 'release' => 'Versi / release proyek', 'institution' => 'Institusi'] as $field => $label)
                                <div class="im-settings-field"><label for="overview-{{ $field }}">{{ $label }}</label><input id="overview-{{ $field }}" class="input im-settings-input" type="text" wire:model="overviewDraft.{{ $field }}" @if ($field !== 'release') required @endif><span class="im-settings-error">@error('overviewDraft.'.$field) {{ $message }} @enderror</span></div>
                            @endforeach
                            <p class="im-settings-help im-settings-wide">Versi proyek adalah label dokumentasi, misalnya 1.0.0. Tidak mengubah versi software yang terpasang.</p>
                            @foreach (['summary' => 'Tentang IMNEHNET', 'objectives' => 'Tujuan proyek', 'architecture' => 'Arsitektur infrastruktur', 'features' => 'Fitur utama', 'team' => 'Tim pengembang'] as $field => $label)
                                <div class="im-settings-field im-settings-wide"><label for="overview-{{ $field }}">{{ $label }}</label><textarea id="overview-{{ $field }}" class="textarea im-settings-input" rows="3" wire:model="overviewDraft.{{ $field }}" @if ($field !== 'team') required @endif></textarea><span class="im-settings-error">@error('overviewDraft.'.$field) {{ $message }} @enderror</span></div>
                            @endforeach
                        </div>
                    </section>
                    <section class="card im-settings-card">
                        <header class="im-settings-card-head"><div><h2>Software &amp; versions</h2><p>Ubah nama, kategori, versi, serta kegunaan setiap teknologi dari halaman ini.</p></div><button type="button" class="btn im-settings-btn im-settings-btn-secondary" wire:click="addSoftware" wire:loading.attr="disabled">+ Tambah software</button></header>
                        <div class="im-settings-card-body im-settings-stack">
                            <div class="im-settings-note">Perubahan ini memperbarui informasi yang ditampilkan. Instalasi atau upgrade software tetap dilakukan pada mesin terkait.</div>
                            @foreach ($overviewDraft['software'] ?? [] as $index => $software)
                                <div class="im-settings-software-edit" wire:key="software-{{ $index }}-{{ $photoInputKey }}">
                                    <div class="im-settings-form-grid">
                                        <div class="im-settings-field"><label for="software-name-{{ $index }}">Nama software</label><input id="software-name-{{ $index }}" class="input im-settings-input" wire:model="overviewDraft.software.{{ $index }}.name" required maxlength="100"></div>
                                        <div class="im-settings-field"><label for="software-group-{{ $index }}">Kategori</label><select id="software-group-{{ $index }}" class="select im-settings-input" wire:model="overviewDraft.software.{{ $index }}.group">@foreach (['Portal', 'Automation / Data', 'Virtualisasi / Lab', 'Monitoring', 'Security / Connectivity'] as $group)<option value="{{ $group }}">{{ $group }}</option>@endforeach</select></div>
                                        <div class="im-settings-field"><label for="software-version-{{ $index }}">Versi software</label><input id="software-version-{{ $index }}" class="input im-settings-input" wire:model="overviewDraft.software.{{ $index }}.version" placeholder="Belum dicatat" maxlength="100"></div>
                                        <div class="im-settings-field"><label for="software-purpose-{{ $index }}">Peran / kegunaan</label><input id="software-purpose-{{ $index }}" class="input im-settings-input" wire:model="overviewDraft.software.{{ $index }}.purpose" required maxlength="500"></div>
                                    </div>
                                    <button type="button" class="btn im-settings-btn im-settings-btn-danger" wire:click="removeSoftware({{ $index }})" aria-label="Hapus software {{ $software['name'] ?: $index + 1 }}">Hapus</button>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    <div class="im-settings-editor-actions"><span>Disimpan bersama sebagai informasi proyek.</span><div class="im-settings-actions"><button type="button" class="btn im-settings-btn im-settings-btn-secondary" wire:click="cancelOverviewChanges" wire:loading.attr="disabled">Batal</button><button type="submit" class="btn btn-primary im-settings-btn im-settings-btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="saveOverview">Simpan overview</span><span wire:loading wire:target="saveOverview">Menyimpan…</span></button></div></div>
                </form>
            @else
                <section class="card im-settings-card im-settings-intro">
                    <div class="im-settings-card-body">
                        <div class="im-settings-intro-top"><span class="im-settings-eyebrow">PLATFORM OVERVIEW</span><span class="badge im-settings-badge">{{ $overview['release'] ?: 'Release belum dicatat' }}</span></div>
                        <div class="im-settings-intro-main"><div><h2>{{ $overview['name'] }}</h2><p class="im-settings-institution">{{ $overview['institution'] }}</p></div>@if ($canEditOverview)<button type="button" class="btn btn-primary im-settings-btn im-settings-btn-primary" wire:click="editOverview" wire:loading.attr="disabled"><span aria-hidden="true">{!! \App\Support\Lucide::icon('pencil') !!}</span> Edit overview</button>@endif</div>
                        <p class="im-settings-prose">{{ $overview['summary'] }}</p>
                        <div class="im-settings-tags"><span>Lab as a Service</span><span>Micro-segmentation</span><span>Infrastructure as Code</span><span>Guided CTF</span></div>
                    </div>
                </section>
                <div class="im-settings-overview-grid">
                    <section class="card im-settings-card"><header class="im-settings-card-head"><div><h2>Tujuan proyek</h2><p>Arah pengembangan IMNEHNET</p></div><span class="im-settings-icon" aria-hidden="true">{!! \App\Support\Lucide::icon('target') !!}</span></header><div class="im-settings-card-body"><p class="im-settings-prose">{{ $overview['objectives'] }}</p></div></section>
                    <section class="card im-settings-card"><header class="im-settings-card-head"><div><h2>Fitur utama</h2><p>Alur praktikum dalam satu portal</p></div><span class="im-settings-icon" aria-hidden="true">{!! \App\Support\Lucide::icon('layers') !!}</span></header><div class="im-settings-card-body"><p class="im-settings-prose">{{ $overview['features'] }}</p></div></section>
                </div>
                <section class="card im-settings-card"><header class="im-settings-card-head"><div><h2>Infrastructure Architecture &amp; Topology</h2><p>Struktur infrastruktur dan peran komponen IMNEHNET.</p></div><span class="im-settings-icon" aria-hidden="true">{!! \App\Support\Lucide::icon('network') !!}</span></header><div class="im-settings-card-body"><p class="im-settings-prose">{{ $overview['architecture'] }}</p></div></section>
                <section class="card im-settings-card">
                    <header class="im-settings-card-head"><div><h2>Technologies &amp; Versions</h2><p>Software yang digunakan pada portal dan infrastruktur proyek.</p></div><span class="badge im-settings-badge">{{ collect($technologyGroups)->sum(fn ($group) => count($group['items'])) }} komponen</span></header>
                    @foreach ($technologyGroups as $groupName => $group)
                        @if (count($group['items']))
                            <div class="im-settings-tech-group"><h3>{{ $groupName }}</h3><div class="im-settings-table-scroll"><table class="table im-settings-table"><caption class="im-settings-sr">Software dan versi — {{ $groupName }}</caption><thead><tr><th scope="col">Software</th><th scope="col">Peran</th><th scope="col">Versi</th></tr></thead><tbody>@foreach ($group['items'] as $item)<tr><th scope="row">{{ $item['name'] }}</th><td>{{ $item['purpose'] }}</td><td><code>{{ $item['version'] }}</code></td></tr>@endforeach</tbody></table></div></div>
                        @endif
                    @endforeach
                </section>
                @if (filled($overview['team']))<section class="card im-settings-card"><header class="im-settings-card-head"><div><h2>Tim pengembang</h2><p>IMNEHNET · Project Based Learning</p></div></header><div class="im-settings-card-body"><p class="im-settings-prose">{{ $overview['team'] }}</p></div></section>@endif
            @endif
        @else
            <section class="card im-settings-card"><div class="im-settings-card-body im-settings-profile-head"><div class="im-settings-avatar">@if ($user->getFilamentAvatarUrl())<img src="{{ $user->getFilamentAvatarUrl() }}" alt="Foto profil {{ $user->name }}">@else<span>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>@endif</div><div class="im-settings-profile-title"><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><div class="im-settings-tags"><span>{{ ucfirst($user->role->value) }}</span><span>{{ ucfirst($user->status->value) }}</span></div></div><div class="im-settings-profile-caption">PERSONAL ACCOUNT<br><span>Perubahan berlaku untuk akun Anda.</span></div></div></section>
            <div class="im-settings-account-grid">
                <section class="card im-settings-card">
                    <header class="im-settings-card-head"><div><h2>Profile details</h2><p>Kelola identitas dan foto profil Anda.</p></div><span class="im-settings-icon" aria-hidden="true">{!! \App\Support\Lucide::icon('user') !!}</span></header>
                    <form wire:submit="updateProfile" class="im-settings-card-body im-settings-stack">
                        <div class="im-settings-upload" wire:key="photo-input-{{ $photoInputKey }}"><div class="im-settings-avatar im-settings-avatar-small">@if ($photo && ! $errors->has('photo'))<img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto baru">@elseif ($user->getFilamentAvatarUrl())<img src="{{ $user->getFilamentAvatarUrl() }}" alt="Foto profil saat ini">@else<span>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>@endif</div><div class="im-settings-field"><label for="settings-photo">Foto profil</label><input id="settings-photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="im-settings-file"><p class="im-settings-help">JPG, PNG, atau WebP. Maksimal 2 MB.</p><p wire:loading wire:target="photo" class="im-settings-help" role="status">Mengunggah foto…</p></div></div>
                        @error('photo')<p class="im-settings-error">{{ $message }}</p>@enderror
                        @if ($user->avatar_url)<button type="button" class="btn im-settings-btn im-settings-btn-danger" wire:click="removePhoto" wire:confirm="Hapus foto profil Anda?" wire:loading.attr="disabled">Hapus foto profil</button>@endif
                        <div class="im-settings-field"><label for="settings-name">Nama lengkap</label><input id="settings-name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="input im-settings-input">@error('name')<p class="im-settings-error">{{ $message }}</p>@enderror</div>
                        <div class="im-settings-field"><label for="settings-email">Alamat email</label><input id="settings-email" type="email" wire:model="email" autocomplete="email" required maxlength="255" class="input im-settings-input">@error('email')<p class="im-settings-error">{{ $message }}</p>@enderror</div>
                        <div class="im-settings-actions"><button type="submit" class="btn btn-primary im-settings-btn im-settings-btn-primary" wire:loading.attr="disabled">Simpan profil</button><button type="button" class="btn im-settings-btn im-settings-btn-secondary" wire:click="cancelProfileChanges" wire:loading.attr="disabled">Batal</button></div>
                    </form>
                </section>
                <div class="im-settings-stack">
                    <section class="card im-settings-card">
                        <header class="im-settings-card-head"><div><h2>Change password</h2><p>Konfirmasi password saat ini sebelum menggantinya.</p></div><span class="im-settings-icon" aria-hidden="true">{!! \App\Support\Lucide::icon('lock-keyhole') !!}</span></header>
                        <form wire:submit="changePassword" class="im-settings-card-body im-settings-stack">
                            @foreach (['current_password' => 'Password saat ini', 'new_password' => 'Password baru', 'new_password_confirmation' => 'Konfirmasi password baru'] as $field => $label)
                                <div class="im-settings-field"><label for="settings-{{ $field }}">{{ $label }}</label><div x-data="{ visible: false }" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center"><input id="settings-{{ $field }}" type="password" x-bind:type="visible ? 'text' : 'password'" wire:model="{{ $field }}"
 @if ($field === 'new_password') x-on:input="$wire.new_password = $event.target.value" @endif
 autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required @if ($field !== 'current_password') minlength="8" maxlength="255" @endif class="input im-settings-input">
<button type="button" class="btn im-settings-btn im-settings-btn-secondary" data-feature="im-password-visibility-v2" x-on:click="visible = !visible" x-bind:aria-pressed="visible" x-bind:aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'" aria-label="Tampilkan password" aria-controls="settings-{{ $field }}" style="padding:10px;min-width:42px">

<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">

<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>

<circle cx="12" cy="12" r="3"></circle>

<path x-show="visible" style="display:none" d="m3 3 18 18"></path>

</svg>

</button>
</div>@error($field)<p class="im-settings-error">{{ $message }}</p>@enderror</div>
                            @endforeach
                            

<div class="im-settings-help" data-feature="im-live-password-rules-v1" x-data="{ meets(rule, value) { const p = String(value || ''); if (rule === 'length') return Array.from(p).length >= 8; if (rule === 'upper') return /\p{Lu}/u.test(p); if (rule === 'number') return /[0-9]/.test(p); return /[\p{P}\p{S}]/u.test(p); } }">

<style>

.im-settings .im-password-rule { display:flex; align-items:center; gap:8px; color:var(--ss-muted); }

.im-settings .im-password-rule[data-valid="true"] { color:#15803d; }

.dark .im-settings .im-password-rule[data-valid="true"] { color:#4ade80; }

.im-settings .im-password-rule svg { width:16px; height:16px; flex-shrink:0; }

</style>

<p style="margin:0 0 8px">Password baru harus memenuhi:</p>

<ul style="margin:0;padding:0;list-style:none;display:grid;gap:6px">

@foreach (['length' => 'Minimal 8 karakter', 'upper' => 'Minimal satu huruf besar', 'number' => 'Minimal satu angka', 'symbol' => 'Minimal satu simbol, misalnya # atau $'] as $ruleKey => $ruleText)

<li class="im-password-rule" x-bind:data-valid="meets('{{ $ruleKey }}', $wire.new_password)">

<svg x-show="!meets('{{ $ruleKey }}', $wire.new_password)" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m7 7 10 10M17 7 7 17"></path></svg>

<svg x-show="meets('{{ $ruleKey }}', $wire.new_password)" style="display:none" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>

<span>{{ $ruleText }}</span>

<span class="im-settings-sr" x-text="meets('{{ $ruleKey }}', $wire.new_password) ? 'Terpenuhi' : 'Belum terpenuhi'"></span>

</li>

@endforeach

</ul>

</div>


                            <div class="im-settings-actions"><button type="submit" class="btn btn-primary im-settings-btn im-settings-btn-primary" wire:loading.attr="disabled">Perbarui password</button><button type="button" class="btn im-settings-btn im-settings-btn-secondary" wire:click="cancelPasswordChanges" wire:loading.attr="disabled">Batal</button></div>
                        </form>
                    </section>
                    
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
