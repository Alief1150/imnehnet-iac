# 03 — Admin Environments: Dokumentasi Implementasi IMNEHNET

**PIC:** Septihadi Malik — Portal Developer  
**Source:** `Alief1150/imnehnet-iac` / `main`, audit 23 November 2026  
**Status:** Audit source selesai; bukti screenshot dan pengujian perlu dilengkapi

> Cuplikan berasal dari source asli dan diberi **komentar tambahan**. Potongan baris bukan file lengkap siap tempel. Source GitHub dan deployment server dapat berbeda jika ada patch lokal yang belum dipush.

## 1. Tujuan halaman

Resource Filament untuk inventaris, provisioning/runtime status, filter, dan lifecycle container. Table/Actions dideklarasikan lewat PHP sehingga tidak perlu satu Blade untuk setiap tombol.

## 2. Arsitektur dan fungsi komponen

Filament Resource/Page mendefinisikan data dan action di PHP; Blade (untuk halaman kustom) menampilkan markup dan event UI. Login dan pemeriksaan hak akses tetap harus dilakukan di server. Variabel state Livewire menghubungkan form/filter dengan method backend.

## 3. Penjelasan kode penting

### 3.1 EnvironmentResource.php

```php
// Penjelasan: Resource terhubung dengan model LabEnvironment.
{
    protected static ?string $model = LabEnvironment::class;

    protected static ?string $recordTitleAttribute = 'container_name';

    protected static ?string $label = 'Environment';

    protected static ?string $pluralLabel = 'Environments';

    protected static string|\UnitEnum|null $navigationGroup = 'LAB MANAGEMENT';

    protected static ?int $navigationSort = 10;

```

