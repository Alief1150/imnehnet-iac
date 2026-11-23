# 04.06 — Profile Webportal IMNEHNET

**PIC:** Malik · **Panel:** Student · **Basis audit:** [commit `b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf), 23 November 2026.

**Status:** Kode implementasi terverifikasi di `main`. Bukti pengujian fungsional disajikan terpisah.

## 1. Deskripsi Pekerjaan

Profile menangani pemutakhiran nama/email, avatar, dan password. Validasi dan otorisasi dilakukan di metode PHP/Livewire; password tidak boleh dicatat dalam log atau screenshot.

## 2. Arsitektur dan Alur Pemrosesan

1. Route panel Student memanggil Page Filament dengan pembatasan akses untuk pengguna terautentikasi.
2. Class PHP memvalidasi input/kondisi akses dan menyiapkan state; handler aksi menyimpan data bila diotorisasi.
3. Blade menyusun halaman, menunjukkan pesan dan tombol sesuai kondisi, mengirim aksi Livewire jika tersedia.
4. UI harus menunjukkan keadaan sukses, gagal, dan kosong sesuai keadaan nyata, bukan placeholder.

## 3. Analisis Implementasi PHP dan Blade

Cuplikan berikut diambil dari source asli; seluruh penjelasan sengaja berada di luar cuplikan, sehingga tautan pada setiap blok bisa dibandingkan baris demi baris.

### 3.1 Method PHP — saveProfile()

Kode di bawah menangani bagian method php — saveprofile(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php
    public function getHeading(): string { return ''; }

    public function saveProfile(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->validate([
            'name' => ['required','string','min:2','max:255'],
            'email' => ['required','email','max:255',Rule::unique('users','email')->ignore($user->getKey())],
            'emailPassword' => [Rule::requiredIf(fn () => $this->email !== $user->email)],
        ]);
        if ($this->email !== $user->email && ! Hash::check($this->emailPassword, $user->password)) {
            $this->addError('emailPassword', 'Password saat ini tidak sesuai.');
            return;
        }
        $user->name = $this->name;
        if ($this->email !== $user->email) {
            $user->email = $this->email;
            $user->email_verified_at = null;
        }
        $user->save();
        $this->reset('emailPassword');
```

**[Lihat source `app/Filament/Student/Pages/StudentProfile.php` (L44–L67)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Student/Pages/StudentProfile.php#L44-L67)**

### 3.2 Method PHP — saveAvatar()

Kode di bawah menangani bagian method php — saveavatar(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php
    }

    public function saveAvatar(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $this->validate([
            'avatarUpload' => [
                'required', 'file', 'max:2048',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096',
            ],
        ]);

        $file = $this->avatarUpload;
        $temp = $file->getRealPath();
        $info = @getimagesize($temp);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->addError('avatarUpload', 'File harus berupa gambar JPG, PNG, atau WebP yang valid.');
            return;
        }
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
```

**[Lihat source `app/Filament/Student/Pages/StudentProfile.php` (L84–L107)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Student/Pages/StudentProfile.php#L84-L107)**

### 3.3 Method PHP — changePassword()

Kode di bawah menangani bagian method php — changepassword(). Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```php
    }

    public function changePassword(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $this->validate([
            'currentPassword' => ['required','current_password'],
            'newPassword' => ['required','same:newPasswordConfirmation', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        if (Hash::check($this->newPassword, $user->password)) {
            $this->addError('newPassword', 'Password baru harus berbeda dari password lama.');
            return;
        }
        $user->password = $this->newPassword;
        $user->save();
        $this->reset('currentPassword','newPassword','newPasswordConfirmation');
        session()->flash('profile_success', 'Password berhasil diperbarui.');
        $this->profileToast('Password berhasil diperbarui.');
    }
}

```

**[Lihat source `app/Filament/Student/Pages/StudentProfile.php` (L167–L188)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Student/Pages/StudentProfile.php#L167-L188)**

### 3.4 Blade — wire:model

Kode di bawah menangani bagian blade — wire:model. Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```blade
                                <small>JPG, PNG, atau WebP · maksimal 2 MB · otomatis tersimpan setelah dipilih.</small>
                                <small wire:loading wire:target="avatarUpload,saveAvatar" role="status">Memproses foto...</small>
                                <input id="im-avatar-file" class="im-avatar-flyon-input" type="file" wire:model="avatarUpload" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-label="Pilih gambar avatar" />
                            </div>
                        </div>
                        <p class="im-avatar-flyon-alert" role="alert" aria-live="polite" hidden></p>
                    </div>
                    @error('avatarUpload')<p class="im-profile-error" role="alert">{{ $message }}</p>@enderror
                    
                </form>
                
                <form wire:submit="saveProfile" class="im-profile-form">
                    <label for="im-profile-name">Nama lengkap</label><input id="im-profile-name" type="text" wire:model="name" maxlength="255" autocomplete="name" required>@error('name')<span class="im-profile-error">{{ $message }}</span>@enderror
                    <label for="im-profile-email">Alamat email</label><input id="im-profile-email" type="email" wire:model="email" maxlength="255" autocomplete="email" required>@error('email')<span class="im-profile-error">{{ $message }}</span>@enderror
                    <div class="im-profile-email-confirm"><label for="im-profile-email-pass">Konfirmasi password saat mengubah email</label><input id="im-profile-email-pass" type="password" wire:model="emailPassword" autocomplete="current-password" placeholder="Isi hanya jika mengubah email">@error('emailPassword')<span class="im-profile-error">{{ $message }}</span>@enderror</div>
                    <div class="im-profile-actions"><button class="btn btn-gradient btn-primary im-profile-btn im-profile-primary" type="submit" wire:loading.attr="disabled" wire:target="saveProfile">Simpan profil</button><button class="btn btn-gradient btn-secondary im-profile-btn" type="button" onclick="window.location.reload()">Batal</button></div>
                </form>
            </div>
        </section>
        <section class="im-profile-card card">
            <div class="im-profile-card-head"><div><h2>Change password</h2><p>Konfirmasi password saat ini sebelum menggantinya.</p></div>{!! $svg('shield-check') !!}</div>
            <form wire:submit="changePassword" class="im-profile-card-body im-profile-form">
```

**[Lihat source `resources/views/filament/pages/student-profile.blade.php` (L90–L111)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-profile.blade.php#L90-L111)**

### 3.5 Blade — @include

Kode di bawah menangani bagian blade — @include. Perhatikan argumen, validasi, pengembalian status, dan dependensi. Kode PHP menjalankan logika domain sedangkan Blade hanya menyusun tampilan serta interaksi yang disediakan.

```blade
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
        <main class="im-v4-content" id="im-v4-main">
<section class="im-profile" aria-label="Student account and security">
    <div class="im-profile-heading"><h1>Profile</h1><p>Kelola informasi pribadi, foto profil, dan keamanan akun Anda.</p></div>

    <section class="im-profile-summary card">
        <div class="im-profile-avatar im-profile-avatar-large">
            @if(auth()->user()?->getFilamentAvatarUrl())<img src="{{ auth()->user()->getFilamentAvatarUrl() }}" alt="Foto profil">@else{!! $svg('user-round') !!}@endif
        </div>
        <div class="im-profile-summary-identity"><h2>{{ auth()->user()?->name }}</h2><p>{{ auth()->user()?->email }}</p><div class="im-profile-pills"><span>Student</span><span>Active</span></div></div>
        <div class="im-profile-summary-caption"><strong>PERSONAL ACCOUNT</strong><small>Perubahan hanya untuk akun Anda.</small></div>
    </section>
    <div class="im-profile-grid">
        <section class="im-profile-card card">
            <div class="im-profile-card-head"><div><h2>Profile details</h2><p>Kelola identitas dan foto profil Anda.</p></div>{!! $svg('user-round') !!}</div>
            <div class="im-profile-card-body">
                {{-- IMNEHNET_AVATAR_SECURE_FLYON_V2: FlyonUI-style singleton upload, Livewire-owned request --}}
                <form wire:submit="saveAvatar" class="im-profile-avatar-form" enctype="multipart/form-data">
```

**[Lihat source `resources/views/filament/pages/student-profile.blade.php` (L48–L69)](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-profile.blade.php#L48-L69)**

## 4. Matriks Pengujian

| Aspek | Verifikasi yang diperlukan | Status |
|---|---|---|
| Halaman dan role | Halaman tersedia, akun yang tidak berhak ditolak | Source terverifikasi; hasil uji belum dilampirkan |
| Rendering Blade | Data dan tombol muncul sesuai kondisi | Perlu screenshot |
| Interaksi | Hasil backend sesuai masukan dan validasi | Perlu bukti uji |
| Negative case | Masukan tidak valid/akses tak berhak aman | Perlu bukti uji |

Akses website yang berhasil tidak mengesahkan seluruh skenario uji. Jangan mengklaim PASS tanpa rekaman hasil aktual.

## 5. Screenshot

Upload ke [`images/04-student/06-profile/`](../../images/04-student/06-profile/).

![Gambar 1 — Profile](../../images/04-student/06-profile/01-profile-overview.png)

![Gambar 2 — Profile](../../images/04-student/06-profile/02-profile-main.png)

![Gambar 3 — Profile](../../images/04-student/06-profile/03-profile-interaction.png)

![Gambar 4 — Profile](../../images/04-student/06-profile/04-profile-result.png)

## 6. Kesimpulan

Modul ini telah dikaitkan dengan implementasi PHP/Blade pada commit final. Penilaian keberhasilan sistem dilengkapi dengan tangkapan layar dan bukti pengujian nyata.

## Referensi Source

- [PHP](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/app/Filament/Student/Pages/StudentProfile.php)
- [Blade](https://github.com/Alief1150/imnehnet-iac/blob/main/webportal/resources/views/filament/pages/student-profile.blade.php)
