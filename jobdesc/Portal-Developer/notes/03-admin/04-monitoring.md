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

### Tujuan dan Lingkungan Pengujian

Memastikan implementasi modul dapat ditelusuri dari route Laravel/Filament ke class PHP, tampilan Blade, serta metode/aksi backend yang digunakan. Pengujian berlangsung di **IAAS-MGMT** pada **23 November 2026** menggunakan aplikasi `/home/iaas/projects/webportal-iaas`. Metode berupa skrip **read-only** yang melakukan bootstrap Laravel, inspeksi method/source, dan membaca database bila relevan.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| AD-MN-01 | Halaman Monitoring | GET `/admin/monitoring` dan Blade tersedia | Route, class dan Blade ditemukan | PASS |
| AD-MN-02 | Monitoring kesehatan | Method health tersedia | `health()` ditemukan; referensi service terdeteksi | PASS |
| AD-MN-03 | Monitoring resource dan tren | Method resources dan trends tersedia | Kedua method dan sinyal service ditemukan | PASS |
| AD-MN-04 | Inventory dan backend | Method backends dan containerInventory tersedia | Kedua method terdeteksi di PHP | PASS |
| AD-MN-05 | Akses panel | Method canAccess tersedia | `canAccess()` dengan penanda pengecekan akses ditemukan | PASS |

**Interpretasi status:** **PASS** berarti pemeriksaan implementasi yang tercantum benar-benar lolos pada terminal. Ini **bukan** hasil klik dan eksekusi operasi produksi.

### Bukti dan Pembahasan

**Rekap Admin Panel V2:** 10 modul **PASS**, 146 pemeriksaan **PASS**, 0 FAIL; angka 146 adalah akumulasi seluruh Admin, bukan jumlah khusus modul ini. Waktu output terminal **05:04:41** (waktu server). Bukti terstruktur: **[Transkrip ringkas pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md)**. File bukti mengutip keluaran hasil terminal yang diberikan operator, termasuk route, method, dan binding yang terdeteksi. Tautan source PHP/Blade terdapat pada bagian implementasi dan referensi dokumen ini.

**Analisis:** seluruh baris yang diuji memenuhi kriteria berupa ketersediaan route, file/class, dan keterhubungan UI–handler sebagaimana cakupan script. Tidak ditemukan kegagalan di dalam pemeriksaan tersebut.

### Batasan dan Tindak Lanjut

Pengujian ini **tidak** menekan tombol pada browser, menjalankan perubahan data, membuka sesi terminal, mengubah environment, atau memvalidasi seluruh output service secara end-to-end. Karena itu, aksi nyata tersebut tidak diklaim PASS. Bukti screenshot terminal lengkap yang disensor dapat dilampirkan saat penyusunan laporan akhir.


## 6. Kesimpulan

Struktur dan alur utama **Monitoring** telah dijelaskan dengan 6 cuplikan source tertaut. Hasil verifikasi implementasi di IAAS-MGMT sudah tercatat PASS; bukti screenshot dan uji fungsional browser merupakan kategori bukti terpisah.


---
