# 04.02 — My Labs Webportal IMNEHNET

**Proyek:** IMNEHNET · **PIC:** Malik · **Panel:** Student · **Acuan:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf), 23 November 2026.

**Status:** Source final tersedia dan telah diaudit. Pengujian individual dicatat sesuai bukti, bukan diasumsikan PASS otomatis.

## 1. Tujuan dan Cakupan

My Labs mengatur sesi lab mahasiswa dengan state Livewire dan antrian pekerjaan; Start Lab, Stop/Resume, Reset, Delete serta terminal ganda memiliki validasi/konfirmasi dan tidak sekadar mengubah tampilan.

## 2. Arsitektur dan Alur

1. Student mengakses halaman di panel Filament yang sesuai; pemeriksaan role dilakukan oleh class PHP/panel.
2. Page PHP menyediakan query, lifecycle, dan aksi Livewire sesuai sumber data.
3. Blade mengikat state dan interaksi pengguna; komponen yang memerlukan update ditampilkan ulang sesuai mekanisme Livewire.
4. Service/queue melaksanakan operasi backend yang memerlukan pemrosesan asinkron (bila digunakan oleh handler).

## 3. Analisis Source PHP dan Blade

Komentar penjelas berada di luar cuplikan agar baris kode tetap persis sama dengan source yang di-commit.

### 3.1 PHP — startLab

