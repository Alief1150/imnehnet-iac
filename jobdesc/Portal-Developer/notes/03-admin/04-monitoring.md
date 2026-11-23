# Admin Monitoring — Dokumentasi Implementasi Webportal IMNEHNET

**Penanggung jawab:** Septihadi Malik — Portal Developer  
**Tahap:** 03 — Admin Panel  
**Sumber:** Repository `Alief1150/imnehnet-iac`, branch `main` (audit 23 November 2026)  
**Status: Implementasi terverifikasi pada source final; bukti end-to-end dan screenshot menyusul.

## 1. Deskripsi dan fungsi modul

Admin Monitoring memperlihatkan telemetry host fisik, VM IAAS-MGMT/LAB-ENV/LAB-ATK, CPU, memory, storage, container, dan status endpoint monitoring.

Modul dibuat dengan **Filament/Livewire PHP** untuk state, pengambilan data, otorisasi dan aksi backend, serta **Blade** untuk presentasi dan interaksi `wire:click`/`wire:poll`. Kode di bawah merupakan baris dari source asli dengan **komentar tambahan untuk penjelasan**, bukan file lengkap yang dapat langsung ditempel.

## 2. Kode penting: PHP, Blade, variabel dan alurnya

### 2.1 Monitoring.php

```php
// PENJELASAN: Data host fisik diperoleh dari service monitoring.

    public function physicalHost(): array

    {

        return app(\App\Services\MonitoringHostService::class)->summary();

    }



    public function resources(): array
    {
```

