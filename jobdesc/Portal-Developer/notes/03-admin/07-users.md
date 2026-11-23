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


### Hasil Pengujian Implementasi Admin — PASS (23 November 2026)

**Server:** `iaas-mgmt` · **Project:** `/home/iaas/projects/webportal-iaas` · **Metode:** verifikasi PHP read-only terhadap Laravel GET routes, class/method, Blade, binding `wire:click`/`wire:submit`, deklarasi aksi Filament Resource, dan indikasi validasi/otorisasi/pemanggilan service.

**Hasil pada modul users: PASS.** Seluruh **10 dari 10 modul Admin PASS**, dengan **146 pemeriksaan PASS dan 0 FAIL** pada script *Admin Panel Deep Verification V2*, dieksekusi **23 November 2026 pukul 05:04:41** (waktu tercetak di terminal server). Angka 146 merupakan **total lintas 10 modul**, bukan 146 pengujian khusus modul ini.

**Makna hasil:** halaman, class, method dan relasi UI–handler yang diperiksa tersedia. Sinyal seperti `authorization-check`, `validation` dan `service-reference` adalah hasil pembacaan source, **bukan** eksekusi seluruh cabang logika.

**Batas pembuktian:** belum dilakukan klik browser end-to-end, perubahan database, eksekusi action lifecycle lab, pembuatan sesi terminal, atau penyimpanan konfigurasi. Perilaku runtime tidak dinyatakan PASS dari pemeriksaan statis saja.


| ID | Skenario | Target | Status |
|---|---|---|---|
| 01 | Admin membuka modul | Halaman tampil | Bukti hasil uji belum dilampirkan |
| 02 | Pengguna non-Admin mengakses | Akses sesuai kebijakan role | Bukti hasil uji belum dilampirkan |
| 03 | Search/filter/sort (jika tersedia) | Hasil sesuai data | Bukti hasil uji belum dilampirkan |
| 04 | Aksi pada data uji | Validasi dan perubahan backend benar | Bukti hasil uji belum dilampirkan |

**Risiko:** Source terbaru sudah menyertakan migrasi Last Seen; hasil UI perlu diverifikasi. Jangan unggah screenshot berisi data pribadi tanpa disensor.

## 7. Kesimpulan

Source Users telah diaudit dan dapat ditelusuri melalui 6 tautan langsung. Hasil pengujian runtime, screenshot, dan sinkronisasi dengan server aktif tetap perlu dilengkapi sebelum status dokumen final.


---
