<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
// Role and status changes are audited by the model itself, so the entry is
// written whichever surface made the change. Declared here rather than in a
// service provider because the attribute is applied exactly once.
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The environments this account owns.
     *
     * A student may own more than one: the inventory keys ownership on a
     * nullable user_id, and an environment with no owner is an unassigned one
     * rather than an ownerless user's. The relation crosses database
     * connections on purpose, because the inventory lives in the infrastructure
     * database and accounts live in the application one.
     *
     * @return HasMany<LabEnvironment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(LabEnvironment::class, 'user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
