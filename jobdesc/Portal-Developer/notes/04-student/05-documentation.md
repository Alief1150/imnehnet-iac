# 04.05 — Documentation / Manual Book Webportal IMNEHNET

**PIC:** Malik · **Panel:** Student · **Basis audit:** [commit `b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf), 23 November 2026.

**Status:** Kode implementasi terverifikasi di `main`. Bukti pengujian fungsional disajikan terpisah.

## 1. Deskripsi Pekerjaan

Documentation menyediakan panduan pengguna dan tautan Manual Book PDF secara kondisional: link unduhan ditampilkan hanya apabila file PDF fisik ada di public/downloads. Menu Documentation bukan bukti bahwa PDF telah disediakan.

## 2. Arsitektur dan Alur Pemrosesan

1. Route panel Student memanggil Page Filament dengan pembatasan akses untuk pengguna terautentikasi.
2. Class PHP memvalidasi input/kondisi akses dan menyiapkan state; handler aksi menyimpan data bila diotorisasi.
3. Blade menyusun halaman, menunjukkan pesan dan tombol sesuai kondisi, mengirim aksi Livewire jika tersedia.
4. UI harus menunjukkan keadaan sukses, gagal, dan kosong sesuai keadaan nyata, bukan placeholder.

## 3. Analisis Implementasi PHP dan Blade

Cuplikan memuat source asli dan komentar dokumentasi yang ditandai secara eksplisit, dan tautan pada setiap blok menunjuk ke baris kode asli (tanpa komentar tambahan).

### 3.1 Method PHP — canAccess()

Kode di bawah menangani bagian method php — canaccess(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Memeriksa akses halaman sesuai autentikasi dan role Student.


    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}


```

**[Lihat source `app/Filament/Pages/StudentDocumentation.php` (L55–L70)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDocumentation.php#L55-L70)**

### 3.2 Method PHP — getSlug()

Kode di bawah menangani bagian method php — getslug(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Menentukan slug/route halaman pada panel Filament.


    public static function getSlug(?Panel $panel = null): string

    {

        return 'documentation';

    }


    public static function getNavigationLabel(): string

    {

        return 'Documentation';

    }


    public function getTitle(): string

```

**[Lihat source `app/Filament/Pages/StudentDocumentation.php` (L25–L48)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDocumentation.php#L25-L48)**

### 3.3 Blade — $manualBookPath

Kode di bawah menangani bagian blade — $manualbookpath. Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
@php

    $manualBookPath = public_path('downloads/IMNEHNET-Manual-Book.pdf');

    $manualBookReady = is_file($manualBookPath);

@endphp

<div class="im-doc-manual">

    <div>

        <strong>Manual Book IMNEHNET</strong>

        <p>

            @if($manualBookReady)

                Panduan lengkap tersedia dalam format PDF.

            @else

```

**[Lihat source `resources/views/filament/pages/student-documentation.blade.php` (L58–L79)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-documentation.blade.php#L58-L79)**

### 3.4 Blade — $manualBookReady

Kode di bawah menangani bagian blade — $manualbookready. Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
    $manualBookPath = public_path('downloads/IMNEHNET-Manual-Book.pdf');

    $manualBookReady = is_file($manualBookPath);

@endphp

<div class="im-doc-manual">

    <div>

        <strong>Manual Book IMNEHNET</strong>

        <p>

            @if($manualBookReady)

                Panduan lengkap tersedia dalam format PDF.

            @else

                Manual Book sedang disiapkan. Download akan aktif setelah PDF tersedia.

```

**[Lihat source `resources/views/filament/pages/student-documentation.blade.php` (L60–L81)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-documentation.blade.php#L60-L81)**

### 3.5 Blade — @if($manualBookReady)

Kode di bawah menangani bagian blade — @if($manualbookready). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
        <p>

            @if($manualBookReady)

                Panduan lengkap tersedia dalam format PDF.

            @else

                Manual Book sedang disiapkan. Download akan aktif setelah PDF tersedia.

            @endif

        </p>

    </div>

    @if($manualBookReady)

        <a href="{{ asset('downloads/IMNEHNET-Manual-Book.pdf') }}"

           download="IMNEHNET-Manual-Book.pdf">

```

**[Lihat source `resources/views/filament/pages/student-documentation.blade.php` (L72–L93)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-documentation.blade.php#L72-L93)**


## Interaksi dan Alur Backend

### Pemetaan tombol, aksi, dan handler

| Kontrol | Fungsi dan logika | Source |
|---|---|---|
| **Ke Dashboard** | Anchor menuju `/student` dan tidak mengubah data | [Blade L54](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L54) |
| **Download PDF** | Tautan `downloads/IMNEHNET-Manual-Book.pdf` hanya muncul bila `$manualBookReady` benar; ketika tidak siap, UI menampilkan **Segera Tersedia**, bukan tautan unduh aktif | [Blade L70–99](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L70-L99) |
| **Cari bagian panduan...** | Input `type="search"` melakukan penyaringan tautan sidebar di browser melalui handler `oninput`; bukan pencarian database atau request ke API | [Blade L112–140](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L112-L140) |
| **Mengenal IMNEHNET / Membuat Lab / Mengakses Containers / Pentest & Submit Flag / Mengelola Sesi Lab / Troubleshooting** | Tautan menuju anchor seksi dalam halaman dokumentasi yang sama, bukan menjalankan lifecycle lab | [Blade L148–164](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L148-L164) |
| **Ketentuan Penggunaan** dan **Selanjutnya: Ketentuan Penggunaan** | Navigasi ke `/student/documentation/terms` | [Blade L166–205](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L166-L205) |
| **DI HALAMAN INI** | Daftar *table of contents* dengan anchor lokal seperti `#about`, `#first`, `#containers`; tanpa operasi backend | [Blade L206](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/resources/views/filament/pages/student-documentation.blade.php#L206) |


**Uji:** cek ketersediaan berkas PDF dan dua state unduhan; uji search dengan kata yang ada/tidak ada; cek setiap anchor dan tautan Terms, termasuk kondisi mobile. Ini merupakan verifikasi fungsi navigasi dan aset, bukan pengujian mutasi database.

## 4. Matriks Pengujian

### Tujuan dan Metode Pengujian

Pengujian bertujuan memverifikasi kesesuaian route, class PHP, antarmuka Blade, dan interaksi/handler yang dapat diperiksa di source modul. Lingkungan pengujian: server **IAAS-MGMT**, proyek `/home/iaas/projects/webportal-iaas`, **23 November 2026**. Metode: skrip PHP CLI Laravel **read-only** tanpa mutasi database atau infrastruktur.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| ST-DOC-01 | Route dokumentasi | Halaman Student tersedia | GET `/student/documentation` ditemukan | PASS |
| ST-DOC-02 | Class dan Blade | Implementasi PHP/Blade tersedia | `StudentDocumentation` dan view ditemukan | PASS |
| ST-DOC-03 | Kontrol akses | Method akses didefinisikan | `canAccess()` ditemukan | PASS |
| ST-DOC-04 | Ketersediaan manual | State file manual dirujuk Blade | Referensi `manualBookReady` terdeteksi | PASS |

### Bukti dan Analisis

**Rekap Student:** 82 pemeriksaan PASS pada skrip awal; 1 FAIL disebabkan asumsi URL Pentest yang salah dan telah diperbaiki dengan pemeriksaan ulang route `/student/guided-ctf` (PASS). Keenam modul lolos verifikasi implementasi.

**Bukti terminal:** [Ringkasan transkrip hasil pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md). Cuplikan bukti berasal dari output yang diserahkan operator dan dikaitkan dengan source modul pada bagian implementasi dokumen ini.

**Analisis:** pada skenario yang diuji, route, class, method dan/atau hubungan binding yang relevan berhasil ditemukan. Status **PASS** pada tabel menyatakan *verifikasi implementasi*, bukan kepastian setiap operasi tombol berhasil dieksekusi secara runtime.

### Batasan Pengujian

Pengujian belum mengeksekusi klik browser, pembuatan/penghentian lab, Submit Flag, penyimpanan profil maupun perubahan password, dan belum membuktikan pembatasan akses lintas akun melalui sesi nyata. Hasil operasi tersebut harus dicatat melalui skenario end-to-end tersendiri. Untuk laporan akhir, tambahkan screenshot terminal asli yang telah disensor jika disyaratkan dosen.


## 5. Screenshot

Upload ke [`images/04-student/05-documentation/`](../../images/04-student/05-documentation/).

![Gambar 1 — Documentation / Manual Book](../../images/04-student/05-documentation/01-documentation-overview.png)

![Gambar 2 — Documentation / Manual Book](../../images/04-student/05-documentation/02-documentation-main.png)

![Gambar 3 — Documentation / Manual Book](../../images/04-student/05-documentation/03-documentation-interaction.png)

![Gambar 4 — Documentation / Manual Book](../../images/04-student/05-documentation/04-documentation-result.png)

## 6. Kesimpulan

Modul ini telah dikaitkan dengan implementasi PHP/Blade pada commit final. Penilaian keberhasilan sistem dilengkapi dengan tangkapan layar dan bukti pengujian nyata.

## Referensi Source

- [PHP](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentDocumentation.php)
- [Blade](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-documentation.blade.php)
