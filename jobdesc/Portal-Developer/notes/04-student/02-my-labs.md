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

## 4. Perilaku yang Perlu Diverifikasi

| Aspek | Hasil audit | Bukti runtime |
|---|---|---|
| Registrasi halaman & kontrol akses | Ada pada source/panel | Periksa akses role student/admin |
| Tampilan & pengambilan data | Ada pada PHP/Blade | Lampirkan screenshot sesuai akun uji |
| Aksi tombol dan status | Handler tersedia sesuai cuplikan | Jalankan pada data uji sah; catat aktual dan hasil |
| Error, data kosong, dan batasan | Perlu periksa jalur exception/fallback | Belum ada bukti uji terlampir |

Keberhasilan membuka website tidak membuktikan seluruh aksi berjalan sukses. Status **lulus** hanya diberikan ketika langkah, hasil aktual, dan bukti tercatat.

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

Dokumen mengacu langsung ke implementasi pada commit final, dengan pemisahan tanggung jawab page PHP dan Blade. Pengujian runtime, role, interaksi, dan lampiran screenshot dilengkapi sebagai bukti final tanpa mengubah source produksi.

## Referensi Source

- [PHP — app/Filament/Pages/MyLabs.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/MyLabs.php)
- [Blade — resources/views/filament/pages/my-labs.blade.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/my-labs.blade.php)


## Audit Final Implementasi — Commit b3370b5

### Start Lab dan pencegahan duplikasi

Berikut cuplikan PHP asli dari repository terbaru; satu baris komentar dokumentasi ditambahkan untuk menjelaskan alur.

```php
// KOMENTAR DOKUMENTASI: pemrosesan terjadi di backend; periksa guard, state, dan hasil aksi.
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
                ->title('Limit lab tercapai')
                ->body('Kamu sudah memiliki 1 paired lab. Selesaikan atau hapus lab sebelumnya sebelum membuat lab baru.')
                ->warning()
                ->send();

            return;
        }

        try {
            $session = app(LabSessionManager::class)->start($user, $course);

            if (! $session->wasRecentlyCreated) {
                Notification::make()
                    ->title('Lab sudah tersedia')
                    ->body('Sesi lab yang sudah ada akan dibuka.')
                    ->warning()
                    ->send();

                $this->redirect(GuidedCtf::getUrl(panel: 'student'));

                return;
            }

            ProvisioningTimeline::record((int) $session->id, 'queued',
                'Create diterima. User #'.$session->user_id.'; session #'.$session->id.
```

**[Buka kode asli — app/Filament/Pages/MyLabs.php (L108–L149)](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/app/Filament/Pages/MyLabs.php#L108-L149)**

**Logika fitur:** Handler `startLab()` memeriksa autentikasi/course, mendeteksi sesi berjalan, dan menyampaikan feedback lewat Notification. Aksi hidup/mati/reset menggunakan handler backend yang perlu diuji pada sesi latihan, bukan klik tombol pada halaman produksi.

**Pengujian dan hasil:** Source sudah diverifikasi terpasang pada commit final. Akses panel membuktikan halaman dapat diakses pada kondisi pengguna yang diuji, tetapi tidak otomatis memverifikasi semua skenario. Catat PASS pada kasus yang benar-benar sudah diuji beserta screenshot dan outputnya; jangan membuat data hasil uji yang tidak terlampir.
