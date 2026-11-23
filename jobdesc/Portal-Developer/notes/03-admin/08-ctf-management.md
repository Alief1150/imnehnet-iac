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

| Skenario | Target | Status |
|---|---|---|
| Masuk sebagai Admin | Halaman CTF Management terbuka | Belum dicatat |
| Akses role tanpa izin | Akses ditolak sesuai kebijakan | Belum dicatat |
| Search/filter/tombol yang tersedia | Perubahan UI sesuai data dan proses server | Belum dicatat |
| Data backend tidak tersedia | Pesan status tidak menyatakan sehat secara palsu | Belum dicatat |

**Perhatian:** Jangan mengedit course dan menghapus riwayat produksi hanya untuk membuat screenshot; gunakan data uji.

## 6. Kesimpulan dan tindak lanjut

Source modul CTF Management telah diaudit dan terdokumentasi menggunakan 6 cuplikan kode dengan tautan ke baris aslinya. Tahap yang masih diperlukan adalah screenshot, uji role dan perilaku tombol, serta pembandingan dengan source terbaru di server `iaas-mgmt` karena source final sudah masuk GitHub `main` melalui commit `b3370b5`.


---

## Audit Revisi Final — Commit `b3370b5` (23 November 2026)

Panel mengelola sesi CTF, inspection, course dan objective berdasarkan state sesi.

Cuplikan source aktual:

```php
<?php

namespace App\Filament\Pages;

use App\Enums\ObjectiveStatus;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\Objective;
use App\Models\ObjectiveProgress;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CtfControlCenter extends Page
{
    protected static ?string $navigationLabel = 'CTF Management';
    protected static ?string $title = 'CTF Management';
    protected static ?string $slug = 'ctf-management';
    protected static ?int $navigationSort = 1;
    protected static string|\UnitEnum|null $navigationGroup = 'GUIDED CTF';
    protected string $view = 'filament.pages.ctf-control-center';

    public string $search = '';
```

**[Buka kode asli app/Filament/Pages/CtfControlCenter.php — baris 1–24](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/CtfControlCenter.php#L1-L24)**

**Evaluasi:** Implementasi tersedia di kode final. Halaman yang berjalan merupakan bukti integrasi dasar, tetapi pengujian aksi, keamanan role, ekspor, dan respons kesalahan memerlukan hasil uji/screenshot khusus. Tabel skenario lama tidak dianggap otomatis PASS atau FAIL.