**Kode asli:** [EnvironmentResource.php, baris 24–36](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/EnvironmentResource.php#L24-L36).

**Fungsi:** Resource terhubung dengan model LabEnvironment. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.2 EnvironmentsTable.php

```php
// Penjelasan: Filament menyusun kolom nama container.
            ->columns([
                TextColumn::make('container_name')
                    // IMNEHNET_PAIR_BORDER_V3
                    ->formatStateUsing(function ($state): \Illuminate\Support\HtmlString {
                        $css = <<<'CSS'
tr.imnehnet-pair-admin-attacker > td,
tr.imnehnet-pair-admin-victim > td {
    background-color: rgba(20,184,166,.035);
}
tr.imnehnet-pair-student-attacker > td,
tr.imnehnet-pair-student-victim > td {
    background-color: rgba(96,165,250,.035);
}
```

**Kode asli:** [EnvironmentsTable.php, baris 36–48](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentsTable.php#L36-L48).

**Fungsi:** Filament menyusun kolom nama container. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.3 EnvironmentsTable.php

```php
// Penjelasan: Runtime dibedakan dari provisioning status agar status tidak rancu.

                TextColumn::make('runtime_status')
                    ->label('Runtime')
                    ->badge()
                    ->formatStateUsing(fn (RuntimeStatus $state): string => $state->label())
                    ->color(fn (RuntimeStatus $state): string => $state->color())
                    ->icon(fn (RuntimeStatus $state): ?Htmlable => $state->icon())
                    ->extraAttributes(fn (LabEnvironment $record): array => $record->hasStateMismatch()
                        ? ['title' => 'State mismatch: the record and the container disagree']
                        : []),

                TextColumn::make('tofu_workspace')
                    ->label('Workspace')
```

**Kode asli:** [EnvironmentsTable.php, baris 205–217](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentsTable.php#L205-L217).

**Fungsi:** Runtime dibedakan dari provisioning status agar status tidak rancu. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.4 EnvironmentActions.php

```php
// Penjelasan: Daftar aksi lifecycle dideklarasikan terpusat.
     */
    public static function all(): array
    {
        return [
            self::start(),
            self::stop(),
            self::reset(),
            self::refresh(),

            // IMNEHNET_ADMIN_DELETE_PAIR_V8
            self::delete(),
        ];
    }
```

**Kode asli:** [EnvironmentActions.php, baris 37–49](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentActions.php#L37-L49).

**Fungsi:** Daftar aksi lifecycle dideklarasikan terpusat. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.5 EnvironmentActions.php

```php
// Penjelasan: Tombol Start terikat pada callback dan pemeriksaan yang sesuai.

    private static function start(): Action
    {
        return Action::make('start')
            ->iconButton()
            ->label('Start')
            ->icon(Lucide::icon('play'))
            ->color('success')
            ->tooltip('Converge the container onto running')
            ->visible(fn (LabEnvironment $record): bool => self::pairRuntimeVisible($record, 'start'))
            ->action(fn (LabEnvironment $record) => self::pairRuntimeAction($record, 'start'));
    }

```

**Kode asli:** [EnvironmentActions.php, baris 409–421](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentActions.php#L409-L421).

**Fungsi:** Tombol Start terikat pada callback dan pemeriksaan yang sesuai. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.6 EnvironmentActions.php

```php
// Penjelasan: Aksi Delete diproses backend dan memiliki guard sesuai record.
    // Exactly one Delete action for independent and managed environments.
    private static function delete(): Action
    {
        $linkedLab = static function (LabEnvironment $record): ?\App\Models\LabSession {
            return \App\Models\LabSession::query()
                ->where(function ($query) use ($record): void {
                    $query
                        ->where('attacker_environment_id', $record->getKey())
                        ->orWhere('victim_environment_id', $record->getKey());
                })
                ->latest('id')
                ->first();
        };
```

**Kode asli:** [EnvironmentActions.php, baris 649–661](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentActions.php#L649-L661).

**Fungsi:** Aksi Delete diproses backend dan memiliki guard sesuai record. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

## 4. Logika tombol, filter, dan data

- **Sumber data:** diambil dari model dan service melalui PHP; HTML/Blade hanya menerima hasil untuk ditampilkan.
- **Tombol/aksi:** konfigurasi Filament `Action::make(...)` atau direktif Livewire memanggil callback/method, termasuk pemeriksaan izin dan kondisi record.
- **Filter/state:** input atau select mengganti nilai state yang diproses pada query/method backend.
- **Hasil:** keberhasilan proses harus diperiksa pada status dan catatan sebenarnya; bukan hanya perubahan warna badge.

## 5. Screenshot dokumentasi

Upload ke `jobdesc/Portal-Developer/images/03-admin/06-environments/`. Nama file di bawah harus sama persis:

![Gambar 1 — Environments](../../images/03-admin/06-environments/01-environments-overview.png)

*Gambar 1. Environments — environments overview.*

![Gambar 2 — Environments](../../images/03-admin/06-environments/02-environments-filters.png)

*Gambar 2. Environments — environments filters.*

![Gambar 3 — Environments](../../images/03-admin/06-environments/03-environments-actions.png)

*Gambar 3. Environments — environments actions.*

![Gambar 4 — Environments](../../images/03-admin/06-environments/04-environment-details.png)

*Gambar 4. Environments — environment details.*

## 6. Pengujian dan evaluasi

| ID | Skenario | Target | Status |
|---|---|---|---|
| 01 | Admin membuka modul | Halaman tampil | Bukti hasil uji belum dilampirkan |
| 02 | Pengguna non-Admin mengakses | Akses sesuai kebijakan role | Bukti hasil uji belum dilampirkan |
| 03 | Search/filter/sort (jika tersedia) | Hasil sesuai data | Bukti hasil uji belum dilampirkan |
| 04 | Aksi pada data uji | Validasi dan perubahan backend benar | Bukti hasil uji belum dilampirkan |

**Risiko:** Start/Stop/Reset/Delete dapat mengubah container nyata. Jangan klik tanpa data uji dan izin.

## 7. Kesimpulan

Source Environments telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Hasil pengujian runtime, screenshot, dan sinkronisasi dengan server aktif tetap perlu dilengkapi sebelum status dokumen final.


---

## Pembaruan Audit Source Final — 23 November 2026

**Basis versi:** commit [`b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf) (`feat(portal): joh nggateli`). Contoh berikut adalah cuplikan source terbaru; penjelasan ditempatkan di luar kode agar tidak disalahartikan sebagai baris asli.

### Implementasi yang diverifikasi melalui source

Aksi lingkungan memisahkan perubahan runtime dari provisioning; operasi pada container dijalankan melalui handler yang terotorisasi.

```php
<?php

namespace App\Filament\Resources\Environments\Tables;

use App\Contracts\LifecycleDriver;
use App\Models\LabEnvironment;
use App\Models\AdminEnvironmentPair;
use App\Services\EnvironmentLifecycleService;
use App\Support\Lucide;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/**
 * The lifecycle actions offered on each environment row.
 *
```

**[Buka kode asli — app/Filament/Resources/Environments/Tables/EnvironmentActions.php, baris 1–17](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Environments/Tables/EnvironmentActions.php#L1-L17)**

**Status implementasi:** TERSEDIA DALAM SOURCE `main`. **Status pengujian:** keberadaan kode dan koneksi halaman tidak cukup untuk menyatakan seluruh skenario PASS; pengujian role, interaksi, error path, dan screenshot dinyatakan **perlu bukti hasil uji** bila belum dilampirkan. Dengan demikian tabel pengujian terdahulu adalah daftar verifikasi, bukan bukti kegagalan fitur.


## Penyempurnaan Dokumentasi Final — Audit Commit b3370b5

### Dua status berbeda: provisioning dan runtime

```php
// KOMENTAR DOKUMENTASI: perhatikan hak akses, data dari service, state Livewire, dan fallback error.
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Provisioning')
                    ->badge()
                    ->formatStateUsing(fn (EnvironmentStatus $state): string => $state->label())
                    ->color(fn (EnvironmentStatus $state): string => $state->color())
                    ->icon(fn (EnvironmentStatus $state): ?Htmlable => $state->icon()),

                TextColumn::make('runtime_status')
                    ->label('Runtime')
                    ->badge()
                    ->formatStateUsing(fn (RuntimeStatus $state): string => $state->label())
                    ->color(fn (RuntimeStatus $state): string => $state->color())
                    ->icon(fn (RuntimeStatus $state): ?Htmlable => $state->icon())
                    ->extraAttributes(fn (LabEnvironment $record): array => $record->hasStateMismatch()
                        ? ['title' => 'State mismatch: the record and the container disagree']
                        : []),

                TextColumn::make('tofu_workspace')
                    ->label('Workspace')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not assigned'),

                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->since()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // IMNEHNET_PAIR_SORT_V3
            // Group by normalized pair identity, attacker before victim.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "REPLACE(REPLACE(container_name, 'attacker-', ''), 'victim-', '') ASC"
                )
                ->orderByRaw(
                    "CASE
                        WHEN container_name LIKE 'attacker-%' THEN 0
                        WHEN container_name LIKE 'victim-%' THEN 1
                        ELSE 2
                    END ASC"
                )
                ->orderBy('container_name')
            )
            ->filters([
                SelectFilter::make('status')
                    ->label('Provisioning status')
                    ->options(collect(EnvironmentStatus::cases())
                        ->mapWithKeys(fn (EnvironmentStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                SelectFilter::make('runtime_status')
                    ->label('Runtime status')
                    ->options(collect(RuntimeStatus::cases())
                        ->mapWithKeys(fn (RuntimeStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                Filter::make('mismatch')
                    ->label('State mismatch only')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(function (Builder $query): void {
                            $query
```

**[Buka kode asli — app/Filament/Resources/Environments/Tables/EnvironmentsTable.php (L196–L260)](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/app/Filament/Resources/Environments/Tables/EnvironmentsTable.php#L196-L260)**

**Analisis implementasi:** Kolom **Provisioning** berasal dari `EnvironmentStatus`, sedangkan **Runtime** memakai `RuntimeStatus`; keduanya tidak dapat disamakan. Filter dan aksi lifecycle harus dinilai terhadap status masing-masing.

**Status verifikasi:** kode implementasi diperiksa di commit `b3370b5` dan sudah tersedia pada source. Tampilan halaman dapat diobservasi saat screenshot, tetapi pengujian aksi, integrasi layanan, pembatasan role, kondisi error, dan perubahan data baru berstatus **PASS** jika dicatat bukti input, keluaran aktual, serta hasilnya. Kode tidak diubah oleh dokumentasi.


## Peta Interaksi dan Data Antarmuka — Audit Final

| UI / aksi | Method, enum, atau komponen | Logika yang diperiksa |
|---|---|---|
| **Provisioning filter** | `EnvironmentStatus` | Status provisioning menjelaskan proses penyediaan; berbeda dari keadaan runtime. |
| **Runtime filter** | `RuntimeStatus` | Status runtime menampilkan hidup/mati/container; tidak setara dengan provisioning. |
| **Start / Stop / Reset** | `EnvironmentActions dan Table actions` | Aksi memicu lifecycle backend dengan validasi role/ownership dan kondisi environment. |
| **Delete** | `EnvironmentActions dan Table actions` | Aksi destruktif memerlukan verifikasi kepemilikan/konfirmasi dan pemeriksaan hasil penghapusan. |
| **Pair Terminal** | `EnvironmentsTable` | Alur khusus paired lab membuka hubungan terminal setelah state dan target valid. |

**[Buka kode tampilan/konfigurasi — app/Filament/Resources/Environments/Tables/EnvironmentsTable.php](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/app/Filament/Resources/Environments/Tables/EnvironmentsTable.php)**

**[Buka kode PHP/handler — app/Filament/Resources/Environments/Tables/EnvironmentActions.php](https://github.com/Alief1150/imnehnet-iac/blob/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf/webportal/app/Filament/Resources/Environments/Tables/EnvironmentActions.php)**

**Pengujian:** pengecekan kode dan UI dapat dinyatakan telah diaudit; hasil operasi setiap tombol, pembatasan role, dan interaksi dengan backend diberi PASS hanya apabila bukti aktual tersedia. Tidak menjalankan operasi destruktif pada server produksi untuk tujuan dokumentasi.
