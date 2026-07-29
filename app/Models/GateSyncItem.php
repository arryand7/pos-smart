<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateSyncItem extends Model
{
    protected $fillable = ['batch_id', 'gate_user_uuid', 'local_user_id', 'category', 'recommended_action', 'selected_action', 'gate_payload', 'local_payload', 'differences', 'result_status', 'external_user_id', 'error_code', 'error_message'];

    protected function casts(): array
    {
        return ['gate_payload' => 'array', 'local_payload' => 'array', 'differences' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GateSyncBatch::class, 'batch_id');
    }

    public function localUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'local_user_id');
    }
}
