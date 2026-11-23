# 03 — Admin System Settings: Dokumentasi Implementasi

**Proyek:** IMNEHNET  
**Penanggung jawab:** Septihadi Malik — Portal Developer  
**Source audit:** `Alief1150/imnehnet-iac`, branch `main`, 23 November 2026  
**Status:** Dokumentasi source; screenshot dan pengujian fungsional masih perlu bukti.

> Kode di bawah merupakan cuplikan **source asli** yang ditambahkan komentar penjelasan. Cuplikan parsial tidak selalu merupakan fungsi lengkap yang siap dieksekusi. Tautan setelah blok membuka baris sumber langsung di GitHub. Tidak ada perubahan source aplikasi.

## 1. Ruang lingkup dan fungsi

Pengaturan proyek dan akun Admin, terdiri dari tab Overview dan Account & Security: edit metadata teknologi, nama/email/foto profil, dan perubahan password.

## 2. Alur implementasi

1. Pengguna mengakses halaman melalui panel Admin yang telah diautentikasi.
2. Class PHP Filament menyiapkan state, model, query atau service backend.
3. Komponen Blade atau tabel Filament menampilkan data, form dan tombol aksi.
4. Interaksi diteruskan ke method PHP melalui Livewire/Filament.
5. Operasi yang memengaruhi data harus diverifikasi hasil dan otorisasinya di sisi server.

## 3. Audit kode Blade/PHP dan fungsi variabel

### 3.1 SystemSettings.php

```php
// Penjelasan: Property tab mengontrol panel yang sedang dibuka.

    public string $tab = 'overview';
    public string $name = '';
    public string $email = '';
    public $photo = null;
    public int $photoInputKey = 0;
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';
    public bool $editingOverview = false;
    public array $overviewDraft = [];

    public static function canAccess(): bool
```