Method `startLab()` perlu dibaca bersama validasi, state, dan pemanggilan service setelah cuplikan; perhatikan parameter, guard, serta pemutakhiran state.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Memulai lifecycle sesi lab lewat backend; kondisi/kuota diperiksa sebelum membuat sesi.
    public function startLab(): void
    {
        $user = auth()->user();
        $course = $this->course();

        if (! $user || ! $course) {
            Notification::make()
                ->title('Course tidak tersedia')
                ->danger()
                ->send();

            return;
        }

        // Reopen an existing session. Never enqueue a duplicate job.
        if ($this->currentSession()) {
            Notification::make()
```

**[Buka kode asli: `app/Filament/Pages/MyLabs.php` baris 106–124](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/MyLabs.php#L106-L124)**

### 3.2 PHP — requestDelete

Method `requestDelete()` perlu dibaca bersama validasi, state, dan pemanggilan service setelah cuplikan; perhatikan parameter, guard, serta pemutakhiran state.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Menampilkan/menyiapkan konfirmasi penghapusan tanpa langsung menghapus lab.
    public function requestDelete(): void
    {
        if ($this->currentSession() === null) {
            return;
        }

        $this->showDeleteConfirmation = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirmation = false;
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirmation = false;
```

**[Buka kode asli: `app/Filament/Pages/MyLabs.php` baris 170–188](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/MyLabs.php#L170-L188)**

### 3.3 PHP — connectLabTerminals

Method `connectLabTerminals()` perlu dibaca bersama validasi, state, dan pemanggilan service setelah cuplikan; perhatikan parameter, guard, serta pemutakhiran state.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Menghubungkan terminal sesi lab berdasarkan status lingkungan dan izin pengguna.
    public array $dualTerminalErrors = [];

    public function connectLabTerminals(): void
    {
        $lab = $this->currentSession();
        $user = auth()->user();
        if (! $user || ! $user->isActive() || ! $lab
            || (int) $lab->user_id !== (int) $user->getKey()
            || ! $lab->status->allowsTerminal()
            || Cache::has('imnehnet:terminal-hold:'.$lab->id)) {
            $this->dualTerminal = [];
            $this->dualTerminalErrors = ['general' => 'Lab belum siap untuk terminal.'];

            return;
        }

        $service = app(TerminalService::class);
        $this->dualTerminalErrors = [];
        foreach (['attacker' => $lab->attackerEnvironment, 'victim' => $lab->victimEnvironment] as $role => $environment) {
```

**[Buka kode asli: `app/Filament/Pages/MyLabs.php` baris 396–414](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/MyLabs.php#L396-L414)**

### 3.4 Blade — presentasi dan interaksi

Blade menggunakan ekspresi PHP/komponen dan atribut Livewire untuk merender data. Elemen markup hanya tampilan; operasi sesungguhnya berada di method PHP yang dipanggil.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
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
```

**[Buka kode asli: `resources/views/filament/pages/my-labs.blade.php` baris 153–172](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/my-labs.blade.php#L153-L172)**


## Interaksi dan Alur Backend

### Pemetaan tombol, aksi, dan handler

| Kontrol | Handler / state | Proses, validasi, dan hasil |
|---|---|---|
| **Start Lab** | `startLab` | Memeriksa autentikasi dan course, mencegah duplikasi sesi, kemudian memulai jalur provisioning sesuai layanan dan queue. |
| **Open Workspace** | `openWorkspace` | Mengarahkan student ke workspace lab sesuai sesi yang tersedia dan aturan akses. |
| **Stop** | `requestStop → stopLab` | Konfirmasi dilakukan sebelum lifecycle berhenti; aksi perubahan runtime ditangani backend. |
| **Resume** | `resumeLab` | Meminta start ulang runtime sesi sesuai status dan batasannya. |
| **Reset** | `requestReset → confirmReset` | Menampilkan konfirmasi dan meminta reset; efek pada state latihan harus diinformasikan kepada student. |
| **Delete** | `requestDelete → confirmDelete` | Meminta konfirmasi lalu menghapus sesi/lab menurut alur backend; jangan dipakai hanya untuk screenshot. |
| **Cancel dialog** | `cancelDelete / cancelStop / cancelReset` | Menutup dialog konfirmasi tanpa memicu operasi destruktif. |
| **Dual Terminal** | `connectLabTerminals` | Menghubungkan terminal untuk lab yang siap, menyimpan state/kegagalan akses menurut handler. |


**Penjelasan:** Blade menyajikan form, tombol, dan indikator loading; method PHP/Livewire menjalankan otorisasi, query, notifikasi, validasi, serta memanggil service atau queue ketika diperlukan. **Pengujian UI dan runtime dipisahkan** agar status keberhasilan tidak dilebihkan tanpa bukti kasus uji.

## 4. Perilaku yang Perlu Diverifikasi

### Tujuan dan Metode Pengujian

Pengujian bertujuan memverifikasi kesesuaian route, class PHP, antarmuka Blade, dan interaksi/handler yang dapat diperiksa di source modul. Lingkungan pengujian: server **IAAS-MGMT**, proyek `/home/iaas/projects/webportal-iaas`, **23 November 2026**. Metode: skrip PHP CLI Laravel **read-only** tanpa mutasi database atau infrastruktur.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| ST-LAB-01 | Route dan UI My Labs | Halaman, class dan Blade tersedia | GET `/student/my-labs`, class dan view terdeteksi | PASS |
| ST-LAB-02 | Start dan Workspace | Tombol cocok dengan method PHP | `startLab()` dan `openWorkspace()` terhubung lewat `wire:click` | PASS |
| ST-LAB-03 | Stop dan Resume | Handler dan binding cocok | `requestStop()`, `stopLab()`, `resumeLab()` terdeteksi | PASS |
| ST-LAB-04 | Reset dan konfirmasi | Handler reset tersedia dan terhubung | `requestReset()`, `cancelReset()`, `confirmReset()` ditemukan | PASS |
| ST-LAB-05 | Delete dan konfirmasi | Handler delete tersedia dan terhubung | `requestDelete()`, `cancelDelete()`, `confirmDelete()` ditemukan | PASS |

### Bukti dan Analisis

**Rekap Student:** 82 pemeriksaan PASS pada skrip awal; 1 FAIL disebabkan asumsi URL Pentest yang salah dan telah diperbaiki dengan pemeriksaan ulang route `/student/guided-ctf` (PASS). Keenam modul lolos verifikasi implementasi.

**Bukti terminal:** [Ringkasan transkrip hasil pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md). Cuplikan bukti berasal dari output yang diserahkan operator dan dikaitkan dengan source modul pada bagian implementasi dokumen ini.

**Analisis:** pada skenario yang diuji, route, class, method dan/atau hubungan binding yang relevan berhasil ditemukan. Status **PASS** pada tabel menyatakan *verifikasi implementasi*, bukan kepastian setiap operasi tombol berhasil dieksekusi secara runtime.

### Batasan Pengujian

Pengujian belum mengeksekusi klik browser, pembuatan/penghentian lab, Submit Flag, penyimpanan profil maupun perubahan password, dan belum membuktikan pembatasan akses lintas akun melalui sesi nyata. Hasil operasi tersebut harus dicatat melalui skenario end-to-end tersendiri. Untuk laporan akhir, tambahkan screenshot terminal asli yang telah disensor jika disyaratkan dosen.


## 5. Screenshot

Folder: [`images/04-student/02-my-labs/`](../../images/04-student/02-my-labs/)

**Gambar 1 — Tampilan keseluruhan halaman.**

![Tampilan keseluruhan halaman](../../images/04-student/02-my-labs/01-my-labs-overview.png)

**Gambar 2 — Komponen KPI/daftar utama.**

![Komponen KPI/daftar utama](../../images/04-student/02-my-labs/02-my-labs-main.png)

**Gambar 3 — Kontrol dan status alur.**

![Kontrol dan status alur](../../images/04-student/02-my-labs/03-my-labs-interaction.png)

**Gambar 4 — Detail atau hasil pada data uji.**

![Detail atau hasil pada data uji](../../images/04-student/02-my-labs/04-my-labs-detail.png)

## 6. Kesimpulan

Dokumen mengacu langsung ke implementasi pada commit final, dengan pemisahan tanggung jawab page PHP dan Blade. Pengujian implementasi dari IAAS-MGMT telah PASS. Uji interaksi browser, role lintas akun, dan screenshot tetap dipisahkan sesuai bukti yang tersedia.

## Referensi Source

- [PHP — app/Filament/Pages/MyLabs.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/MyLabs.php)
- [Blade — resources/views/filament/pages/my-labs.blade.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/my-labs.blade.php)
