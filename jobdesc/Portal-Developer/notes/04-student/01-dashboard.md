# 04.01 — Dashboard Student Webportal IMNEHNET

**Proyek:** IMNEHNET · **PIC:** Malik · **Panel:** Student · **Acuan:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf), 23 November 2026.

**Status:** Source final tersedia dan telah diaudit. Pengujian individual dicatat sesuai bukti, bukan diasumsikan PASS otomatis.

## 1. Tujuan dan Cakupan

Dashboard Student menyajikan data ringkasan mahasiswa melalui StudentDashboard::dashboardData, sementara Blade menyusun tampilan dan partial shell. KPI harus dibaca dari hasil query sesuai akun aktif.

## 2. Arsitektur dan Alur

1. Student mengakses halaman di panel Filament yang sesuai; pemeriksaan role dilakukan oleh class PHP/panel.
2. Page PHP menyediakan query, lifecycle, dan aksi Livewire sesuai sumber data.
3. Blade mengikat state dan interaksi pengguna; komponen yang memerlukan update ditampilkan ulang sesuai mekanisme Livewire.
4. Service/queue melaksanakan operasi backend yang memerlukan pemrosesan asinkron (bila digunakan oleh handler).

## 3. Analisis Source PHP dan Blade

Komentar penjelas berada di luar cuplikan agar baris kode tetap persis sama dengan source yang di-commit.

### 3.1 PHP — dashboardData

Method `dashboardData()` perlu dibaca bersama validasi, state, dan pemanggilan service setelah cuplikan; perhatikan parameter, guard, serta pemutakhiran state.

```php
    }

    public function dashboardData(): array
    {
        $uid = auth()->id();
        $days = collect(range(6, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));
        $empty = [
            'total' => 0, 'active' => 0, 'completed' => 0, 'objectives' => 0,
            'hours' => 0, 'activity' => array_fill(0, 7, 0),
            'usage' => array_fill(0, 7, 0), 'history' => collect(),
            'days' => $days->map(fn ($day) => $day->format('D'))->all(),
            'course_name' => 'Web Server Breach',
        ];
        if (! $uid) {
            return $empty;
        }

        $sessions = LabSession::query()->where('user_id', $uid)
            ->with('course')->orderByDesc('id')->get();
```

