<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wali extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'notifications_enabled',
        'notification_channels',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'notification_channels' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function santris(): HasMany
    {
        return $this->hasMany(Santri::class);
    }
}
