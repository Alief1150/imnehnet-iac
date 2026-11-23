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


### Hasil Pengujian Implementasi Admin — PASS (23 November 2026)

**Server:** `iaas-mgmt` · **Project:** `/home/iaas/projects/webportal-iaas` · **Metode:** verifikasi PHP read-only terhadap Laravel GET routes, class/method, Blade, binding `wire:click`/`wire:submit`, deklarasi aksi Filament Resource, dan indikasi validasi/otorisasi/pemanggilan service.

**Hasil pada modul security: PASS.** Seluruh **10 dari 10 modul Admin PASS**, dengan **146 pemeriksaan PASS dan 0 FAIL** pada script *Admin Panel Deep Verification V2*, dieksekusi **23 November 2026 pukul 05:04:41** (waktu tercetak di terminal server). Angka 146 merupakan **total lintas 10 modul**, bukan 146 pengujian khusus modul ini.

**Makna hasil:** halaman, class, method dan relasi UI–handler yang diperiksa tersedia. Sinyal seperti `authorization-check`, `validation` dan `service-reference` adalah hasil pembacaan source, **bukan** eksekusi seluruh cabang logika.

**Batas pembuktian:** belum dilakukan klik browser end-to-end, perubahan database, eksekusi action lifecycle lab, pembuatan sesi terminal, atau penyimpanan konfigurasi. Perilaku runtime tidak dinyatakan PASS dari pemeriksaan statis saja.

**Hasil modul ini: PASS — pemeriksaan implementasi pada IAAS-MGMT.** Route, class/method, view (jika ada), dan binding/aksi yang relevan tercakup dalam pengujian Admin V2 (146 PASS, 0 FAIL untuk 10 modul). Uji klik nyata, hasil query runtime, perubahan data, dan akses lintas role tidak termasuk dalam pengujian ini.

## 7. Kesimpulan

Source Security telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Verifikasi implementasi pada server IAAS-MGMT telah PASS; pengujian runtime interaktif dan screenshot tetap dipisahkan.


---
