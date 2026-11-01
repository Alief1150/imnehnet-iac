<?php

namespace App\Filament\Student\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class StudentProfile extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.student-profile';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'profile';

    public string $name = '';
    public string $email = '';
    public string $emailPassword = '';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';
    public $avatarUpload = null;

    public static function canAccess(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'student'
            && auth()->check();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->name = (string) auth()->user()->name;
        $this->email = (string) auth()->user()->email;
    }

    public function getTitle(): string { return 'Profile'; }
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
        session()->flash('profile_success', 'Profil berhasil diperbarui.');
        $this->profileToast('Profil berhasil diperbarui.');
    }

    // IMNEHNET_AVATAR_SECURE_FLYON_V2
    // IMNEHNET_STUDENT_PROFILE_V3: upload completes -> server validates and saves immediately.
    public function updatedAvatarUpload(): void
    {
        if ($this->avatarUpload !== null) {
            $this->saveAvatar();
        }
    }

    private function profileToast(string $message, string $type = 'success'): void
    {
        $this->dispatch('imnehnet-profile-toast', message: $message, type: $type);
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
            $this->addError('avatarUpload', 'Pemrosesan gambar tidak tersedia di server (PHP GD).');
            return;
        }

        // Decode and re-encode the image: never store raw uploaded bytes.
        // This strips embedded scripts, metadata and most image polyglot payloads.
        $image = @imagecreatefromstring(file_get_contents($temp));
        if ($image === false) {
            $this->addError('avatarUpload', 'Gambar tidak dapat diproses.');
            return;
        }
        $encoded = null;
        try {
            ob_start();
            $ok = imagewebp($image, null, 85);
            $encoded = ob_get_clean();
            if (! $ok || ! is_string($encoded) || $encoded === '') {
                $this->addError('avatarUpload', 'Gagal memproses gambar.');
                return;
            }
        } finally {
            imagedestroy($image);
        }

        $directory = 'student-avatars/'.$user->getKey();
        $path = $directory.'/'.(string) \Illuminate\Support\Str::uuid().'.webp';
        if (! \Illuminate\Support\Facades\Storage::disk('public')->put($path, $encoded)) {
            $this->addError('avatarUpload', 'Gagal menyimpan foto profil.');
            return;
        }
        $old = $user->avatar_url;
        try {
            $user->avatar_url = $path;
            $user->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            throw $e;
        }
        if ($old && str_starts_with($old, $directory.'/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }
        $this->reset('avatarUpload');
        session()->flash('profile_success', 'Foto profil berhasil diperbarui.');
        $this->profileToast('Foto profil berhasil diperbarui.');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $old = $user->avatar_url;
        $user->avatar_url = null;
        $user->save();
        if ($old && str_starts_with($old, 'student-avatars/'.$user->getKey().'/')) {
            Storage::disk('public')->delete($old);
        }
        $this->reset('avatarUpload');
        session()->flash('profile_success', 'Foto profil dihapus.');
        $this->profileToast('Foto profil dihapus.');
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