**Kode asli:** [SystemSettings.php — baris 26–38](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/SystemSettings.php#L26-L38).

**Pembahasan:** Property tab mengontrol panel yang sedang dibuka. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.2 system-settings.blade.php

```blade
{{-- Penjelasan: Tombol tab memicu perubahan state Livewire. --}}
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

```

**Kode asli:** [system-settings.blade.php — baris 3–15](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/system-settings.blade.php#L3-L15).

**Pembahasan:** Tombol tab memicu perubahan state Livewire. Sintaks `wire:click` / `wire:model` / `wire:submit` mengirim aksi atau nilai input ke Livewire. Blade hanya menangani tampilan, bukan keputusan akses.

### 3.3 SystemSettings.php

```php
// Penjelasan: Metadata proyek divalidasi sebelum disimpan.

    public function saveOverview(): void
    {
        $user = $this->requireAdmin();
        $validated = $this->validate([
            'overviewDraft' => ['required', 'array:name,release,summary,objectives,architecture,features,team,institution,software'],
            'overviewDraft.name' => ['required', 'string', 'max:120'],
            'overviewDraft.release' => ['nullable', 'string', 'max:60'],
            'overviewDraft.summary' => ['required', 'string', 'max:4000'],
            'overviewDraft.objectives' => ['required', 'string', 'max:4000'],
            'overviewDraft.architecture' => ['required', 'string', 'max:6000'],
            'overviewDraft.features' => ['required', 'string', 'max:4000'],
            'overviewDraft.team' => ['nullable', 'string', 'max:3000'],
```

**Kode asli:** [SystemSettings.php — baris 137–149](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/SystemSettings.php#L137-L149).

**Pembahasan:** Metadata proyek divalidasi sebelum disimpan. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.4 system-settings.blade.php

```blade
{{-- Penjelasan: Form pengeditan mengirim data ke method PHP, bukan menyimpan dari HTML. --}}
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
```

**Kode asli:** [system-settings.blade.php — baris 17–29](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/system-settings.blade.php#L17-L29).

**Pembahasan:** Form pengeditan mengirim data ke method PHP, bukan menyimpan dari HTML. Sintaks `wire:click` / `wire:model` / `wire:submit` mengirim aksi atau nilai input ke Livewire. Blade hanya menangani tampilan, bukan keputusan akses.

### 3.5 SystemSettings.php

```php
// Penjelasan: Perubahan nama/email dan foto dijalankan setelah pemeriksaan akun.

    public function updateProfile(): void
    {
        $user = $this->account();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $oldPhoto = $user->avatar_url;
        $newPhoto = null;
        if ($this->photo) {
            $newPhoto = $this->photo->store('avatars/'.$user->getKey(), 'public');
```

**Kode asli:** [SystemSettings.php — baris 179–191](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/SystemSettings.php#L179-L191).

**Pembahasan:** Perubahan nama/email dan foto dijalankan setelah pemeriksaan akun. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.6 SystemSettings.php

```php
// Penjelasan: Password baru diproses melalui validasi backend.
    
public function changePassword(): void

    {

        // im-admin-password-policy-v1

        $user = $this->account();

        $this->resetValidation();


```

**Kode asli:** [SystemSettings.php — baris 228–240](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/SystemSettings.php#L228-L240).

**Pembahasan:** Password baru diproses melalui validasi backend. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

## 4. Nama screenshot dan lokasi penyimpanan

Folder: `jobdesc/Portal-Developer/images/03-admin/10-system-settings/`

![Gambar 1 — System Settings](../../images/03-admin/10-system-settings/01-system-settings-overview.png)

*Gambar 1. System Settings — system settings overview.*

![Gambar 2 — System Settings](../../images/03-admin/10-system-settings/02-edit-overview.png)

*Gambar 2. System Settings — edit overview.*

![Gambar 3 — System Settings](../../images/03-admin/10-system-settings/03-account-profile.png)

*Gambar 3. System Settings — account profile.*

![Gambar 4 — System Settings](../../images/03-admin/10-system-settings/04-change-password.png)

*Gambar 4. System Settings — change password.*

## 5. Pemeriksaan dan pengujian

| Skenario | Target | Status |
|---|---|---|
| Masuk sebagai Admin | Halaman System Settings terbuka | Belum dicatat |
| Akses role tanpa izin | Akses ditolak sesuai kebijakan | Belum dicatat |
| Search/filter/tombol yang tersedia | Perubahan UI sesuai data dan proses server | Belum dicatat |
| Data backend tidak tersedia | Pesan status tidak menyatakan sehat secara palsu | Belum dicatat |

**Perhatian:** Versi software yang diedit di Overview adalah informasi dokumentasi, bukan proses upgrade runtime; jangan tampilkan password pada screenshot.

## 6. Kesimpulan dan tindak lanjut

Source modul System Settings telah diaudit dan terdokumentasi menggunakan 6 cuplikan kode dengan tautan ke baris aslinya. Tahap yang masih diperlukan adalah screenshot, uji role dan perilaku tombol, serta pembandingan dengan source terbaru di server `iaas-mgmt` karena source final sudah masuk GitHub `main` melalui commit `b3370b5`.


---

## Interaksi dan Alur Backend

### Pemetaan tombol, aksi, dan handler

| Kontrol pada halaman | Handler atau state | Penjelasan alur dan hasil |
|---|---|---|
| **Overview / Account & Security** | `selectTab` | Mengubah tab aktif tanpa mengeksekusi operasi server. |
| **Edit Overview** | `editOverview` | Membuka state form pengeditan metadata proyek. |
| **Add / Remove Software** | `addSoftware / removeSoftware` | Mengubah baris daftar software pada draft form, belum menginstal atau menghapus paket OS. |
| **Save / Cancel Overview** | `saveOverview / cancelOverviewChanges` | Menyimpan metadata form atau membatalkan draft; tidak melakukan upgrade infrastruktur. |
| **Update Profile** | `updateProfile` | Memvalidasi dan memperbarui data akun admin. |
| **Remove Photo** | `removePhoto` | Menghapus avatar akun sesuai handler dan izin. |
| **Change Password** | `changePassword` | Memeriksa input password saat ini, password baru, dan konfirmasi di backend. |
| **Cancel Profile / Password** | `cancelProfileChanges / cancelPasswordChanges` | Menghapus state perubahan yang belum disimpan. |


**Cara membaca interaksi:** atribut `wire:click` menjalankan method komponen Livewire melalui request server; `wire:model` mengikat input dan properti; `wire:loading` hanya mencerminkan request masih diproses. Jika terjadi error, status aktual harus mengikuti respons backend, bukan hanya perubahan tampilan.
