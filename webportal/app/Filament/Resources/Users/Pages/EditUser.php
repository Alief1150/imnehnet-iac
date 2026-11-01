<?php



namespace App\Filament\Resources\Users\Pages;



use App\Enums\UserRole;

use App\Filament\Resources\Users\UserResource;

use App\Models\User;

use App\Services\ActivityLogService;

use Filament\Actions\Action;

use Filament\Forms\Components\TextInput;

use Filament\Notifications\Notification;

use Filament\Resources\Pages\EditRecord;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Gate;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\RateLimiter;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Str;

use Illuminate\Validation\Rules\Password;



class EditUser extends EditRecord

{

    protected static string $resource = UserResource::class;



    protected function mutateFormDataBeforeFill(array $data): array

    {

        unset($data['password'], $data['remember_token']);



        return $data;

    }



    protected function handleRecordUpdate(Model $record, array $data): Model

    {

        $record->refresh();

        Gate::authorize('update', $record);



        // Perubahan password hanya melalui dua aksi khusus.

        unset($data['password'], $data['remember_token'], $data['must_change_password']);



        return parent::handleRecordUpdate($record, $data);

    }



    protected function getHeaderActions(): array

    {

        return [

            $this->studentPasswordAction(false),

            $this->studentPasswordAction(true),

        ];

    }



    protected function studentPasswordAction(bool $reset): Action

    {

        return Action::make($reset ? 'resetStudentPassword' : 'changeStudentPassword')

            ->label($reset ? 'Reset password' : 'Ganti password')

            ->color($reset ? 'warning' : 'primary')

            ->visible(fn (): bool =>

                $this->getRecord()->role === UserRole::Student

                && Gate::allows('update', $this->getRecord())

            )

            ->modalHeading($reset ? 'Reset password student' : 'Ganti password student')

            ->modalDescription($reset

                ? 'Untuk student yang lupa password. Konfirmasikan dengan password admin yang sedang login.'

                : 'Masukkan password lama student, lalu tentukan password baru.')

            ->modalSubmitActionLabel($reset ? 'Reset password' : 'Ganti password')

            ->schema([

                TextInput::make('verification_password')

                    ->label($reset ? 'Password admin saat ini' : 'Password lama student')

                    ->password()->revealable()->required()->maxLength(1024),

                TextInput::make('new_password')

                    ->label('Password baru student')

                    ->password()->revealable()->required()->maxLength(72)

                    ->rules([Password::min(8)->mixedCase()->numbers()->symbols()])

                    ->helperText('Minimal 8 karakter: huruf besar, huruf kecil, angka, dan simbol.'),

                TextInput::make('new_password_confirmation')

                    ->label('Konfirmasi password baru')

                    ->password()->revealable()->required()

                    ->same('new_password'),

            ])

            ->action(function (array $data, Action $action) use ($reset): void {

                $actor = User::query()->findOrFail(auth()->id());

                $subject = User::query()->findOrFail($this->getRecord()->getKey());



                Gate::forUser($actor)->authorize('update', $subject);

                abort_unless($subject->role === UserRole::Student, 403);



                $key = 'student-password:'.$actor->getKey().':'.$subject->getKey();



                if (RateLimiter::tooManyAttempts($key, 5)) {

                    Notification::make()->danger()

                        ->title('Terlalu banyak percobaan')

                        ->body('Coba lagi dalam '.RateLimiter::availableIn($key).' detik.')

                        ->send();

                    $action->halt();

                }



                RateLimiter::hit($key, 60);



                if (Validator::make($data, [

                    'verification_password' => ['required', 'string', 'max:1024'],

                    'new_password' => [

                        'required', 'string', 'max:72', 'confirmed',

                        Password::min(8)->mixedCase()->numbers()->symbols(),

                    ],

                ])->fails()) {

                    Notification::make()->danger()

                        ->title('Password baru belum memenuhi aturan atau konfirmasi tidak cocok.')

                        ->send();

                    $action->halt();

                }



                $error = $subject->getConnection()->transaction(

                    function () use ($actor, $subject, $data, $reset): ?string {

                        $target = User::query()->whereKey($subject->getKey())

                            ->lockForUpdate()->firstOrFail();

                        $operator = User::query()->findOrFail($actor->getKey());



                        Gate::forUser($operator)->authorize('update', $target);

                        abort_unless($target->role === UserRole::Student, 403);



                        $hash = $reset ? $operator->password : $target->password;



                        if (! Hash::check($data['verification_password'], $hash)) {

                            return $reset

                                ? 'Password admin salah.'

                                : 'Password lama student salah.';

                        }



                        if (Hash::check($data['new_password'], $target->password)) {

                            return 'Password baru harus berbeda dari password lama.';

                        }



                        $target->password = $data['new_password'];

                        $target->remember_token = Str::random(60);

                        $target->must_change_password = $reset;

                        $target->save();



                        app(ActivityLogService::class)->record(

                            actor: $operator,

                            action: $reset ? 'RESET_USER_PASSWORD' : 'CHANGE_USER_PASSWORD',

                            resource: $target,

                            status: 'completed',

                            description: $reset

                                ? 'Admin mereset password akun student.'

                                : 'Admin mengganti password student setelah verifikasi password lama.',

                        );



                        return null;

                    }

                );



                if ($error !== null) {

                    Notification::make()->danger()

                        ->title('Perubahan ditolak')->body($error)->send();

                    $action->halt();

                }



                RateLimiter::clear($key);

                $this->getRecord()->refresh();



                Notification::make()->success()

                    ->title($reset ? 'Password berhasil direset' : 'Password berhasil diganti')

                    ->send();

            });

    }

}

