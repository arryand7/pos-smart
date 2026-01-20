<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'type',
        'channel',
        'location_id',
        'kasir_id',
        'santri_id',
        'status',
        'sub_total',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'cash_amount',
        'wallet_amount',
        'gateway_amount',
        'paid_amount',
        'change_amount',
        'primary_payment_method',
        'payment_breakdown',
        'requires_sync',
        'offline_reference',
        'processed_at',
        'synced_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sub_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'wallet_amount' => 'decimal:2',
            'gateway_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'requires_sync' => 'boolean',
            'payment_breakdown' => 'array',
            'processed_at' => 'datetime',
            'synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            $metadata = $transaction->metadata ?? [];
            if (! is_array($metadata)) {
                $metadata = (array) $metadata;
            }
            if (empty($metadata['verification_token'])) {
                $metadata['verification_token'] = Str::uuid()->toString();
                $transaction->metadata = $metadata;
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
