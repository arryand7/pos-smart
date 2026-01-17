<?php

namespace App\Services\POS;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
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

            $transaction = new Transaction([
                'reference' => Arr::get($payload, 'reference', Str::upper(Str::random(10))),
                'type' => Arr::get($payload, 'type', 'pos'),
                'channel' => Arr::get($payload, 'channel', 'counter'),
                'location_id' => Arr::get($payload, 'location_id'),
                'status' => Arr::get($payload, 'status', 'completed'),
                'cash_amount' => Arr::get($payload, 'payments.cash', 0),
                'wallet_amount' => Arr::get($payload, 'payments.wallet', 0),
                'gateway_amount' => Arr::get($payload, 'payments.gateway', 0),
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
            $transaction->paid_amount = $transaction->cash_amount + $transaction->wallet_amount + $transaction->gateway_amount;
            $transaction->change_amount = max(0, $transaction->paid_amount - $transaction->total_amount);
            $transaction->save();

            if ($transaction->wallet_amount > 0 && $transaction->santri) {
                $this->walletService->debit($transaction->santri, $transaction->wallet_amount, [
                    'performed_by' => $kasir,
                    'channel' => 'wallet',
                    'reference' => $transaction,
                    'description' => 'Pembayaran POS',
                ]);
            }

            $this->accountingService->recordPosTransaction($transaction);

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
