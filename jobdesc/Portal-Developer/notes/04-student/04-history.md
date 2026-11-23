# 04.04 — History Webportal IMNEHNET

**PIC:** Malik · **Acuan:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf), 23 November 2026 · **Status:** implementasi tersedia pada source final; bukti uji harus terlampir secara terpisah.

## 1. Tujuan

History menampilkan histori sesi laboratorium berdasarkan kepemilikan akun dan filter status yang diterima. Data harus terbatasi per pengguna dan status diambil dari database; beberapa nilai fallback pada template tidak boleh diklaim sebagai data nyata.

## 2. Mekanisme Kerja

1. Filament mengarahkan pengguna ke Page PHP melalui panel Student; akses mengikuti metode `canAccess()` dan konfigurasi panel.
2. Backend memeriksa user/session, menerapkan aturan domain, lalu menyiapkan data atau memproses aksi.
3. View Blade merender state yang disuplai dari PHP, menyediakan navigasi, filter atau kontrol.
4. Error/empty state harus ditampilkan sesuai data yang tersedia; jangan menyamakan placeholder dengan hasil produksi.

## 3. Cuplikan Kode, Variabel dan Logika

Baris source pada cuplikan berasal dari file yang di-commit; komentar bertanda PENJELASAN DOKUMENTASI adalah anotasi tambahan. Penjelasan diberikan di luar cuplikan, sedangkan tautan tepat di bawahnya menunjuk ke baris source.

### 3.1 PHP — canAccess

Fungsi `canAccess` dipanggil dalam konteks page Filament. Pemeriksaan akses, data, dan efek samping dilakukan melalui kode backend, bukan sekadar tampilan.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Memeriksa akses halaman sesuai autentikasi dan role Student.


    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}


```

**[Buka kode asli `app/Filament/Pages/StudentHistory.php` — baris 55–70](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentHistory.php#L55-L70)**

### 3.2 PHP — getSlug

Fungsi `getSlug` dipanggil dalam konteks page Filament. Pemeriksaan akses, data, dan efek samping dilakukan melalui kode backend, bukan sekadar tampilan.

```php
// PENJELASAN DOKUMENTASI (bukan bagian source): Menentukan slug/route halaman pada panel Filament.


    public static function getSlug(?Panel $panel = null): string

    {

        return 'history';

    }



    public static function getNavigationLabel(): string

    {

        return 'History';

    }


```

**[Buka kode asli `app/Filament/Pages/StudentHistory.php` — baris 25–45](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentHistory.php#L25-L45)**

### 3.3 Blade — $filter = request()

Blade mengimplementasikan bagian antarmuka `$filter = request()`; interaksi dinamis dikaitkan dengan action PHP atau state hasil query.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
    $user = auth()->user();

    $filter = request()->query('status', 'all');

    $allowed = ['all','completed','running','stopped','failed'];

    if (!in_array($filter, $allowed, true)) $filter = 'all';



    $rows = collect();

    $error = null;



    try {

        $model = new \App\Models\LabSession;

```

**[Buka kode asli `resources/views/filament/pages/student-history.blade.php` — baris 56–75](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-history.blade.php#L56-L75)**

### 3.4 Blade — LabSession

Blade mengimplementasikan bagian antarmuka `LabSession`; interaksi dinamis dikaitkan dengan action PHP atau state hasil query.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}
    try {

        $model = new \App\Models\LabSession;

        $table = $model->getTable();

        $schema = \Illuminate\Support\Facades\Schema::connection(

            $model->getConnectionName()

        );



        $owner = collect(['user_id','owner_id','student_id'])

            ->first(fn($c) => $schema->hasColumn($table, $c));



```

**[Buka kode asli `resources/views/filament/pages/student-history.blade.php` — baris 72–91](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-history.blade.php#L72-L91)**

### 3.5 Blade — $rows = collect

Blade mengimplementasikan bagian antarmuka `$rows = collect`; interaksi dinamis dikaitkan dengan action PHP atau state hasil query.

```blade
{{-- PENJELASAN DOKUMENTASI (bukan bagian source): Blade adalah lapisan presentasi: tampilkan state, lakukan binding event, jangan menaruh otorisasi hanya di markup. --}}


    $rows = collect();

    $error = null;



    try {

        $model = new \App\Models\LabSession;

        $table = $model->getTable();

        $schema = \Illuminate\Support\Facades\Schema::connection(

            $model->getConnectionName()

        );

```

**[Buka kode asli `resources/views/filament/pages/student-history.blade.php` — baris 64–83](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-history.blade.php#L64-L83)**

## 4. Pengujian dan Bukti

| Pengujian | Kriteria hasil | Status bukti |
|---|---|---|
| Halaman dapat dibuka akun Student | Render tanpa exception, akses sesuai role | Perlu bukti screenshot |
| Data sesuai identitas pengguna | Tidak menampilkan sesi milik pengguna lain | Perlu uji dengan akun uji |
| Filter, tombol, dan transisi state | Hasil UI konsisten dengan DB/service | Perlu hasil uji dan tangkapan layar |
| Kesalahan dan data kosong | Fallback/validasi aman | Perlu hasil uji |

**Catatan:** aplikasi dapat diakses tidak berarti semua alur uji telah PASS. Pengujian ditandai berhasil setelah langkah, hasil aktual, dan bukti tercatat.

## 5. Screenshot

Simpan di [`images/04-student/04-history`](../../images/04-student/04-history/). Nama file yang disarankan:

![Gambar 1 — History](../../images/04-student/04-history/01-history-overview.png)

![Gambar 2 — History](../../images/04-student/04-history/02-history-status.png)

![Gambar 3 — History](../../images/04-student/04-history/03-history-details.png)

![Gambar 4 — History](../../images/04-student/04-history/04-history-action.png)

## 6. Kesimpulan

Dokumentasi dibuat dari implementasi PHP/Blade final, termasuk alur data, pengamanan per pengguna, dan aksi yang relevan. Lampiran pengujian akan menentukan status kelulusan fungsi secara objektif.

## Referensi

- [PHP](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Pages/StudentHistory.php)
- [Blade](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-history.blade.php)
