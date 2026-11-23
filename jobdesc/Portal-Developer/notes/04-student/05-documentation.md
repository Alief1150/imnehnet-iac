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

Cuplikan berikut diambil dari source asli; seluruh penjelasan sengaja berada di luar cuplikan, sehingga tautan pada setiap blok bisa dibandingkan baris demi baris.

### 3.1 Method PHP — canAccess()

Kode di bawah menangani bagian method php — canaccess(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php


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

## 4. Matriks Pengujian

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
