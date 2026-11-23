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


## Interaksi dan Alur Backend

### Pemetaan tombol, aksi, dan handler

| Kontrol pada halaman | Handler atau state | Penjelasan alur dan hasil |
|---|---|---|
| **Detail KPI** | `toggleDetail` | Membuka/menutup rincian KPI pada state Livewire. |
| **Filter severity grafik** | `toggleSev` | Memilih severity untuk membatasi data dan grafik yang disajikan. |
| **Daftar alerts** | `openAlerts` | Membuka browser alert/modal dengan data telemetri yang tersedia. |
| **Filter severity alert** | `setSev` | Memperbarui filter severity pada tampilan alert. |
| **Tutup alert** | `closeAlerts` | Menutup panel alert; sumber data tidak dihapus. |
| **Pagination alert** | `setAlertsPage` | Mengubah halaman data alert, tidak mengubah log sumber. |


**Cara membaca interaksi:** atribut `wire:click` menjalankan method komponen Livewire melalui request server; `wire:model` mengikat input dan properti; `wire:loading` hanya mencerminkan request masih diproses. Jika terjadi error, status aktual harus mengikuti respons backend, bukan hanya perubahan tampilan.

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

### Tujuan dan Lingkungan Pengujian

Memastikan implementasi modul dapat ditelusuri dari route Laravel/Filament ke class PHP, tampilan Blade, serta metode/aksi backend yang digunakan. Pengujian berlangsung di **IAAS-MGMT** pada **23 November 2026** menggunakan aplikasi `/home/iaas/projects/webportal-iaas`. Metode berupa skrip **read-only** yang melakukan bootstrap Laravel, inspeksi method/source, dan membaca database bila relevan.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| AD-SC-01 | Halaman Security | GET `/admin/security` dan Blade tersedia | Route, class dan Blade ditemukan | PASS |
| AD-SC-02 | Buka daftar alert | Binding dan handler cocok | `wire:click` → `openAlerts()` ditemukan | PASS |
| AD-SC-03 | Filter severity | Tombol memanggil handler yang tersedia | `toggleSev()` dan `setSev()` cocok dengan Blade | PASS |
| AD-SC-04 | Detail alert | Handler toggle detail tersedia | `toggleDetail()` cocok dengan binding Blade | PASS |
| AD-SC-05 | Akses dan pagination | Method akses dan pagination tersedia | `canAccess()`, `setAlertsPage()` ditemukan | PASS |

**Interpretasi status:** **PASS** berarti pemeriksaan implementasi yang tercantum benar-benar lolos pada terminal. Ini **bukan** hasil klik dan eksekusi operasi produksi.

### Bukti dan Pembahasan

**Rekap Admin Panel V2:** 10 modul **PASS**, 146 pemeriksaan **PASS**, 0 FAIL; angka 146 adalah akumulasi seluruh Admin, bukan jumlah khusus modul ini. Waktu output terminal **05:04:41** (waktu server). Bukti terstruktur: **[Transkrip ringkas pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md)**. File bukti mengutip keluaran hasil terminal yang diberikan operator, termasuk route, method, dan binding yang terdeteksi. Tautan source PHP/Blade terdapat pada bagian implementasi dan referensi dokumen ini.

**Analisis:** seluruh baris yang diuji memenuhi kriteria berupa ketersediaan route, file/class, dan keterhubungan UI–handler sebagaimana cakupan script. Tidak ditemukan kegagalan di dalam pemeriksaan tersebut.

### Batasan dan Tindak Lanjut

Pengujian ini **tidak** menekan tombol pada browser, menjalankan perubahan data, membuka sesi terminal, mengubah environment, atau memvalidasi seluruh output service secara end-to-end. Karena itu, aksi nyata tersebut tidak diklaim PASS. Bukti screenshot terminal lengkap yang disensor dapat dilampirkan saat penyusunan laporan akhir.


## 7. Kesimpulan

Source Security telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Verifikasi implementasi pada server IAAS-MGMT telah PASS; pengujian runtime interaktif dan screenshot tetap dipisahkan.


---
