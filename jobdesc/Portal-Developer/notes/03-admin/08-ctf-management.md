# 03 — Admin CTF Management: Dokumentasi Implementasi

**Proyek:** IMNEHNET  
**Penanggung jawab:** Septihadi Malik — Portal Developer  
**Source audit:** `Alief1150/imnehnet-iac`, branch `main`, 23 November 2026  
**Status:** Dokumentasi source; screenshot dan pengujian fungsional masih perlu bukti.

> Kode di bawah merupakan cuplikan **source asli** yang ditambahkan komentar penjelasan. Cuplikan parsial tidak selalu merupakan fungsi lengkap yang siap dieksekusi. Tautan setelah blok membuka baris sumber langsung di GitHub. Tidak ada perubahan source aplikasi.

## 1. Ruang lingkup dan fungsi

Panel Admin untuk memantau status Guided CTF mahasiswa, sesi current/history, course, objective, serta inspector sesi. Pengelolaan dilakukan melalui CtfControlCenter dan Blade.

## 2. Alur implementasi

1. Pengguna mengakses halaman melalui panel Admin yang telah diautentikasi.
2. Class PHP Filament menyiapkan state, model, query atau service backend.
3. Komponen Blade atau tabel Filament menampilkan data, form dan tombol aksi.
4. Interaksi diteruskan ke method PHP melalui Livewire/Filament.
5. Operasi yang memengaruhi data harus diverifikasi hasil dan otorisasinya di sisi server.

## 3. Audit kode Blade/PHP dan fungsi variabel

### 3.1 CtfControlCenter.php

```php
// Penjelasan: Property Livewire digunakan sebagai keyword pencarian sesi.

    public string $search = '';
    public string $statusFilter = 'all';
    public string $courseFilter = 'all';
    public ?int $selectedSession = null;
    public string $viewMode = 'current';
    public int $historyPage = 1;
    public string $inspectorTab = 'overview';
    public ?int $editingCourse = null;
    public string $courseTitle = '';
    public string $courseDifficulty = '';
    public string $courseSummary = '';
    public ?int $editingObjective = null;
```

