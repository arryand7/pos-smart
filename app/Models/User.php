<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'roles',
        'preferences',
        'password',
        'sso_sub',
        'sso_synced_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'roles' => 'array',
            'preferences' => 'array',
            'sso_synced_at' => 'datetime',
        ];
    }

    public function santri(): HasOne
    {
        return $this->hasOne(Santri::class);
    }

    public function wali(): HasOne
    {
        return $this->hasOne(Wali::class);
    }

    public function cashierTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'kasir_id');
    }

    public function performedWalletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'performed_by');
    }

    public function dailyClosings(): HasMany
    {
        return $this->hasMany(DailyClosing::class, 'kasir_id');
    }

    public function hasRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        $roles = collect($this->roles ?? [])
            ->filter()
            ->map(fn ($item) => $item instanceof UserRole ? $item->value : (string) $item)
            ->unique();

        if ($roles->contains($value)) {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        if ($this->role instanceof UserRole) {
            return $this->role->value === $value;
        }

        return $this->role === $value;
    }

    public function hasAnyRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
