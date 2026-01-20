<?php

namespace App\Services\POS;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\ActivityLog;
use App\Services\Accounting\AccountingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly AccountingService $accountingService,
    ) {
    }

    public function createTransaction(array $payload, User $kasir): Transaction
    {
        return DB::transaction(function () use ($payload, $kasir) {
            $itemsPayload = Arr::get($payload, 'items', []);

            if (empty($itemsPayload)) {
                throw new \InvalidArgumentException('POS transaction requires at least one item.');
            }

            $gatewayAmount = (float) Arr::get($payload, 'payments.gateway', 0);
            $transactionStatus = $gatewayAmount > 0 ? 'pending' : Arr::get($payload, 'status', 'completed');

            $transaction = new Transaction([
                'reference' => Arr::get($payload, 'reference', Str::upper(Str::random(10))),
                'type' => Arr::get($payload, 'type', 'pos'),
                'channel' => Arr::get($payload, 'channel', 'counter'),
                'location_id' => Arr::get($payload, 'location_id'),
                'status' => $transactionStatus,
                'cash_amount' => Arr::get($payload, 'payments.cash', 0),
                'wallet_amount' => Arr::get($payload, 'payments.wallet', 0),
                'gateway_amount' => $gatewayAmount,
                'primary_payment_method' => Arr::get($payload, 'primary_payment_method'),
                'payment_breakdown' => Arr::get($payload, 'payments', []),
                'requires_sync' => Arr::get($payload, 'requires_sync', false),
                'offline_reference' => Arr::get($payload, 'offline_reference'),
                'processed_at' => Arr::get($payload, 'processed_at', now()),
                'notes' => Arr::get($payload, 'notes'),
                'metadata' => Arr::get($payload, 'metadata', []),
            ]);

            $transaction->kasir()->associate($kasir);

            if ($santriId = Arr::get($payload, 'santri_id')) {
                $santri = Santri::findOrFail($santriId);
                $transaction->santri()->associate($santri);
            }

            $transaction->save();

            $subTotal = 0;

            foreach ($itemsPayload as $itemPayload) {
                $subTotal += $this->storeItem($transaction, $itemPayload);
            }

            $transaction->sub_total = $subTotal;
            $transaction->discount_amount = Arr::get($payload, 'discount_amount', 0);
            $transaction->tax_amount = Arr::get($payload, 'tax_amount', 0);
            $transaction->total_amount = $subTotal - $transaction->discount_amount + $transaction->tax_amount;
            $transaction->paid_amount = $transaction->cash_amount + $transaction->wallet_amount;
            $transaction->change_amount = max(0, $transaction->paid_amount - $transaction->total_amount);
            if ($transaction->gateway_amount > 0) {
                $transaction->status = 'pending';
            }
            $transaction->save();

            if ($transaction->wallet_amount > 0 && $transaction->santri) {
                $this->walletService->debit($transaction->santri, $transaction->wallet_amount, [
                    'performed_by' => $kasir,
                    'channel' => 'wallet',
                    'reference' => $transaction,
                    'description' => 'Pembayaran POS',
                ]);
            }

            if ($transaction->status === 'completed') {
                $this->accountingService->recordPosTransaction($transaction);
            }

            return $transaction->fresh(['items']);
        });
    }

    public function syncOfflinePayloads(array $payloads, User $kasir): array
    {
        $results = [];

        foreach ($payloads as $payload) {
            $payload['requires_sync'] = false;
            $payload['status'] = 'completed';

            $results[] = $this->createTransaction($payload, $kasir);
        }

        return $results;
    }

    public function cancelTransaction(Transaction $transaction, User $actor, ?string $reason = null): Transaction
    {
        if ($transaction->status === 'cancelled') {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $transaction->loadMissing(['items', 'payments', 'santri']);

            if ($transaction->wallet_amount > 0 && $transaction->santri) {
                $alreadyRefunded = WalletTransaction::query()
                    ->where('reference_type', Transaction::class)
                    ->where('reference_id', $transaction->id)
                    ->where('type', 'credit')
                    ->exists();

                if (! $alreadyRefunded) {
                    $this->walletService->credit($transaction->santri, $transaction->wallet_amount, [
                        'performed_by' => $actor,
                        'channel' => 'wallet',
                        'reference' => $transaction,
                        'description' => 'Pembatalan transaksi '.$transaction->reference,
                        'metadata' => ['reversal' => true],
                    ]);
                }
            }

            $alreadyRestocked = InventoryMovement::query()
                ->where('reference_type', Transaction::class)
                ->where('reference_id', $transaction->id)
                ->where('type', 'cancel')
                ->exists();

            if (! $alreadyRestocked) {
                foreach ($transaction->items as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = Product::withTrashed()->find($item->product_id);
                    if (! $product) {
                        continue;
                    }

                    $product->increment('stock', $item->quantity);

                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'location_id' => $transaction->location_id,
                        'type' => 'cancel',
                        'quantity_change' => $item->quantity,
                        'unit_cost' => $product->cost_price,
                        'total_cost' => $product->cost_price * $item->quantity,
                        'reference_type' => Transaction::class,
                        'reference_id' => $transaction->id,
                        'description' => 'Pembatalan transaksi '.$transaction->reference,
                        'metadata' => [
                            'transaction_item_id' => $item->id,
                            'reversal' => true,
                        ],
                        'recorded_at' => now(),
                    ]);
                }
            }

            foreach ($transaction->payments as $payment) {
                if (in_array($payment->status, ['settlement', 'capture', 'completed', 'paid', 'success'], true)) {
                    continue;
                }

                $payment->status = 'cancelled';
                $payment->cancelled_at = now();
                $payment->save();
            }

            $transaction->status = 'cancelled';
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'cancelled_by' => $actor->id,
                'cancelled_at' => now()->toISOString(),
                'cancel_reason' => $reason,
            ]);
            $transaction->save();

            $this->accountingService->reversePosTransaction($transaction, $reason);

            ActivityLog::log('cancelled', 'Pembatalan transaksi '.$transaction->reference, $transaction, [
                'reason' => $reason,
            ]);

            return $transaction;
        });
    }

    protected function storeItem(Transaction $transaction, array $payload): float
    {
        $product = isset($payload['product_id']) ? Product::find($payload['product_id']) : null;

        $quantity = (int) Arr::get($payload, 'quantity', 1);
        $unitPrice = (float) Arr::get($payload, 'unit_price', $product?->sale_price ?? 0);
        $discount = (float) Arr::get($payload, 'discount_amount', 0);

        $item = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? Arr::get($payload, 'product_name', 'Produk POS'),
            'product_sku' => $product?->sku,
            'product_barcode' => $product?->barcode,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'subtotal' => ($unitPrice * $quantity) - $discount,
            'metadata' => Arr::get($payload, 'metadata', []),
        ]);

        if ($product) {
            $product->decrement('stock', $quantity);

            InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $transaction->location_id,
                'type' => 'sale',
                'quantity_change' => $quantity * -1,
                'unit_cost' => $product->cost_price,
                'total_cost' => $product->cost_price * $quantity,
                'reference_type' => Transaction::class,
                'reference_id' => $transaction->id,
                'description' => 'Penjualan POS '.$transaction->reference,
                'metadata' => [
                    'transaction_item_id' => $item->id,
                    'unit_price' => $unitPrice,
                ],
                'recorded_at' => $transaction->processed_at ?? now(),
            ]);
        }

        return ($unitPrice * $quantity) - $discount;
    }
}