**Kode asli:** [CtfControlCenter.php — baris 23–35](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/CtfControlCenter.php#L23-L35).

**Pembahasan:** Property Livewire digunakan sebagai keyword pencarian sesi. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.2 CtfControlCenter.php

```php
// Penjelasan: Handler memilih sesi sesuai ID dan memeriksa hak Admin.

    public function inspect(int $sessionId): void
    {
        $this->ensureAdmin();
        $this->selectedSession = LabSession::query()->whereKey($sessionId)->value('id');
        $this->inspectorTab = 'overview';
    }

    public function editCourse(int $id): void
    {
        $this->ensureAdmin();
        $course = Course::query()->findOrFail($id);
        $this->editingCourse = $course->id;
```

**Kode asli:** [CtfControlCenter.php — baris 49–61](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/CtfControlCenter.php#L49-L61).

**Pembahasan:** Handler memilih sesi sesuai ID dan memeriksa hak Admin. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.3 CtfControlCenter.php

```php
// Penjelasan: Course hanya disimpan setelah validasi backend.

    public function saveCourse(): void
    {
        $this->ensureAdmin();
        $data = $this->validate([
            'courseTitle' => 'required|string|max:128',
            'courseDifficulty' => 'required|string|max:32',
            'courseSummary' => 'nullable|string|max:5000',
        ]);
        abort_if($this->editingCourse === null, 404);
        $course = Course::query()->findOrFail($this->editingCourse);
        $course->update([
            'title' => $data['courseTitle'],
```

**Kode asli:** [CtfControlCenter.php — baris 67–79](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/CtfControlCenter.php#L67-L79).

**Pembahasan:** Course hanya disimpan setelah validasi backend. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.4 ctf-control-center.blade.php

```blade
{{-- Penjelasan: Blade mengambil data agregat sesi, course, dan ringkasan KPI dari PHP. --}}
<x-filament-panels::page>
    @php($data = $this->dashboard())
    <div id="ctf-ui-v31" class="space-y-6" style="--ctf-accent:#10b981">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>

                <p class="text-sm text-gray-500 dark:text-gray-400">Verified student progress and course oversight.</p>
            </div>
            <span class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">● Live monitoring · 15s</span>
        </header>
        <div wire:poll.15s="refreshDashboard" class="space-y-4">
            <div class="ctf-v31-kpis grid grid-cols-2 gap-3 xl:grid-cols-4">
                @foreach ([['Current Students',$data['summary']['current_students'],'👥'],['Ready',$data['summary']['ready'],'▶'],['In Progress',$data['summary']['in_progress'],'◷'],['Completed',$data['summary']['completed'],'✓']] as $metric)
```

**Kode asli:** [ctf-control-center.blade.php — baris 1–13](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/ctf-control-center.blade.php#L1-L13).

**Pembahasan:** Blade mengambil data agregat sesi, course, dan ringkasan KPI dari PHP. Sintaks `wire:click` / `wire:model` / `wire:submit` mengirim aksi atau nilai input ke Livewire. Blade hanya menangani tampilan, bukan keputusan akses.

### 3.5 ctf-control-center.blade.php

```blade
{{-- Penjelasan: Tombol Current Students/History mengubah state melalui Livewire. --}}
                    <div class="flex gap-2">
                        <button type="button" wire:click="switchView('current')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $viewMode === 'current' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">Current Students</button>
                        <button type="button" wire:click="switchView('history')" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $viewMode === 'history' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">History · {{ $data['summary']['history'] }}</button>
                    </div>


<div class="flex items-center gap-2">
    <span class="text-xs text-gray-500">
        {{ $data['matched'] }} matching sessions
    </span>
</div>


```

**Kode asli:** [ctf-control-center.blade.php — baris 22–34](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/ctf-control-center.blade.php#L22-L34).

**Pembahasan:** Tombol Current Students/History mengubah state melalui Livewire. Sintaks `wire:click` / `wire:model` / `wire:submit` mengirim aksi atau nilai input ke Livewire. Blade hanya menangani tampilan, bukan keputusan akses.

### 3.6 ctf-control-center.blade.php

```blade
{{-- Penjelasan: Search menggunakan debounce 500 ms agar permintaan tidak terlalu sering. --}}
                <div class="grid gap-2 p-4 md:grid-cols-3">
                    <input type="search" wire:model.live.debounce.500ms="search" placeholder="Search student, email, course…" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    <select wire:model.live="statusFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All statuses</option>
                        @foreach (['CREATING','PROVISIONING','CONFIGURING','READY','RUNNING','STOPPED','RESETTING','COMPLETED','FAILED','DELETING','DELETED'] as $status)
                            <option value="{{ $status }}">{{ ucwords(strtolower($status)) }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="courseFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All courses</option>
                        @foreach ($data['courses'] as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
```

**Kode asli:** [ctf-control-center.blade.php — baris 36–48](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/ctf-control-center.blade.php#L36-L48).

**Pembahasan:** Search menggunakan debounce 500 ms agar permintaan tidak terlalu sering. Sintaks `wire:click` / `wire:model` / `wire:submit` mengirim aksi atau nilai input ke Livewire. Blade hanya menangani tampilan, bukan keputusan akses.

## 4. Nama screenshot dan lokasi penyimpanan

Folder: `jobdesc/Portal-Developer/images/03-admin/08-ctf-management/`

![Gambar 1 — CTF Management](../../images/03-admin/08-ctf-management/01-ctf-management-overview.png)

*Gambar 1. CTF Management — ctf management overview.*

![Gambar 2 — CTF Management](../../images/03-admin/08-ctf-management/02-ctf-current-history.png)

*Gambar 2. CTF Management — ctf current history.*

![Gambar 3 — CTF Management](../../images/03-admin/08-ctf-management/03-ctf-session-inspector.png)

*Gambar 3. CTF Management — ctf session inspector.*

![Gambar 4 — CTF Management](../../images/03-admin/08-ctf-management/04-ctf-course-objective.png)

*Gambar 4. CTF Management — ctf course objective.*

## 5. Pemeriksaan dan pengujian

### Tujuan dan Metode Pengujian

Pengujian bertujuan memverifikasi kesesuaian route, class PHP, antarmuka Blade, dan interaksi/handler yang dapat diperiksa di source modul. Lingkungan pengujian: server **IAAS-MGMT**, proyek `/home/iaas/projects/webportal-iaas`, **23 November 2026**. Metode: skrip PHP CLI Laravel **read-only** tanpa mutasi database atau infrastruktur.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| AD-CTF-01 | Halaman CTF Management | Route dan class tersedia | GET `/admin/ctf-management`, class dan Blade terdeteksi | PASS |
| AD-CTF-02 | Navigasi dan inspector | Handler interaksi tersedia | `switchView()`, `inspect()`, `editCourse()` cocok dengan binding | PASS |
| AD-CTF-03 | Penyimpanan course | Form Blade memanggil method PHP | `wire:submit` → `saveCourse()`; indikator validasi/write source terdeteksi | PASS |
| AD-CTF-04 | Penyimpanan objective | Form Blade memanggil method PHP | `wire:submit` → `saveObjective()`; indikator validasi/write source terdeteksi | PASS |
| AD-CTF-05 | Riwayat | Handler navigasi dan delete tersedia | `deleteHistorySession()`, `previousHistoryPage()`, `nextHistoryPage()` cocok | PASS |

### Bukti dan Analisis

**Rekap Admin V2:** 10/10 modul PASS, 146 pemeriksaan PASS, 0 FAIL, pada 23 November 2026 pukul 05:04:41 menurut output terminal. Angka 146 merupakan total lintas Admin, bukan hanya modul ini.

**Bukti terminal:** [Ringkasan transkrip hasil pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md). Cuplikan bukti berasal dari output yang diserahkan operator dan dikaitkan dengan source modul pada bagian implementasi dokumen ini.

**Analisis:** pada skenario yang diuji, route, class, method dan/atau hubungan binding yang relevan berhasil ditemukan. Status **PASS** pada tabel menyatakan *verifikasi implementasi*, bukan kepastian setiap operasi tombol berhasil dieksekusi secara runtime.

### Batasan Pengujian

Pengujian belum mengklik kontrol browser secara nyata, mengeksekusi aksi infrastruktur, membuka sesi terminal, memodifikasi course/user/settings, atau menguji seluruh kebijakan akses dengan akun berbeda. Hasil operasi tersebut harus dicatat melalui skenario end-to-end tersendiri. Untuk laporan akhir, tambahkan screenshot terminal asli yang telah disensor jika disyaratkan dosen.


## 6. Kesimpulan dan tindak lanjut

Source modul CTF Management telah diaudit dan terdokumentasi menggunakan 6 cuplikan kode dengan tautan ke baris aslinya. Verifikasi source dan hubungan handler di server `iaas-mgmt` telah lulus pengujian implementasi; uji interaksi langsung dan screenshot tetap dibedakan.


---

## Interaksi dan Alur Backend

### Pemetaan tombol, aksi, dan handler

| Kontrol pada halaman | Handler atau state | Penjelasan alur dan hasil |
|---|---|---|
| **Current / History** | `switchView` | Mengubah mode daftar sesi yang disajikan. |
| **Search, Status, Course** | `search / statusFilter / courseFilter` | Properti Livewire memfilter data dari query dashboard. |
| **Inspect** | `inspect` | Membuka inspector sesi terpilih berdasarkan ID yang diproses PHP. |
| **Hapus History** | `deleteHistorySession` | Aksi perubahan data permanen harus mengikuti otorisasi dan konfirmasi; jangan dieksekusi untuk screenshot. |
| **Sebelumnya / Berikutnya** | `previousHistoryPage / nextHistoryPage` | Mengubah pagination pada riwayat. |
| **Edit dan Save Course** | `editCourse / saveCourse` | Memuat metadata course dan menyimpan perubahan setelah pemeriksaan admin/validasi. |
| **Edit dan Save Objective** | `editObjective / saveObjective` | Memuat objective terpilih dan memproses penyimpanannya di backend. |
| **Cancel** | `cancelEdit` | Membatalkan state pengeditan tanpa menyimpan. |


**Cara membaca interaksi:** atribut `wire:click` menjalankan method komponen Livewire melalui request server; `wire:model` mengikat input dan properti; `wire:loading` hanya mencerminkan request masih diproses. Jika terjadi error, status aktual harus mengikuti respons backend, bukan hanya perubahan tampilan.
