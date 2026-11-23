# 03 — Admin Security: Dokumentasi Implementasi IMNEHNET

**PIC:** Septihadi Malik — Portal Developer  
**Source:** `Alief1150/imnehnet-iac` / `main`, audit 23 November 2026  
**Status:** Audit source selesai; bukti screenshot dan pengujian perlu dilengkapi

> Cuplikan berasal dari source asli dan diberi **komentar tambahan**. Potongan baris bukan file lengkap siap tempel. Source GitHub dan deployment server dapat berbeda jika ada patch lokal yang belum dipush.

## 1. Tujuan halaman

Monitoring keamanan yang menggabungkan hasil SIEM/Wazuh, rangkuman KPI, tren alert, severity filter, dan integrasi telemetry. Hasil tidak dipalsukan bila backend tidak tersedia.

## 2. Arsitektur dan fungsi komponen

Filament Resource/Page mendefinisikan data dan action di PHP; Blade (untuk halaman kustom) menampilkan markup dan event UI. Login dan pemeriksaan hak akses tetap harus dilakukan di server. Variabel state Livewire menghubungkan form/filter dengan method backend.

## 3. Penjelasan kode penting

### 3.1 Security.php

```php
// Penjelasan: Properti Livewire menyimpan pilihan periode default 24 jam.
    #[Url(as: 'range')]
    public string $range = '24h';

    public string $sev = 'all';

    public function mount(): void
    {
        if (! array_key_exists($this->range, SecurityDashboard::RANGES)) {
            $this->range = '24h';
        }
    }

    public function selectedRange(): string
```

