<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GateSyncBatch extends Model
{
    protected $fillable = ['uuid', 'initiated_by', 'status', 'gate_response_checksum', 'total_items', 'expires_at', 'applied_at', 'report_status', 'report_attempts', 'last_report_error', 'reported_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'applied_at' => 'datetime', 'reported_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GateSyncItem::class, 'batch_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
