<?php

namespace App\Filament\Pages;

use App\Services\ActivityLogService;
use App\Support\Lucide;
use App\Support\ProjectOverview;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

final class SystemSettings extends Page
{
    use WithFileUploads;

    protected static ?string $navigationLabel = 'System Settings';
    protected static string|\UnitEnum|null $navigationGroup = 'OPERATIONS';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.system-settings';

    public string $tab = 'overview';
    public string $name = '';
    public string $email = '';
    public $photo = null;
    public int $photoInputKey = 0;
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';
    public bool $editingOverview = false;
    public array $overviewDraft = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->isActive() === true && $user->isAdmin();
    }

    private function account(): \App\Models\User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof \App\Models\User && $user->isActive() && $user->isAdmin(), 403);

        return $user;
    }

    private function requireAdmin(): \App\Models\User
    {
        $user = $this->account();
        abort_unless($user->isAdmin(), 403);

        return $user;
    }

    public function mount(): void
    {
        $this->cancelProfileChanges();
    }

    public function getTitle(): string { return 'System Settings'; }
    public function getSubheading(): ?string { return 'Tentang platform, teknologi, dan pengaturan akun Anda.'; }
    public static function getNavigationIcon(): Htmlable|string|null { return Lucide::icon('settings'); }

    public function selectTab(string $tab): void
    {
        $this->account();
        $this->tab = match ($tab) {
            'account-security', 'akun-keamanan' => 'account-security',
            default => 'overview',
        };
        $this->cancelPasswordChanges();
        $this->resetValidation();
    }

    public function getViewData(): array
    {
        $user = $this->account();

        return [
            'user' => $user,
            'overview' => ProjectOverview::read(),
            'technologyGroups' => $this->getTechnologyGroups(),
            'canEditOverview' => $user->isAdmin(),
        ];
    }

    public function getTechnologyGroups(): array
    {
        $groups = [];
        foreach (ProjectOverview::read()['software'] as $item) {
            $groups[$item['group']] ??= ['items' => []];
            $groups[$item['group']]['items'][] = [
                'name' => $item['name'],
                'purpose' => $item['purpose'],
                'version' => trim($item['version'] ?? '') ?: 'Belum dicatat',
            ];
        }

        return $groups;
    }

    public function editOverview(): void
    {
        $this->requireAdmin();
        $this->overviewDraft = ProjectOverview::read();
        $this->editingOverview = true;
        $this->resetValidation();
    }

    public function cancelOverviewChanges(): void
    {
        $this->editingOverview = false;
        $this->overviewDraft = [];
        $this->resetValidation();
    }

    public function addSoftware(): void
    {
        $this->requireAdmin();
        if (! $this->editingOverview || count($this->overviewDraft['software'] ?? []) >= 50) { return; }
        $this->overviewDraft['software'][] = ['name' => '', 'group' => 'Portal', 'version' => '', 'purpose' => ''];
    }

    public function removeSoftware(int $index): void
    {
        $this->requireAdmin();
        unset($this->overviewDraft['software'][$index]);
        $this->overviewDraft['software'] = array_values($this->overviewDraft['software']);
        $this->resetValidation();
    }

    public function saveOverview(): void
    {
        $user = $this->requireAdmin();
        $validated = $this->validate([
            'overviewDraft' => ['required', 'array:name,release,summary,objectives,architecture,features,team,institution,software'],
            'overviewDraft.name' => ['required', 'string', 'max:120'],
            'overviewDraft.release' => ['nullable', 'string', 'max:60'],
            'overviewDraft.summary' => ['required', 'string', 'max:4000'],
            'overviewDraft.objectives' => ['required', 'string', 'max:4000'],
            'overviewDraft.architecture' => ['required', 'string', 'max:6000'],
            'overviewDraft.features' => ['required', 'string', 'max:4000'],
            'overviewDraft.team' => ['nullable', 'string', 'max:3000'],
            'overviewDraft.institution' => ['required', 'string', 'max:255'],
            'overviewDraft.software' => ['present', 'array', 'max:50'],
            'overviewDraft.software.*' => ['array:name,group,version,purpose'],
            'overviewDraft.software.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'overviewDraft.software.*.group' => ['required', Rule::in(['Portal', 'Automation / Data', 'Virtualisasi / Lab', 'Monitoring', 'Security / Connectivity'])],
            'overviewDraft.software.*.version' => ['nullable', 'string', 'max:100'],
            'overviewDraft.software.*.purpose' => ['required', 'string', 'max:500'],
        ]);
        ProjectOverview::write($validated['overviewDraft']);
        app(ActivityLogService::class)->record(actor: $user, action: 'UPDATE_SETTINGS', resource: null, status: 'completed', description: 'Memperbarui dokumentasi proyek dan inventaris software IMNEHNET.');
        $this->cancelOverviewChanges();
        Notification::make()->title('Overview disimpan')->body('Informasi proyek dan versi teknologi telah diperbarui.')->success()->send();
    }

    public function cancelProfileChanges(): void
    {
        $user = $this->account();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->photo = null;
        $this->photoInputKey++;
        $this->resetValidation();
    }

    public function updatedPhoto(): void
    {
        $this->account();
        $this->validateOnly('photo', ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
    }

    public function updateProfile(): void
    {
        $user = $this->account();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $oldPhoto = $user->avatar_url;
        $newPhoto = null;
        if ($this->photo) {
            $newPhoto = $this->photo->store('avatars/'.$user->getKey(), 'public');
            if (! $newPhoto) { throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Coba kembali.']); }
        }
        try {
            $changes = ['name' => $data['name'], 'email' => $data['email']];
            if ($newPhoto) { $changes['avatar_url'] = $newPhoto; }
            if ($user->email !== $data['email']) { $changes['email_verified_at'] = null; }
            $user->forceFill($changes)->save();
        } catch (\Throwable $exception) {
            if ($newPhoto) { Storage::disk('public')->delete($newPhoto); }
            throw $exception;
        }
        // Delete only files in this account's managed upload directory.
        if ($newPhoto && $oldPhoto && str_starts_with($oldPhoto, 'avatars/'.$user->getKey().'/')) { Storage::disk('public')->delete($oldPhoto); }
        app(ActivityLogService::class)->record(actor: $user, action: 'UPDATE_USER', resource: $user, status: 'completed', description: 'Memperbarui profil akun sendiri melalui System Settings.');
        $this->cancelProfileChanges();
        Notification::make()->title('Profil diperbarui')->success()->send();
    }

    public function removePhoto(): void
    {
        $user = $this->account();
        $oldPhoto = $user->avatar_url;
        $user->update(['avatar_url' => null]);
        if ($oldPhoto && str_starts_with($oldPhoto, 'avatars/'.$user->getKey().'/')) { Storage::disk('public')->delete($oldPhoto); }
        $this->photo = null;
        $this->photoInputKey++;
        app(ActivityLogService::class)->record(actor: $user, action: 'UPDATE_USER', resource: $user, status: 'completed', description: 'Menghapus foto profil akun sendiri.');
        Notification::make()->title('Foto profil dihapus')->success()->send();
    }

    public function cancelPasswordChanges(): void
    {
        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->resetValidation();
    }

    
public function changePassword(): void

    {

        // im-admin-password-policy-v1

        $user = $this->account();

        $this->resetValidation();



        $this->validate([

            'current_password' => ['required', 'string'],

        ], [

            'current_password.required' => 'Masukkan password saat ini terlebih dahulu.',

        ]);



        if (! Hash::check($this->current_password, $user->password)) {

            Notification::make()

                ->title('Password saat ini salah')

                ->body('Password tidak cocok dengan akun Anda. Periksa kembali dan coba lagi.')

                ->danger()

                ->send();



            throw ValidationException::withMessages([

                'current_password' => 'Password saat ini salah. Perubahan belum disimpan.',

            ]);

        }



        $data = $this->validate([

            'new_password' => [

                'required', 'string', 'min:8', 'max:255', 'confirmed',

                'regex:/\p{Lu}/u',

                'regex:/[0-9]/',

                'regex:/[\p{P}\p{S}]/u',

            ],

            'new_password_confirmation' => ['required', 'string'],

        ], [

            'new_password.required' => 'Masukkan password baru.',

            'new_password.min' => 'Password baru minimal 8 karakter.',

            'new_password.max' => 'Password baru maksimal 255 karakter.',

            'new_password.regex' => 'Password baru harus mengandung huruf besar, angka, dan simbol, misalnya # atau $.',

            'new_password.confirmed' => 'Konfirmasi password baru tidak sama.',

            'new_password_confirmation.required' => 'Masukkan kembali password baru pada kolom konfirmasi.',

        ]);



        $user->update([

            'password' => Hash::make($data['new_password']),

            'must_change_password' => false,

        ]);



        session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());

        session()->regenerate();

        $this->cancelPasswordChanges();



        app(ActivityLogService::class)->record(

            actor: $user,

            action: 'UPDATE_USER',

            resource: $user,

            status: 'completed',

            description: 'Mengganti password akun sendiri melalui System Settings.',

        );



        Notification::make()

            ->title('Password berhasil diperbarui')

            ->success()

            ->send();

    }


    public function logout(): mixed
    {
        $this->account();
        $loginUrl = Filament::getCurrentPanel()->getLoginUrl();
        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect($loginUrl);
    }
}