**Kode asli:** [Monitoring.php — baris 26–38](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Monitoring.php#L26-L38).

**Fungsi dan alasan:** Data host fisik diperoleh dari service monitoring. Method/properti PHP memproses data di server. Nama variabel pada cuplikan perlu dibaca bersama class dan service yang dipanggil.

### 2.2 Monitoring.php

```php
// PENJELASAN: Rangkaian metrik VM disiapkan untuk grafik.
    /** @return array<string, mixed> */
    public function vmSeries(): array
    {
        return app(MonitoringVmSeriesService::class)->series();
    }

    public function isPolling(): bool
    {
        return true;
    }

    public function refreshedAt(): ?Carbon
    {
```

**Kode asli:** [Monitoring.php — baris 72–84](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/Monitoring.php#L72-L84).

**Fungsi dan alasan:** Rangkaian metrik VM disiapkan untuk grafik. Method/properti PHP memproses data di server. Nama variabel pada cuplikan perlu dibaca bersama class dan service yang dipanggil.

### 2.3 monitoring.blade.php

```blade
{{-- PENJELASAN: Blade menampung hasil method halaman PHP. --}}
    @php
        $resources = $this->resources();
        $physicalHost = $this->physicalHost();
        $hostDetails = $this->hostDetails();
        $trends = $this->trends();
        $inventory = $this->containerInventory();
        $backends = $this->backends();
        $health = $this->health();
        $vmSeries = $this->vmSeries();
        $metricNames = ['cpu' => 'Average CPU', 'memory' => 'Average Memory', 'storage' => 'Average Storage'];
        $metricIcons = ['cpu' => 'cpu', 'memory' => 'memory-stick', 'storage' => 'hard-drive'];
        $metricColors = ['cpu' => '#14b8a6', 'memory' => '#3b82f6', 'storage' => '#f59e0b'];
        $statusTone = fn ($status) => match ($status) {
```

**Kode asli:** [monitoring.blade.php — baris 2–14](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/monitoring.blade.php#L2-L14).

**Fungsi dan alasan:** Blade menampung hasil method halaman PHP. Elemen Blade bertanggung jawab menampilkan UI atau mengirim event ke method Livewire; keputusan akses dan efek sistem tetap berada di PHP.

### 2.4 monitoring.blade.php

```blade
{{-- PENJELASAN: Halaman meminta refresh Livewire 10 detik. --}}

    <div wire:poll.10s class="space-y-4" data-im-monitoring>
        @php
            $hostSamples = collect(['cpu', 'memory', 'storage'])->map(fn ($key) => $physicalHost[$key]['sample_at'] ?? null)->filter(fn ($v) => is_numeric($v));
            $lastHostSample = $hostSamples->isNotEmpty() ? (float) $hostSamples->min() : null;
            $hostAge = $hostDetails['age_seconds'] ?? null;
            $hostOnline = ($hostDetails['up'] ?? false) && collect(['cpu', 'memory', 'storage'])->every(fn ($key) => ($physicalHost[$key]['status'] ?? '') === 'live');
        @endphp
        <div data-monitor-v2-layout class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">Infrastructure Monitoring</h2>
                <p class="mt-1 text-xs text-base-content/60">Near-real-time telemetry · physical host and 3 VMs · dashboard refresh 10s</p>
            </div>
```

**Kode asli:** [monitoring.blade.php — baris 19–31](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/monitoring.blade.php#L19-L31).

**Fungsi dan alasan:** Halaman meminta refresh Livewire 10 detik. Elemen Blade bertanggung jawab menampilkan UI atau mengirim event ke method Livewire; keputusan akses dan efek sistem tetap berada di PHP.

### 2.5 monitoring.blade.php

```blade
{{-- PENJELASAN: Tombol metrik CPU mengubah tampilan grafik frontend. --}}
                        <div class="join" role="group" aria-label="Performance metric">
                            <button type="button" class="btn btn-sm join-item btn-primary" data-monitor-metric="cpu">CPU</button>
                            <button type="button" class="btn btn-sm join-item btn-soft" data-monitor-metric="memory">Memory</button>
                            <button type="button" class="btn btn-sm join-item btn-soft" data-monitor-metric="storage">Storage</button>
                        </div>
                    </div>
                    <div wire:ignore class="min-w-0" data-monitor-chart style="height:290px"></div>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-base-content/70">
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-teal-500"></span>IAAS-MGMT</span>
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-blue-500"></span>LAB-ENV</span>
                        <span><span class="mr-1 inline-block size-2.5 rounded-full bg-amber-500"></span>LAB-ATK</span>
                    </div>
                    <p class="text-[11px] text-base-content/55">Live host telemetry from three native Node Exporters. Missing metrics are shown as No Data.</p>
```

**Kode asli:** [monitoring.blade.php — baris 427–439](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/monitoring.blade.php#L427-L439).

**Fungsi dan alasan:** Tombol metrik CPU mengubah tampilan grafik frontend. Elemen Blade bertanggung jawab menampilkan UI atau mengirim event ke method Livewire; keputusan akses dan efek sistem tetap berada di PHP.

### 2.6 monitoring.blade.php

```blade
{{-- PENJELASAN: Daftar endpoint telemetry dibentuk melalui foreach. --}}
                    <div class="divide-y divide-base-content/10">
                        @foreach ($backends as $backend)
                            @php
                                $probe = $health[$backend['key']] ?? [];
                                $effectiveStatus = $backend['status'];
                            @endphp
                            <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2 shrink-0 rounded-full {{ $effectiveStatus === 'healthy' ? 'bg-success' : ($effectiveStatus === 'offline' ? 'bg-error' : 'bg-warning') }}"></span>
                                        <span class="text-sm font-semibold">{{ $backend['label'] }}</span>
                                    </div>
                                    <div class="truncate text-xs text-base-content/55">{{ $backend['scope'] }}</div>
```

**Kode asli:** [monitoring.blade.php — baris 476–488](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/monitoring.blade.php#L476-L488).

**Fungsi dan alasan:** Daftar endpoint telemetry dibentuk melalui foreach. Elemen Blade bertanggung jawab menampilkan UI atau mengirim event ke method Livewire; keputusan akses dan efek sistem tetap berada di PHP.

## 3. Alur penggunaan

1. Administrator masuk ke panel **Admin** dengan akun yang memiliki izin.
2. Halaman Filament memuat data yang diperlukan dari service atau model.
3. Blade menampilkan nilai/status; tombol dan filter mengirim event Livewire jika tersedia.
4. Server memverifikasi permintaan dan mengembalikan hasil; status operasi harus diperiksa melalui data aktual.

## 4. Screenshot yang dibutuhkan

Upload ke folder `jobdesc/Portal-Developer/images/03-admin/04-monitoring/`:

![Gambar 1 — Monitoring](../../images/03-admin/04-monitoring/01-monitoring-overview.png)

*Gambar 1. Monitoring: monitoring overview.*

![Gambar 2 — Monitoring](../../images/03-admin/04-monitoring/02-physical-host.png)

*Gambar 2. Monitoring: physical host.*

![Gambar 3 — Monitoring](../../images/03-admin/04-monitoring/03-vm-metrics.png)

*Gambar 3. Monitoring: vm metrics.*

![Gambar 4 — Monitoring](../../images/03-admin/04-monitoring/04-telemetry-endpoints.png)

*Gambar 4. Monitoring: telemetry endpoints.*

## 5. Pengujian dan batasan

| Pengujian | Ekspektasi | Status |
|---|---|---|
| Login Admin lalu buka modul | Halaman dan komponen muncul tanpa error | Belum dilampirkan |
| Akses oleh akun tanpa izin | Akses dibatasi sesuai role | Belum dilampirkan |
| Cek isi kartu/tabel/status | Nilai sesuai data backend; tidak dikarang | Belum dilampirkan |
| Coba tombol/filter pada data uji sah | Alur dan validasi backend berjalan sesuai aturan | Belum dilampirkan |

**Catatan:** jangan memodifikasi infrastruktur atau membuka sesi nyata hanya untuk screenshot. Source final telah disinkronkan ke GitHub main pada commit b3370b5; perilaku runtime tetap memerlukan bukti uji.

## 6. Kesimpulan

Struktur dan alur utama **Monitoring** telah dijelaskan dengan 6 cuplikan source tertaut. Screenshot dan bukti pengujian masih perlu dilengkapi untuk menyatakan dokumentasi final.


---

## Pembaruan Audit Source Final — 23 November 2026

**Basis versi:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf) (`feat(portal): joh nggateli`). Contoh berikut adalah cuplikan source terbaru; penjelasan ditempatkan di luar kode agar tidak disalahartikan sebagai baris asli.

### Implementasi yang diverifikasi melalui source

Monitoring mengonsumsi data telemetri dan menampilkan ketersediaan data; nilai metrik harus berasal dari hasil pengukuran aktual.

```php
<?php

namespace App\Services;

use App\Data\MonitoringBackend;
use App\Data\ResourceTrend;
use App\Data\ResourceUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// IMNEHNET_VM_JOB_SPLIT_V14
final class MonitoringService
{
    /**
```

**[Buka kode asli — app/Services/MonitoringService.php, baris 1–17](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Services/MonitoringService.php#L1-L17)**

**Status implementasi:** TERSEDIA DALAM SOURCE `main`. **Status pengujian:** keberadaan kode dan koneksi halaman tidak cukup untuk menyatakan seluruh skenario PASS; pengujian role, interaksi, error path, dan screenshot dinyatakan **perlu bukti hasil uji** bila belum dilampirkan. Dengan demikian tabel pengujian terdahulu adalah daftar verifikasi, bukan bukti kegagalan fitur.


## Penyempurnaan Dokumentasi Final — Audit Commit b3370b5

### Monitoring host, backend, dan data VM

```php
// KOMENTAR DOKUMENTASI: perhatikan hak akses, data dari service, state Livewire, dan fallback error.
    public function hostDetails(): array
    {
        return app(\App\Services\MonitoringHostDetailsService::class)->summary();
    }

    public function physicalHost(): array

    {

        return app(\App\Services\MonitoringHostService::class)->summary();

    }



    public function resources(): array
    {
        return app(MonitoringService::class)->summary();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function trends(): array
    {
        return app(MonitoringService::class)->trend();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function health(): array
    {
        return app(InfrastructureStatusService::class)->summary();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backends(): array
    {
        return app(MonitoringService::class)->backends();
    }

    /** @return array<string, mixed> */
    public function containerInventory(): array
    {
        return app(IncusInventoryMonitoringService::class)->summary();
    }

    /** @return array<string, mixed> */
    public function vmSeries(): array
    {
        return app(MonitoringVmSeriesService::class)->series();
    }
```

**[Buka kode asli — app/Filament/Pages/Monitoring.php (L22–L76)](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/app/Filament/Pages/Monitoring.php#L22-L76)**

**Analisis implementasi:** Page Monitoring menyediakan getter untuk host fisik, resource, tren, health, backend, dan seri metrik VM. Blade membaca hasilnya, sementara service monitoring menentukan asal serta kesegaran telemetri.

**Status verifikasi:** kode implementasi diperiksa di commit `b3370b5` dan sudah tersedia pada source. Tampilan halaman dapat diobservasi saat screenshot, tetapi pengujian aksi, integrasi layanan, pembatasan role, kondisi error, dan perubahan data baru berstatus **PASS** jika dicatat bukti input, keluaran aktual, serta hasilnya. Kode tidak diubah oleh dokumentasi.