**[Buka kode asli: `app/Filament/Pages/StudentDashboard.php` baris 49–67](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDashboard.php#L49-L67)**

### 3.2 PHP — canAccess

Method `canAccess()` perlu dibaca bersama validasi, state, dan pemanggilan service setelah cuplikan; perhatikan parameter, guard, serta pemutakhiran state.

```php


    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}

```

**[Buka kode asli: `app/Filament/Pages/StudentDashboard.php` baris 169–183](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDashboard.php#L169-L183)**

### 3.3 Blade — presentasi dan interaksi

Blade menggunakan ekspresi PHP/komponen dan atribut Livewire untuk merender data. Elemen markup hanya tampilan; operasi sesungguhnya berada di method PHP yang dipanggil.

```blade
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
        <main class="im-v4-content" id="im-v4-main">
            <section class="im-v4-welcome"><span class="im-v4-welcome-icon">{!! $svg('sun') !!}</span><div><h1>Welcome back, {{ $user?->name ?? 'Student' }}!</h1><p>Continue your cybersecurity learning journey.</p></div></section>
            <section class="im-v4-stats" aria-label="Your lab statistics">
                <article class="card im-v4-stat"><span class="im-v4-stat-icon teal">{!! $svg('server') !!}</span><p>Total Labs</p><strong>{{ $d['total'] }}</strong><small>Aktif &amp; sudah dihapus</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon blue">{!! $svg('activity') !!}</span><p>Active Lab</p><strong>{{ $d['active'] }}</strong><small>{{ $d['active'] > 0 ? 'Running / Ready' : 'No active labs' }}</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon violet">{!! $svg('circle-check') !!}</span><p>CTF Progress</p><strong>{{ $completed }} / {{ $objectives }}</strong><small>{{ $d['course_name'] }} · latest attempt</small></article>
                <article class="card im-v4-stat"><span class="im-v4-stat-icon amber">{!! $svg('clock') !!}</span><p>Total Lab Time</p><strong id="imnehnet-lab-time-v3" data-seconds="{{ (int) ($d['lab_time_seconds'] ?? 0) }}" data-live="{{ (int) ($d['lab_time_active_count'] ?? 0) }}">{{ intdiv(max(0, (int) ($d['lab_time_seconds'] ?? 0)), 60) }}</strong><small>Akumulasi waktu lab</small></article>
            </section>
            <div class="im-v4-section-head"><h2>Usage Statistics</h2><span class="im-v4-period badge">Last 7 Days</span></div>
            <section class="im-v4-charts" aria-label="Usage charts">
                <article class="card im-v4-chart"><h3>Lab Session Activity (Daily)</h3><div class="im-v4-chart-area" id="im-v4-activity" aria-label="Activity in the last 7 days"></div></article>
                <article class="card im-v4-chart"><h3>Pentest Objective Progress</h3><div class="im-v4-chart-area" id="im-v4-completion" aria-label="Completed objectives"></div><p class="im-v4-chart-caption">{{ $completed }} / {{ $objectives }} objectives</p></article>
                <article class="card im-v4-chart"><h3>Pentest Terminal Usage (Hours)</h3><div class="im-v4-chart-area" id="im-v4-usage" aria-label="Usage hours in the last 7 days"></div></article>
            </section>
            <section class="im-v4-history" id="im-v4-history"><div class="im-v4-section-head"><div><h2>History</h2><p class="im-v4-history-sub">Tiga sesi lab terbaru</p></div><a href="{{ url('/student/my-labs') }}" class="im-v4-see-all">Lihat Semua {!! $svg('arrow-right') !!}</a></div>
```

**[Buka kode asli: `resources/views/filament/pages/student-dashboard.blade.php` baris 46–65](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-dashboard.blade.php#L46-L65)**

## 4. Perilaku yang Perlu Diverifikasi

| Aspek | Hasil audit | Bukti runtime |
|---|---|---|
| Registrasi halaman & kontrol akses | Ada pada source/panel | Periksa akses role student/admin |
| Tampilan & pengambilan data | Ada pada PHP/Blade | Lampirkan screenshot sesuai akun uji |
| Aksi tombol dan status | Handler tersedia sesuai cuplikan | Jalankan pada data uji sah; catat aktual dan hasil |
| Error, data kosong, dan batasan | Perlu periksa jalur exception/fallback | Belum ada bukti uji terlampir |

Keberhasilan membuka website tidak membuktikan seluruh aksi berjalan sukses. Status **lulus** hanya diberikan ketika langkah, hasil aktual, dan bukti tercatat.

## 5. Screenshot

Folder: [`images/04-student/01-dashboard/`](../../images/04-student/01-dashboard/)

**Gambar 1 — Tampilan keseluruhan halaman.**

![Tampilan keseluruhan halaman](../../images/04-student/01-dashboard/01-dashboard-overview.png)

**Gambar 2 — Komponen KPI/daftar utama.**

![Komponen KPI/daftar utama](../../images/04-student/01-dashboard/02-dashboard-main.png)

**Gambar 3 — Kontrol dan status alur.**

![Kontrol dan status alur](../../images/04-student/01-dashboard/03-dashboard-interaction.png)

**Gambar 4 — Detail atau hasil pada data uji.**

![Detail atau hasil pada data uji](../../images/04-student/01-dashboard/04-dashboard-detail.png)

## 6. Kesimpulan

Dokumen mengacu langsung ke implementasi pada commit final, dengan pemisahan tanggung jawab page PHP dan Blade. Pengujian runtime, role, interaksi, dan lampiran screenshot dilengkapi sebagai bukti final tanpa mengubah source produksi.

## Referensi Source

- [PHP — app/Filament/Pages/StudentDashboard.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDashboard.php)
- [Blade — resources/views/filament/pages/student-dashboard.blade.php](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-dashboard.blade.php)
