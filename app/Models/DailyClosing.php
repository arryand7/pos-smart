<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'closing_date',
        'location_id',
        'kasir_id',
        'cash_opening_balance',
        'cash_total_sales',
        'cash_total_topups',
        'cash_deposited',
        'cash_variance',
        'wallet_sales',
        'gateway_sales',
        'total_transactions',
        'transaction_count',
        'status',
        'notes',
        'report_path',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'cash_opening_balance' => 'decimal:2',
            'cash_total_sales' => 'decimal:2',
            'cash_total_topups' => 'decimal:2',
            'cash_deposited' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'wallet_sales' => 'decimal:2',
            'gateway_sales' => 'decimal:2',
            'total_transactions' => 'decimal:2',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
