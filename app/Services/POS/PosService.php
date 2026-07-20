<?php

namespace App\Services\POS;

use App\Models\ActivityLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Accounting\AccountingService;
use App\Services\Payment\PaymentService;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WalletService;
use App\Support\MoneyValueException;
use App\Support\Rupiah;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly AccountingService $accountingService,
        private readonly PaymentService $paymentService,
    ) {}

    public function createTransaction(array $payload, User $kasir): Transaction
    {
        $fingerprint = $this->requestFingerprint($payload);
        try {
            $transaction = $this->createTransactionAtomically($payload, $kasir, $fingerprint);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $transaction = Transaction::query()
                ->where('client_transaction_id', (string) Arr::get($payload, 'client_transaction_id'))
                ->first();

            if (! $transaction) {
                throw $exception;
            }

            $this->assertIdempotentPayloadMatches($transaction, $fingerprint);

            $transaction->load(['items', 'santri', 'location', 'kasir']);
        }

        return $this->initiateGatewayIfRequired($transaction, $payload, $kasir);
    }

    private function createTransactionAtomically(array $payload, User $kasir, string $fingerprint): Transaction
    {
        return DB::transaction(function () use ($payload, $kasir, $fingerprint) {
            $clientId = (string) Arr::get($payload, 'client_transaction_id');
            $locationId = (int) Arr::get($payload, 'location_id');
            $paymentMethod = (string) Arr::get($payload, 'payment_method', 'wallet');
            $this->assertCashierCanUseLocation($kasir, $locationId);

            $existing = Transaction::query()->where('client_transaction_id', $clientId)->first();
            if ($existing) {
                $this->assertIdempotentPayloadMatches($existing, $fingerprint);

                return $existing->load(['items', 'santri', 'location', 'kasir']);
            }

            $itemsPayload = Arr::get($payload, 'items', []);
            if (empty($itemsPayload)) {
                throw new PosTransactionException('VALIDATION_ERROR', 'Keranjang transaksi kosong.');
            }

            $santriId = Arr::get($payload, 'santri_id');
            if ($paymentMethod === 'wallet' && ! $santriId) {
                throw new PosTransactionException('SANTRI_REQUIRED', 'Pilih santri sebelum menggunakan saldo.');
            }

            $santri = $santriId ? Santri::query()->lockForUpdate()->findOrFail($santriId) : null;
            if ($santri && $santri->status !== 'active') {
                throw new PosTransactionException('SANTRI_INACTIVE', 'Santri tidak aktif.');
            }
            if ($paymentMethod === 'wallet' && $santri?->is_wallet_locked) {
                throw new PosTransactionException('WALLET_INACTIVE', 'Saldo santri sedang diblokir.');
            }

            $quantities = collect($itemsPayload)->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
            $products = Product::query()->whereIn('id', $quantities->keys()->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $quantities->count()) {
                throw new PosTransactionException('PRODUCT_NOT_FOUND', 'Salah satu produk tidak ditemukan.');
            }

            $total = 0;
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product->is_active || (int) $product->location_id !== $locationId) {
                    throw new PosTransactionException('PRODUCT_NOT_AVAILABLE_AT_LOCATION', "Produk {$product->name} tidak tersedia di lokasi kasir.");
                }
                if ((int) $product->stock < (int) $quantity) {
                    throw new PosTransactionException('PRODUCT_OUT_OF_STOCK', "Stok produk {$product->name} tidak mencukupi.", [
                        'product_id' => $product->id, 'available_stock' => (int) $product->stock,
                    ]);
                }
                $blocked = array_map('intval', $santri?->blocked_category_ids ?? []);
                $allowed = array_map('intval', $santri?->whitelisted_category_ids ?? []);
                if ($santri && $product->category_id && (in_array((int) $product->category_id, $blocked, true)
                    || ($allowed !== [] && ! in_array((int) $product->category_id, $allowed, true)))) {
                    throw new PosTransactionException('CATEGORY_NOT_ALLOWED', "Kategori produk {$product->name} tidak diizinkan.", ['product_id' => $product->id]);
                }
                $unitPrice = $this->money($product->sale_price, "products.{$product->id}.sale_price");
                if ($quantity > 0 && $unitPrice > intdiv(PHP_INT_MAX, (int) $quantity)) {
                    throw new PosTransactionException('INVALID_MONEY_VALUE', 'Total transaksi melampaui batas integer rupiah.', [], 500);
                }
                $total += $unitPrice * (int) $quantity;
            }

            if ($paymentMethod === 'wallet') {
                $availableBalance = $this->money($santri->wallet_balance, "santris.{$santri->id}.wallet_balance");
                if ($availableBalance < $total) {
                    throw new PosTransactionException('INSUFFICIENT_WALLET_BALANCE', 'Saldo santri tidak mencukupi.', [
                        'available_balance' => $availableBalance, 'required_balance' => $total,
                    ]);
                }
            }

            $cashReceived = $paymentMethod === 'cash'
                ? $this->money(Arr::get($payload, 'cash_received', 0), 'cash_received')
                : 0;
            if ($paymentMethod === 'cash' && $cashReceived < $total) {
                throw new PosTransactionException('INSUFFICIENT_CASH', 'Jumlah tunai yang diterima kurang dari total transaksi.', [
                    'cash_received' => $cashReceived, 'required_amount' => $total,
                ]);
            }

            $status = $paymentMethod === 'gateway' ? 'pending' : 'completed';
            $cashAmount = $paymentMethod === 'cash' ? $total : 0;
            $walletAmount = $paymentMethod === 'wallet' ? $total : 0;
            $gatewayAmount = $paymentMethod === 'gateway' ? $total : 0;

            $transaction = new Transaction([
                'reference' => 'POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'client_transaction_id' => $clientId,
                'type' => 'pos', 'channel' => 'counter', 'location_id' => $locationId,
                'status' => $status, 'cash_amount' => $cashAmount, 'wallet_amount' => $walletAmount,
                'gateway_amount' => $gatewayAmount, 'primary_payment_method' => $paymentMethod,
                'payment_breakdown' => [$paymentMethod => $total], 'requires_sync' => false,
                'processed_at' => now(),
                'notes' => Arr::get($payload, 'notes'),
                'metadata' => ['request_fingerprint' => $fingerprint],
                'sub_total' => $total, 'discount_amount' => 0, 'tax_amount' => 0,
                'total_amount' => $total,
                'paid_amount' => $paymentMethod === 'cash' ? $cashReceived : ($paymentMethod === 'wallet' ? $total : 0),
                'change_amount' => $paymentMethod === 'cash' ? $cashReceived - $total : 0,
            ]);
            $transaction->kasir()->associate($kasir);
            $transaction->santri()->associate($santri);
            $transaction->save();
            foreach ($quantities as $productId => $quantity) {
                $this->storeItem($transaction, $products->get($productId), (int) $quantity);
            }
            if ($paymentMethod === 'wallet') {
                try {
                    $this->walletService->debit($santri, $total, [
                        'performed_by' => $kasir,
                        'channel' => 'wallet',
                        'reference' => $transaction,
                        'idempotency_key' => $clientId,
                        'description' => 'Pembayaran POS',
                    ]);
                } catch (WalletException $exception) {
                    throw new PosTransactionException($exception->errorCode, $exception->getMessage());
                }
            }
            if ($status === 'completed' && ! $this->accountingService->recordPosTransaction($transaction)) {
                throw new PosTransactionException(
                    'ACCOUNTING_CONFIGURATION_MISSING',
                    'Transaksi belum dapat diproses karena konfigurasi akun keuangan belum lengkap. Hubungi administrator.',
                    [],
                    500,
                );
            }

            return $transaction->fresh(['items', 'santri', 'location', 'kasir']);
        }, attempts: 5);
    }

    private function initiateGatewayIfRequired(Transaction $transaction, array $payload, User $kasir): Transaction
    {
        if ($transaction->primary_payment_method !== 'gateway') {
            return $transaction;
        }

        $payment = $transaction->payments()->latest('id')->first();

        if (! $payment) {
            try {
                $payment = $this->paymentService->initiatePosGateway(
                    $transaction,
                    $this->money($transaction->total_amount, 'transaction.total_amount'),
                    Arr::get($payload, 'gateway_provider'),
                    ['redirect_to' => route('pos')],
                );
            } catch (\Throwable $exception) {
                $this->cancelTransaction($transaction, $kasir, 'Gateway gagal diinisialisasi');

                throw new PosTransactionException(
                    'PAYMENT_GATEWAY_UNAVAILABLE',
                    'Payment gateway belum tersedia atau belum dikonfigurasi. Hubungi administrator.',
                    [],
                    503,
                );
            }
        }

        $transaction->setAttribute('payment_redirect_url', Arr::get($payment->metadata, 'redirect_url'));
        $transaction->setAttribute('payment_status', $payment->status);

        return $transaction;
    }

    public function syncOfflinePayloads(array $payloads, User $kasir): array
    {
        throw new PosTransactionException(
            'OFFLINE_WALLET_NOT_ALLOWED',
            'Pembayaran saldo tidak dapat diproses dari antrean offline.',
            [],
            409,
        );
    }

    public function cancelTransaction(Transaction $transaction, User $actor, ?string $reason = null): Transaction
    {
        if ($transaction->status === 'cancelled') {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $transaction->loadMissing(['items', 'payments', 'santri']);

            if ($transaction->wallet_amount > 0 && $transaction->santri) {
                $originalDebit = WalletTransaction::query()
                    ->where('reference_type', Transaction::class)
                    ->where('reference_id', $transaction->id)
                    ->where('type', 'debit')
                    ->first();
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
                        'reversed_transaction_id' => $originalDebit?->id,
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
                        'unit_cost' => Rupiah::from($product->cost_price, 'product cost_price'),
                        'total_cost' => Rupiah::from($product->cost_price, 'product cost_price') * $item->quantity,
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

            $refundErrors = [];

            foreach ($transaction->payments as $payment) {
                $status = strtolower((string) $payment->status);

                if (in_array($status, ['settlement', 'capture', 'completed', 'paid', 'success'], true)) {
                    try {
                        $this->paymentService->requestRefund($payment, Rupiah::from($payment->amount, 'payment refund'), $reason);
                    } catch (\Throwable $exception) {
                        $refundErrors[] = [
                            'payment_id' => $payment->id,
                            'provider' => $payment->provider,
                            'message' => $exception->getMessage(),
                        ];
                    }

                    continue;
                }

                if (in_array($status, ['refunded', 'refund_pending'], true)) {
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
                'refund_errors' => empty($refundErrors) ? null : $refundErrors,
            ]);
            $transaction->save();

            $this->accountingService->reversePosTransaction($transaction, $reason);

            ActivityLog::log('cancelled', 'Pembatalan transaksi '.$transaction->reference, $transaction, [
                'reason' => $reason,
            ]);

            return $transaction;
        });
    }

    protected function storeItem(Transaction $transaction, Product $product, int $quantity): int
    {
        $unitPrice = $this->money($product->sale_price, "products.{$product->id}.sale_price");
        $unitCost = $this->money($product->cost_price, "products.{$product->id}.cost_price");

        $item = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id, 'product_name' => $product->name,
            'product_sku' => $product->sku, 'product_barcode' => $product->barcode,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0, 'subtotal' => $unitPrice * $quantity,
            'metadata' => ['unit_cost' => $unitCost],
        ]);
        $product->decrement('stock', $quantity);
        InventoryMovement::create([
            'product_id' => $product->id,
            'location_id' => $transaction->location_id,
            'type' => 'sale',
            'quantity_change' => $quantity * -1,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost * $quantity,
            'reference_type' => Transaction::class,
            'reference_id' => $transaction->id,
            'description' => 'Penjualan POS '.$transaction->reference,
            'metadata' => [
                'transaction_item_id' => $item->id,
                'unit_price' => $unitPrice,
            ],
            'recorded_at' => $transaction->processed_at ?? now(),
        ]);

        return $unitPrice * $quantity;
    }

    private function assertCashierCanUseLocation(User $cashier, int $locationId): void
    {
        if ($cashier->hasAnyRole(\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN)) {
            return;
        }

        if (! $cashier->hasRole(\App\Enums\UserRole::KASIR) || (int) $cashier->location_id !== $locationId) {
            throw new PosTransactionException(
                'CASHIER_LOCATION_NOT_ALLOWED',
                'Kasir tidak diizinkan memproses transaksi pada lokasi ini.',
                ['location_id' => $locationId],
                403,
            );
        }
    }

    private function money(mixed $value, string $field): int
    {
        try {
            return Rupiah::from($value, $field);
        } catch (MoneyValueException $exception) {
            throw new PosTransactionException('INVALID_MONEY_VALUE', $exception->getMessage(), ['field' => $field], 500);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if ($sqlState === '23505') {
            return true;
        }

        if ($sqlState !== '23000') {
            return false;
        }

        $vendorCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $vendorCode === 1062
            || str_contains($message, 'unique constraint failed');
    }

    private function requestFingerprint(array $payload): string
    {
        $items = collect(Arr::get($payload, 'items', []))
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => ['product_id' => (int) $productId, 'quantity' => (int) $rows->sum('quantity')])
            ->sortBy('product_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'location_id' => (int) Arr::get($payload, 'location_id'),
            'santri_id' => (int) Arr::get($payload, 'santri_id'),
            'payment_method' => (string) Arr::get($payload, 'payment_method', 'wallet'),
            'cash_received' => (int) Arr::get($payload, 'cash_received', 0),
            'gateway_provider' => (string) Arr::get($payload, 'gateway_provider', ''),
            'items' => $items,
        ], JSON_THROW_ON_ERROR));
    }

    private function assertIdempotentPayloadMatches(Transaction $transaction, string $fingerprint): void
    {
        $stored = $transaction->metadata['request_fingerprint'] ?? null;
        if ($stored !== null && ! hash_equals($stored, $fingerprint)) {
            throw new PosTransactionException(
                'IDEMPOTENCY_KEY_REUSED',
                'Client transaction ID sudah digunakan untuk payload yang berbeda.',
                [],
                409,
            );
        }
    }
}
