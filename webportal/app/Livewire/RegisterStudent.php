<?php

namespace App\Livewire;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewStudentRegistered;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Student self-registration.
 *
 * Registration is open to anyone who can reach the portal, which is why three
 * things are decided here rather than being left to the form.
 *
 * The role is assigned here and nowhere else. 'role' is fillable, because an
 * administrator has to be able to create an administrator from the Users page,
 * so a form that mass-assigned the submitted array could set it. The properties
 * are therefore assigned one at a time and nothing from the request is ever run
 * through fill(). There is no field for a role on this form and there is no path
 * by which one could arrive, so a self-registered account is a student or the
 * registration fails.
 *
 * The account is created active, because the specification is that a student can
 * start practising without waiting for anyone. What that costs is bounded by the
 * per-student environment quota rather than by an approval step.
 *
 * Attempts are rate limited per address and address together. Password reset
 * covers an existing account, so this endpoint has no other throttle and an
 * unbounded one would let anyone enumerate which addresses have accounts.
 */
final class RegisterStudent extends Component
{
    /** Attempts allowed from one address for one address before a cooldown. */
    private const ATTEMPTS = 5;

    /** Seconds before another attempt is allowed. */
    private const DECAY_SECONDS = 3600;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @throws ValidationException
     */
    public function register(ActivityLogService $activity): void
    {
        $email = Str::lower(trim($this->email));
        $key = $this->throttleKey($email);

        // Checked before validating, and counted on every attempt including the
        // ones that fail. Validating first would mean a submission that never
        // reached the limiter, so the endpoint could be driven as fast as the
        // network allows to find out which addresses already have accounts and
        // to create nothing at all.
        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Too many registration attempts from this browser. Try again in %d minutes.',
                    (int) ceil($seconds / 60),
                ),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            // The length is the floor; the other three rules carry the strength.
            // Stated one at a time, because a field with four rules that reports
            // only that validation failed teaches nothing.
            'password.min' => 'Use at least 8 characters.',
            // Stated one rule at a time, because "failed validation" on a field
            // with four rules tells the student none of them.
            'password.mixed' => 'Add at least one uppercase letter.',
            'password.numbers' => 'Add at least one number.',
            'password.symbols' => 'Add at least one symbol, such as # or $.',
        ]);

        // Assigned property by property. Nothing from the request reaches fill(),
        // so the role and the status cannot be chosen by whoever submits it.
        $user = new User;
        $user->name = trim($this->name);
        $user->email = $email;
        $user->password = Hash::make($this->password);
        $user->role = UserRole::Student;
        $user->status = UserStatus::Active;
        $user->must_change_password = false;
        $user->save();

        RateLimiter::clear($key);

        // Recorded against the account that was just created rather than against
        // the session, because the point of the entry is that an unauthenticated
        // request produced an account. The Activity Logs page can then separate
        // a student who arrived from an administrator who made one.
        $activity->record(
            actor: $user,
            action: ActivityAction::Registered,
            resource: $user,
            status: 'completed',
            description: sprintf('Self-registered as a student from %s.', $this->ip()),
            metadata: ['role' => UserRole::Student->value],
        );

        Auth::login($user);

        // Notify all administrators about the new student registration.
        foreach (User::query()->where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->get() as $admin) {
            $admin->notify(new NewStudentRegistered($user));
        }

        // /student, not /admin. The account created here is a student, and
        // sending it to the administrative panel said otherwise the moment it
        // was created.
        $this->redirect(route('filament.student.pages.dashboard'), navigate: false);
    }

    private function throttleKey(string $email): string
    {
        return 'register:'.Str::transliterate($email).'|'.$this->ip();
    }

    private function ip(): string
    {
        return request()->ip() ?? 'unknown';
    }

    public function render()
    {
        return view('livewire.register-student');
    }
}
