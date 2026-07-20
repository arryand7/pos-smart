<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Santri extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'wali_id',
        'nis',
        'qr_code',
        'photo_path',
        'nisn',
        'name',
        'nickname',
        'gender',
        'class',
        'dormitory',
        'status',
        'wallet_balance',
        'daily_limit',
        'weekly_limit',
        'monthly_limit',
        'limit_reset_at',
        'is_wallet_locked',
        'blocked_category_ids',
        'whitelisted_category_ids',
        'metadata',
        'notes',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'daily_limit' => 'decimal:2',
            'weekly_limit' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
            'limit_reset_at' => 'datetime',
            'is_wallet_locked' => 'boolean',
            'blocked_category_ids' => 'array',
            'whitelisted_category_ids' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wali(): BelongsTo
    {
        return $this->belongsTo(Wali::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path || ! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        if (file_exists(public_path('storage'))) {
            return Storage::disk('public')->url($this->photo_path);
        }

        $path = str_starts_with($this->photo_path, 'santris/')
            ? substr($this->photo_path, strlen('santris/'))
            : $this->photo_path;

        return url('/media/santris/'.$path);
    }
}
