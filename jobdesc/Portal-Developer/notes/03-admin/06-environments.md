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

### Tujuan dan Lingkungan Pengujian

Memastikan implementasi modul dapat ditelusuri dari route Laravel/Filament ke class PHP, tampilan Blade, serta metode/aksi backend yang digunakan. Pengujian berlangsung di **IAAS-MGMT** pada **23 November 2026** menggunakan aplikasi `/home/iaas/projects/webportal-iaas`. Metode berupa skrip **read-only** yang melakukan bootstrap Laravel, inspeksi method/source, dan membaca database bila relevan.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| AD-EN-01 | Resource Environments | GET `/admin/environments` dan class Resource tersedia | Route `filament.admin.resources.environments.index` terdaftar | PASS |
| AD-EN-02 | Tabel dan form | Method table/form/infolist/getPages tersedia | Method resource terdeteksi | PASS |
| AD-EN-03 | Source action Filament | Tabel resource menyediakan konfigurasi aksi | `EnvironmentsTable::configure()` ditemukan | PASS |
| AD-EN-04 | Start/Stop environment | Deklarasi aksi start/stop tersedia | `startAdminPairV2` dan `stopAdminPairV2` tercatat di output | PASS |
| AD-EN-05 | Reset/Delete dan terminal | Deklarasi aksi terkait tersedia | `resetAdminPair`, `deleteAdminPair`, `adminStandalonePairTerminal` ditemukan | PASS |

**Interpretasi status:** **PASS** berarti pemeriksaan implementasi yang tercantum benar-benar lolos pada terminal. Ini **bukan** hasil klik dan eksekusi operasi produksi.

### Bukti dan Pembahasan

**Rekap Admin Panel V2:** 10 modul **PASS**, 146 pemeriksaan **PASS**, 0 FAIL; angka 146 adalah akumulasi seluruh Admin, bukan jumlah khusus modul ini. Waktu output terminal **05:04:41** (waktu server). Bukti terstruktur: **[Transkrip ringkas pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md)**. File bukti mengutip keluaran hasil terminal yang diberikan operator, termasuk route, method, dan binding yang terdeteksi. Tautan source PHP/Blade terdapat pada bagian implementasi dan referensi dokumen ini.

**Analisis:** seluruh baris yang diuji memenuhi kriteria berupa ketersediaan route, file/class, dan keterhubungan UI–handler sebagaimana cakupan script. Tidak ditemukan kegagalan di dalam pemeriksaan tersebut.

### Batasan dan Tindak Lanjut

Pengujian ini **tidak** menekan tombol pada browser, menjalankan perubahan data, membuka sesi terminal, mengubah environment, atau memvalidasi seluruh output service secara end-to-end. Karena itu, aksi nyata tersebut tidak diklaim PASS. Bukti screenshot terminal lengkap yang disensor dapat dilampirkan saat penyusunan laporan akhir.


## 7. Kesimpulan

Source Environments telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Verifikasi implementasi pada server IAAS-MGMT telah PASS; pengujian runtime interaktif dan screenshot tetap dipisahkan.


---
