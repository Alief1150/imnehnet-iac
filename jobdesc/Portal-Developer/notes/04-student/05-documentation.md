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


### Hasil Pengujian Implementasi Student — PASS (23 November 2026)

**Lingkungan:** `iaas-mgmt` · **Direktori aplikasi:** `/home/iaas/projects/webportal-iaas` · **Metode:** script PHP read-only, inspeksi route, class, Blade, method PHP, serta binding `wire:click`/`wire:submit` yang tersedia.

**Status verifikasi modul documentation: PASS.** Pengujian Student awal menghasilkan **82 pemeriksaan PASS** dan satu FAIL akibat **asumsi URL Pentest yang salah pada script**, bukan kegagalan handler aplikasi. URL yang benar diverifikasi secara terpisah: **`/student/guided-ctf` → `filament.student.pages.guided-ctf` → `App\Filament\Pages\GuidedCtf`**, dengan hasil **PASS**. Dengan koreksi tersebut, **6 dari 6 modul Student lolos pemeriksaan implementasi**.

**Cakupan PASS:** keberadaan route yang benar, file aplikasi, method, dan kecocokan binding Livewire yang memang ada dalam source. Beberapa kontrol seperti tautan, filter GET, JavaScript, atau action tidak memakai `wire:click` dan tidak seluruhnya diuji otomatis.

**Batas pembuktian:** script tidak menjalankan Start/Stop/Reset/Delete Lab, Submit Flag, upload avatar, perubahan profil/password, maupun interaksi browser secara nyata. Keberhasilan runtime aksi-aksi tersebut memerlukan bukti uji tersendiri. **PASS di sini berarti lolos verifikasi implementasi, bukan uji end-to-end.**


| Aspek | Verifikasi yang diperlukan | Status |
|---|---|---|
| Halaman dan role | Halaman tersedia, akun yang tidak berhak ditolak | Source terverifikasi; hasil uji belum dilampirkan |
| Rendering Blade | Data dan tombol muncul sesuai kondisi | Perlu screenshot |
| Interaksi | Hasil backend sesuai masukan dan validasi | Perlu bukti uji |
| Negative case | Masukan tidak valid/akses tak berhak aman | Perlu bukti uji |

Akses website yang berhasil tidak mengesahkan seluruh skenario uji. Jangan mengklaim PASS tanpa rekaman hasil aktual.

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
