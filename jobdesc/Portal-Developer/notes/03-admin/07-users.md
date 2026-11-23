# 03 — Admin Users: Dokumentasi Implementasi IMNEHNET

**PIC:** Septihadi Malik — Portal Developer  
**Source:** `Alief1150/imnehnet-iac` / `main`, audit 23 November 2026  
**Status:** Audit source selesai; bukti screenshot dan pengujian perlu dilengkapi

> Cuplikan berasal dari source asli dan diberi **komentar tambahan**. Potongan baris bukan file lengkap siap tempel. Source GitHub dan deployment server dapat berbeda jika ada patch lokal yang belum dipush.

## 1. Tujuan halaman

Resource akun untuk melihat, memfilter, dan mengelola User, role, status, environment, presence dan riwayat login. Form/Tabel menggunakan deklarasi Filament PHP.

## 2. Arsitektur dan fungsi komponen

Filament Resource/Page mendefinisikan data dan action di PHP; Blade (untuk halaman kustom) menampilkan markup dan event UI. Login dan pemeriksaan hak akses tetap harus dilakukan di server. Variabel state Livewire menghubungkan form/filter dengan method backend.

## 3. Penjelasan kode penting

### 3.1 UserResource.php

```php
// Penjelasan: Resource diikat ke model akun User.

    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

```

