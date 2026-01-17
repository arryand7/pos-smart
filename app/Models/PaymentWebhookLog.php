<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event',
        'signature',
        'endpoint',
        'http_status',
        'is_processed',
        'error_message',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'is_processed' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }
}
