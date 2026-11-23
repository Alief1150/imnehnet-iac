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


### Hasil Pengujian Implementasi Admin — PASS (23 November 2026)

**Server:** `iaas-mgmt` · **Project:** `/home/iaas/projects/webportal-iaas` · **Metode:** verifikasi PHP read-only terhadap Laravel GET routes, class/method, Blade, binding `wire:click`/`wire:submit`, deklarasi aksi Filament Resource, dan indikasi validasi/otorisasi/pemanggilan service.

**Hasil pada modul monitoring: PASS.** Seluruh **10 dari 10 modul Admin PASS**, dengan **146 pemeriksaan PASS dan 0 FAIL** pada script *Admin Panel Deep Verification V2*, dieksekusi **23 November 2026 pukul 05:04:41** (waktu tercetak di terminal server). Angka 146 merupakan **total lintas 10 modul**, bukan 146 pengujian khusus modul ini.

**Makna hasil:** halaman, class, method dan relasi UI–handler yang diperiksa tersedia. Sinyal seperti `authorization-check`, `validation` dan `service-reference` adalah hasil pembacaan source, **bukan** eksekusi seluruh cabang logika.

**Batas pembuktian:** belum dilakukan klik browser end-to-end, perubahan database, eksekusi action lifecycle lab, pembuatan sesi terminal, atau penyimpanan konfigurasi. Perilaku runtime tidak dinyatakan PASS dari pemeriksaan statis saja.
## 6. Kesimpulan

Struktur dan alur utama **Monitoring** telah dijelaskan dengan 6 cuplikan source tertaut. Hasil verifikasi implementasi di IAAS-MGMT sudah tercatat PASS; bukti screenshot dan uji fungsional browser merupakan kategori bukti terpisah.


---