**Kode asli:** [UserResource.php, baris 22–34](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/UserResource.php#L22-L34).

**Fungsi:** Resource diikat ke model akun User. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.2 UsersTable.php

```php
// Penjelasan: Email disajikan dalam kolom tabel dan dapat dicari/disalin.

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    // The column is cast to a UserRole enum, so the value is
                    // unwrapped explicitly rather than assumed to be a string.
                    ->formatStateUsing(fn (mixed $state): string => self::asString($state))
                    ->color(fn (User $record): string => $record->isAdmin() ? 'teal' : 'gray')
                    ->sortable(),
```

**Kode asli:** [UsersTable.php, baris 38–50](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/Tables/UsersTable.php#L38-L50).

**Fungsi:** Email disajikan dalam kolom tabel dan dapat dicari/disalin. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.3 UsersTable.php

```php
// Penjelasan: Status Presence berasal dari user presence backend; patch Last Seen server perlu sinkron GitHub.

                TextColumn::make('presence')
                    ->label('Presence')
                    ->state(fn (User $record): string => \App\Support\UserPresence::online((int)$record->getKey()) ? 'Online' : 'Offline')
                    ->badge()
                    ->color(fn (User $record): string => \App\Support\UserPresence::online((int)$record->getKey()) ? 'success' : 'gray'),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('Never signed in')
                    ->sortable()
                    ->toggleable(),
```

**Kode asli:** [UsersTable.php, baris 67–79](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/Tables/UsersTable.php#L67-L79).

**Fungsi:** Status Presence berasal dari user presence backend; patch Last Seen server perlu sinkron GitHub. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.4 UserForm.php

```php
// Penjelasan: Input Email menggunakan validasi email serta unique record.

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('role')
                    ->label('Role')
                    ->options([
                        UserRole::Admin->value => 'Administrator',
                        UserRole::Student->value => 'Student',
                    ])
```

**Kode asli:** [UserForm.php, baris 21–33](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/Schemas/UserForm.php#L21-L33).

**Fungsi:** Input Email menggunakan validasi email serta unique record. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.5 UsersTable.php

```php
// Penjelasan: Hapus akun harus ditangani di sisi PHP, bukan hanya menghapus row.
                // quieter and less accountable path to the same outcome.
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (User $record): bool => ListUsers::mayDelete($record->getKey()))
                    ->requiresConfirmation()
                    ->modalHeading('Delete user and all owned labs?')
                    ->modalDescription('Account access will be disabled immediately. Attacker/Victim and standalone environments are queued for verified teardown. The account is deleted only after cleanup succeeds. This may take time.')
                    ->action(function (User $record): void {
                        try {
                            app(\App\Services\UserAccountDeletionService::class)->request(auth()->user(), $record);
                            \Filament\Notifications\Notification::make()->title('Cleanup queued')
```

**Kode asli:** [UsersTable.php, baris 109–121](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/Tables/UsersTable.php#L109-L121).

**Fungsi:** Hapus akun harus ditangani di sisi PHP, bukan hanya menghapus row. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

### 3.6 UsersTable.php

```php
// Penjelasan: Aksi View dibuat oleh Filament sesuai resource.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Two accounts are never offered a delete: the one being used, and
                // the last active administrator. The first would lock you out
                // mid-session and the second would leave nobody able to manage
                // the compute plane, so neither is something to confirm away.
                //
                // Deleting the account does not delete its environments. That is
                // deliberate: removing a container goes through the environment,
                // which runs OpenTofu, verifies the result and logs it. Removing
                // infrastructure as a side effect of removing a login would be a
                // quieter and less accountable path to the same outcome.
```

**Kode asli:** [UsersTable.php, baris 97–109](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Resources/Users/Tables/UsersTable.php#L97-L109).

**Fungsi:** Aksi View dibuat oleh Filament sesuai resource. Deklarasi di PHP digunakan Filament/Livewire untuk data dan tindakan. Perhatikan validasi, callback, serta nilai status dari model/service saat menguji.

## 4. Logika tombol, filter, dan data

- **Sumber data:** diambil dari model dan service melalui PHP; HTML/Blade hanya menerima hasil untuk ditampilkan.
- **Tombol/aksi:** konfigurasi Filament `Action::make(...)` atau direktif Livewire memanggil callback/method, termasuk pemeriksaan izin dan kondisi record.
- **Filter/state:** input atau select mengganti nilai state yang diproses pada query/method backend.
- **Hasil:** keberhasilan proses harus diperiksa pada status dan catatan sebenarnya; bukan hanya perubahan warna badge.

## 5. Screenshot dokumentasi

Upload ke `jobdesc/Portal-Developer/images/03-admin/07-users/`. Nama file di bawah harus sama persis:

![Gambar 1 — Users](../../images/03-admin/07-users/01-users-overview.png)

*Gambar 1. Users — users overview.*

![Gambar 2 — Users](../../images/03-admin/07-users/02-users-presence-last-seen.png)

*Gambar 2. Users — users presence last seen.*

![Gambar 3 — Users](../../images/03-admin/07-users/03-users-filter.png)

*Gambar 3. Users — users filter.*

![Gambar 4 — Users](../../images/03-admin/07-users/04-users-detail-edit.png)

*Gambar 4. Users — users detail edit.*

## 6. Pengujian dan evaluasi

### Tujuan dan Lingkungan Pengujian

Memastikan implementasi modul dapat ditelusuri dari route Laravel/Filament ke class PHP, tampilan Blade, serta metode/aksi backend yang digunakan. Pengujian berlangsung di **IAAS-MGMT** pada **23 November 2026** menggunakan aplikasi `/home/iaas/projects/webportal-iaas`. Metode berupa skrip **read-only** yang melakukan bootstrap Laravel, inspeksi method/source, dan membaca database bila relevan.

### Tabel Hasil Pengujian

| ID | Skenario pemeriksaan | Kriteria keberhasilan | Hasil aktual dari terminal | Status |
|---|---|---|---|---|
| AD-US-01 | Resource Users | GET `/admin/users` dan class Resource tersedia | Route `filament.admin.resources.users.index` terdaftar | PASS |
| AD-US-02 | Pengaturan formulir | Method `form()` dan `getPages()` tersedia | Method resource berhasil terdeteksi | PASS |
| AD-US-03 | Daftar dan detail pengguna | Method `table()` dan `infolist()` tersedia | Method resource berhasil terdeteksi | PASS |
| AD-US-04 | Konfigurasi tabel | `UsersTable::configure()` tersedia | Method pada source tabel terdeteksi | PASS |
| AD-US-05 | Deklarasi delete | Action delete terdefinisi | Nama aksi `delete` ditemukan di source | PASS |

**Interpretasi status:** **PASS** berarti pemeriksaan implementasi yang tercantum benar-benar lolos pada terminal. Ini **bukan** hasil klik dan eksekusi operasi produksi.

### Bukti dan Pembahasan

**Rekap Admin Panel V2:** 10 modul **PASS**, 146 pemeriksaan **PASS**, 0 FAIL; angka 146 adalah akumulasi seluruh Admin, bukan jumlah khusus modul ini. Waktu output terminal **05:04:41** (waktu server). Bukti terstruktur: **[Transkrip ringkas pengujian IAAS-MGMT](../evidence/2026-11-23-iaas-mgmt-verification.md)**. File bukti mengutip keluaran hasil terminal yang diberikan operator, termasuk route, method, dan binding yang terdeteksi. Tautan source PHP/Blade terdapat pada bagian implementasi dan referensi dokumen ini.

**Analisis:** seluruh baris yang diuji memenuhi kriteria berupa ketersediaan route, file/class, dan keterhubungan UI–handler sebagaimana cakupan script. Tidak ditemukan kegagalan di dalam pemeriksaan tersebut.

### Batasan dan Tindak Lanjut

Pengujian ini **tidak** menekan tombol pada browser, menjalankan perubahan data, membuka sesi terminal, mengubah environment, atau memvalidasi seluruh output service secara end-to-end. Karena itu, aksi nyata tersebut tidak diklaim PASS. Bukti screenshot terminal lengkap yang disensor dapat dilampirkan saat penyusunan laporan akhir.


## 7. Kesimpulan

Source Users telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Verifikasi implementasi pada server IAAS-MGMT telah PASS; pengujian runtime interaktif dan screenshot tetap dipisahkan.


---
