<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'daily_limit',
        'weekly_limit',
        'monthly_limit',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'daily_limit' => 'decimal:2',
            'weekly_limit' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
            'is_locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