**Kode asli:** [Security.php, baris 30–42](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Security.php#L30-L42).

**Fungsi:** Properti Livewire menyimpan pilihan periode default 24 jam. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.2 Security.php

```php
// Penjelasan: Range URL dicek ulang terhadap daftar nilai yang diizinkan.

    public function selectedRange(): string
    {
        return array_key_exists($this->range, SecurityDashboard::RANGES)
            ? $this->range
            : '24h';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function kpis(): array
    {
```

**Kode asli:** [Security.php, baris 41–53](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Security.php#L41-L53).

**Fungsi:** Range URL dicek ulang terhadap daftar nilai yang diizinkan. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.3 Security.php

```php
// Penjelasan: KPI berasal dari SecurityDashboard dan collector.
     */
    public function kpis(): array
    {
        return app(SecurityDashboard::class)->kpis(
            $this->memoTelemetry(),
            app(SecurityTelemetryCollector::class)->isolationHistory(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function alertTrend(): array
```

**Kode asli:** [Security.php, baris 51–63](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Security.php#L51-L63).

**Fungsi:** KPI berasal dari SecurityDashboard dan collector. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.4 Security.php

```php
// Penjelasan: Tombol severity memicu perubahan filter.

    public function toggleSev(string $sev): void
    {
        if (! in_array($sev, ['low', 'medium', 'high'], true)) {
            return;
        }

        $this->sev = $this->sev === $sev ? 'all' : $sev;
    }

    /**
     * Newest event per reporter, newest first: one row always proves every
     * plane is heard, where a global latest-N would drown in the noisiest one.
```

**Kode asli:** [Security.php, baris 127–139](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Security.php#L127-L139).

**Fungsi:** Tombol severity memicu perubahan filter. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.5 Security.php

```php
// Penjelasan: Server menyiapkan daftar alert terkini.
     */
    public function recentAlerts(): array
    {
        $byAgent = $this->memoTelemetry()['alerts']['by_agent'] ?? null;

        if (! is_array($byAgent) || $byAgent === []) {
            return $this->memoTelemetry()['alerts']['recent'] ?? [];
        }

        $rows = [];

        foreach ($byAgent as $name => $stats) {
            $rows[] = [
```

**Kode asli:** [Security.php, baris 142–154](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Security.php#L142-L154).

**Fungsi:** Server menyiapkan daftar alert terkini. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.6 security.blade.php

```blade
{{-- Penjelasan: Interaksi Blade memanggil handler Livewire untuk elemen keamanan. --}}
                            @endif
                            <button type="button" wire:click="toggleDetail('{{ $kpiKey }}')"
                                    aria-expanded="{{ in_array($kpiKey, $this->openDetails, true) ? 'true' : 'false' }}"
                                    aria-label="Explain {{ $kpi['label'] }}"
                                    title="What this figure counts, and how to read it"
                                    class="im-detail-toggle {{ in_array($kpiKey, $this->openDetails, true) ? 'is-open' : '' }}">
                                {!! \App\Support\Flyon::html('chevron-down') !!}
                            </button>
                        </div>
                    </div>
                    <div class="im-kpi-value">
                        <span class="font-mono text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ is_numeric($kpi['value'] ?? null) ? number_format((float) $kpi['value']) : 'n/a' }}</span>
                        @if (is_numeric($kpi['value'] ?? null) && ! empty($kpi['unit'])) <span class="text-sm text-gray-500">{{ $kpi['unit'] }}</span> @endif
```

**Kode asli:** [security.blade.php, baris 270–282](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/security.blade.php#L270-L282).

**Fungsi:** Interaksi Blade memanggil handler Livewire untuk elemen keamanan. Kode Blade mengatur markup dan pemanggilan Livewire, bukan keputusan otorisasi.

## 4. Logika tombol, filter, dan data

- **Sumber data:** diambil dari model dan service melalui PHP; HTML/Blade hanya menerima hasil untuk ditampilkan.
- **Tombol/aksi:** konfigurasi Filament `Action::make(...)` atau direktif Livewire memanggil callback/method, termasuk pemeriksaan izin dan kondisi record.
- **Filter/state:** input atau select mengganti nilai state yang diproses pada query/method backend.
- **Hasil:** keberhasilan proses harus diperiksa pada status dan catatan sebenarnya; bukan hanya perubahan warna badge.

## 5. Screenshot dokumentasi

Upload ke `jobdesc/Portal-Developer/images/03-admin/05-security/`. Nama file di bawah harus sama persis:

![Gambar 1 — Security](../../images/03-admin/05-security/01-security-overview.png)

*Gambar 1. Security — security overview.*

![Gambar 2 — Security](../../images/03-admin/05-security/02-security-alert-trend.png)

*Gambar 2. Security — security alert trend.*

![Gambar 3 — Security](../../images/03-admin/05-security/03-severity-filter.png)

*Gambar 3. Security — severity filter.*

![Gambar 4 — Security](../../images/03-admin/05-security/04-security-integrations.png)

*Gambar 4. Security — security integrations.*

## 6. Pengujian dan evaluasi

| ID | Skenario | Target | Status |
|---|---|---|---|
| 01 | Admin membuka modul | Halaman tampil | Bukti hasil uji belum dilampirkan |
| 02 | Pengguna non-Admin mengakses | Akses sesuai kebijakan role | Bukti hasil uji belum dilampirkan |
| 03 | Search/filter/sort (jika tersedia) | Hasil sesuai data | Bukti hasil uji belum dilampirkan |
| 04 | Aksi pada data uji | Validasi dan perubahan backend benar | Bukti hasil uji belum dilampirkan |

**Risiko:** Tidak adanya alert bukan bukti sistem bebas risiko. Periksa freshness collector dan data Wazuh.

## 7. Kesimpulan

Source Security telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Hasil pengujian runtime, screenshot, dan sinkronisasi dengan server aktif tetap perlu dilengkapi sebelum status dokumen final.


---

## Pembaruan Audit Source Final — 23 November 2026

**Basis versi:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf) (`feat(portal): joh nggateli`). Contoh berikut adalah cuplikan source terbaru; penjelasan ditempatkan di luar kode agar tidak disalahartikan sebagai baris asli.

### Implementasi yang diverifikasi melalui source

Collector memproses telemetri keamanan; angka alert bergantung sumber, waktu koleksi, dan normalisasi event.

```php
<?php

namespace App\Services;

use App\Infrastructure\Health\CommandRunner;
use App\Models\ActivityLog;
use App\Models\LabSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What the SIEM and the packet filter report, read by the host account.
 *
 * ── Why this runs as iaas, not as the web ────────────────────────────────────
 *
 * The alert log lives under a root-owned docker volume the web server may not
```

**[Buka kode asli — app/Services/SecurityTelemetryCollector.php, baris 1–17](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Services/SecurityTelemetryCollector.php#L1-L17)**

**Status implementasi:** TERSEDIA DALAM SOURCE `main`. **Status pengujian:** keberadaan kode dan koneksi halaman tidak cukup untuk menyatakan seluruh skenario PASS; pengujian role, interaksi, error path, dan screenshot dinyatakan **perlu bukti hasil uji** bila belum dilampirkan. Dengan demikian tabel pengujian terdahulu adalah daftar verifikasi, bukan bukti kegagalan fitur.
