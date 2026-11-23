# 03 — Admin Activity Logs: Dokumentasi Implementasi

**Proyek:** IMNEHNET  
**Penanggung jawab:** Septihadi Malik — Portal Developer  
**Source audit:** `Alief1150/imnehnet-iac`, branch `main`, 23 November 2026  
**Status:** Dokumentasi source; screenshot dan pengujian fungsional masih perlu bukti.

> Kode di bawah merupakan cuplikan **source asli** yang ditambahkan komentar penjelasan. Cuplikan parsial tidak selalu merupakan fungsi lengkap yang siap dieksekusi. Tautan setelah blok membuka baris sumber langsung di GitHub. Tidak ada perubahan source aplikasi.

## 1. Ruang lingkup dan fungsi

Halaman log aktivitas untuk audit event akun, user dan infrastruktur. Filament Table menghasilkan kolom dan aksi View/Export tanpa harus membuat file Blade khusus.

## 2. Alur implementasi

1. Pengguna mengakses halaman melalui panel Admin yang telah diautentikasi.
2. Class PHP Filament menyiapkan state, model, query atau service backend.
3. Komponen Blade atau tabel Filament menampilkan data, form dan tombol aksi.
4. Interaksi diteruskan ke method PHP melalui Livewire/Filament.
5. Operasi yang memengaruhi data harus diverifikasi hasil dan otorisasinya di sisi server.

## 3. Audit kode Blade/PHP dan fungsi variabel

### 3.1 ActivityLogResource.php

```php
// Penjelasan: Role Admin menjadi syarat akses resource.

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return ActivityLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
```

**Kode asli:** [ActivityLogResource.php — baris 23–35](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/ActivityLogs/ActivityLogResource.php#L23-L35).

**Pembahasan:** Role Admin menjadi syarat akses resource. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.2 ActivityLogsTable.php

```php
// Penjelasan: Kolom waktu ditampilkan relatif dan memiliki tooltip tanggal.
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTooltip()
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->color(fn (string $state): string => str_ends_with($state, '_RESULT') ? 'success' : 'gray'),
```

**Kode asli:** [ActivityLogsTable.php — baris 15–27](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php#L15-L27).

**Pembahasan:** Kolom waktu ditampilkan relatif dan memiliki tooltip tanggal. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.3 ActivityLogsTable.php

```php
// Penjelasan: Nama pelaku berasal dari relasi actor; fallback System.

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

```

**Kode asli:** [ActivityLogsTable.php — baris 28–40](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php#L28-L40).

**Pembahasan:** Nama pelaku berasal dari relasi actor; fallback System. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.4 ListActivityLogs.php

```php
// Penjelasan: Export memakai Filament Action dengan pilihan format.
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button()
                ->schema([
                    Select::make('format')
                        ->label('Format file')
                        ->options([
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV (.csv)',
                        ])
```

**Kode asli:** [ListActivityLogs.php — baris 26–38](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php#L26-L38).

**Pembahasan:** Export memakai Filament Action dengan pilihan format. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

### 3.5 ListActivityLogs.php

```php
// Penjelasan: Pilihan XLSX atau CSV dibaca oleh handler PHP.

                    $format = $data['format'] ?? 'xlsx';
                    abort_unless(in_array($format, ['xlsx', 'csv'], true), 422);

                    $query = ActivityLog::query()
                        ->with('actor')
                        ->when(filled($this->tableSearch ?? null), function (Builder $query): void {
                            $search = $this->tableSearch;

                            $query->where(function (Builder $query) use ($search): void {
                                $query
                                    ->where('action', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
```

**Kode asli:** [ListActivityLogs.php — baris 44–56](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php#L44-L56).

**Pembahasan:** Pilihan XLSX atau CSV dibaca oleh handler PHP. Method/properti PHP menerima state, melakukan pemeriksaan dan mengolah data sesuai kebutuhan modul. Validasi backend tetap diperlukan walaupun UI menyediakan indikator visual.

## 4. Nama screenshot dan lokasi penyimpanan

Folder: `jobdesc/Portal-Developer/images/03-admin/09-activity-logs/`

![Gambar 1 — Activity Logs](../../images/03-admin/09-activity-logs/01-activity-logs-overview.png)

*Gambar 1. Activity Logs — activity logs overview.*

![Gambar 2 — Activity Logs](../../images/03-admin/09-activity-logs/02-logs-search-filters.png)

*Gambar 2. Activity Logs — logs search filters.*

![Gambar 3 — Activity Logs](../../images/03-admin/09-activity-logs/03-logs-view-detail.png)

*Gambar 3. Activity Logs — logs view detail.*

![Gambar 4 — Activity Logs](../../images/03-admin/09-activity-logs/04-logs-export.png)

*Gambar 4. Activity Logs — logs export.*

## 5. Pemeriksaan dan pengujian


### Hasil Pengujian Implementasi Admin — PASS (23 November 2026)

**Server:** `iaas-mgmt` · **Project:** `/home/iaas/projects/webportal-iaas` · **Metode:** verifikasi PHP read-only terhadap Laravel GET routes, class/method, Blade, binding `wire:click`/`wire:submit`, deklarasi aksi Filament Resource, dan indikasi validasi/otorisasi/pemanggilan service.

**Hasil pada modul activity-logs: PASS.** Seluruh **10 dari 10 modul Admin PASS**, dengan **146 pemeriksaan PASS dan 0 FAIL** pada script *Admin Panel Deep Verification V2*, dieksekusi **23 November 2026 pukul 05:04:41** (waktu tercetak di terminal server). Angka 146 merupakan **total lintas 10 modul**, bukan 146 pengujian khusus modul ini.

**Makna hasil:** halaman, class, method dan relasi UI–handler yang diperiksa tersedia. Sinyal seperti `authorization-check`, `validation` dan `service-reference` adalah hasil pembacaan source, **bukan** eksekusi seluruh cabang logika.

**Batas pembuktian:** belum dilakukan klik browser end-to-end, perubahan database, eksekusi action lifecycle lab, pembuatan sesi terminal, atau penyimpanan konfigurasi. Perilaku runtime tidak dinyatakan PASS dari pemeriksaan statis saja.


| Skenario | Target | Status |
|---|---|---|
| Masuk sebagai Admin | Halaman Activity Logs terbuka | Belum dicatat |
| Akses role tanpa izin | Akses ditolak sesuai kebijakan | Belum dicatat |
| Search/filter/tombol yang tersedia | Perubahan UI sesuai data dan proses server | Belum dicatat |
| Data backend tidak tersedia | Pesan status tidak menyatakan sehat secara palsu | Belum dicatat |

**Perhatian:** File ekspor mungkin berisi data sensitif seperti IP/email/log; jangan unggah hasil ekspor ke repo publik.

## 6. Kesimpulan dan tindak lanjut

Source modul Activity Logs telah diaudit dan terdokumentasi menggunakan 5 cuplikan kode dengan tautan ke baris aslinya. Tahap yang masih diperlukan adalah screenshot, uji role dan perilaku tombol, serta pembandingan dengan source terbaru di server `iaas-mgmt` karena source final sudah masuk GitHub `main` melalui commit `b3370b5`.


---
