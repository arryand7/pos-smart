<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProviderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'name',
        'is_active',
        'priority',
        'config',
        'sandbox_config',
        'webhook_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
            'sandbox_config' => 'array',
            'metadata' => 'array',
        ];
    }
}
