<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Santri;
use App\Models\Transaction;
use App\Services\Accounting\AccountingService;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Exceptions\PaymentProviderException;
use App\Services\Payment\Exceptions\PaymentProviderHttpException;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly AccountingService $accountingService
    ) {}

    public function initiateTopUp(Santri $santri, int|string $amount, ?string $providerKey = null, array $payload = []): Payment
    {
        $amount = Rupiah::from($amount, 'wallet top-up amount');
        $providerKey = $this->resolveProviderForCapability('wallet_topup', $providerKey);

        return DB::transaction(function () use ($santri, $amount, $providerKey, $payload) {
            $redirectTarget = Arr::pull($payload, 'redirect_to');

            $payment = new Payment([
                'provider' => $providerKey,
                'amount' => $amount,
                'currency' => 'IDR',
                'status' => 'pending',
                'channel' => $payload['channel'] ?? null,
            ]);

            $payment->santri()->associate($santri);
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'redirect_to' => $redirectTarget ?? url('/'),
            ]);
            $payment->save();

            $payload = $this->applyCallbackRoutes($payload, $payment);
            if ($providerKey === 'midtrans') {
                $payload = $this->applyMidtransDetails($santri, $payment, $payload);
            }

            return $this->manager->provider($providerKey)->createCharge($payment, $payload);
        });
    }

    public function initiatePosGateway(Transaction $transaction, int|string $amount, ?string $providerKey = null, array $payload = []): Payment
    {
        $amount = Rupiah::from($amount, 'POS gateway amount');
        $providerKey = $this->resolveProviderForCapability('pos_checkout', $providerKey);

        return DB::transaction(function () use ($transaction, $amount, $providerKey, $payload) {
            $redirectTarget = Arr::pull($payload, 'redirect_to');

            $payment = new Payment([
                'provider' => $providerKey,
                'amount' => $amount,
                'currency' => 'IDR',
                'status' => 'pending',
                'channel' => $payload['channel'] ?? 'pos',
            ]);

            $payment->payable()->associate($transaction);

            if ($transaction->santri) {
                $payment->santri()->associate($transaction->santri);
            }

            $payment->metadata = array_merge($payment->metadata ?? [], [
                'redirect_to' => $redirectTarget ?? route('pos'),
            ]);
            $payment->save();

            $payload = $this->applyCallbackRoutes($payload, $payment);

            if ($providerKey === 'midtrans') {
                $payload = $this->applyMidtransTransactionDetails($transaction, $payment, $payload);
            }

            return $this->manager->provider($providerKey)->createCharge($payment, $payload);
        });
    }

    public function handleWebhook(string $providerKey, Request $request): Payment
    {
        try {
            $payment = $this->manager->handleWebhook($providerKey, $request);
            $this->syncPaymentPayable($payment);

            PaymentWebhookLog::create([
                'provider' => $providerKey,
                'event' => $request->input('event') ?? $request->input('status'),
                'payload' => $request->all(),
                'signature' => $request->header('signature') ?? $request->header('x-signature'),
                'endpoint' => $request->path(),
                'http_status' => 200,
                'is_processed' => true,
                'received_at' => now(),
            ]);

            return $payment;
        } catch (InvalidSignatureException $exception) {
            $this->logWebhookFailure($providerKey, $request, 401, $exception->getMessage());

            throw $exception;
        } catch (PaymentProviderException $exception) {
            $this->logWebhookFailure($providerKey, $request, 400, $exception->getMessage());

            throw $exception;
        }
    }

    public function refreshPaymentStatus(Payment $payment): Payment
    {
        $payment = $this->manager->checkTransaction($payment);
        $this->syncPaymentPayable($payment);

        return $payment;
    }

    public function requestRefund(Payment $payment, int|string|null $amount = null, ?string $reason = null): Payment
    {
        $amount = Rupiah::from($amount ?? $payment->amount, 'payment refund');

        if ($amount <= 0) {
            return $payment;
        }

        if (in_array($payment->status, ['refunded', 'refund_pending'], true)) {
            return $payment;
        }

        $provider = $this->manager->provider($payment->provider);

        if (! method_exists($provider, 'refund')) {
            throw new PaymentProviderException('Provider tidak mendukung refund otomatis.');
        }

        try {
            return $provider->refund($payment, $amount, $reason);
        } catch (PaymentProviderHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PaymentProviderException($exception->getMessage());
        }
    }

    protected function logWebhookFailure(string $providerKey, Request $request, int $status, ?string $message = null): void
    {
        PaymentWebhookLog::create([
            'provider' => $providerKey,
            'event' => $request->input('event') ?? $request->input('status'),
            'payload' => $request->all(),
            'signature' => $request->header('signature') ?? $request->header('x-signature'),
            'endpoint' => $request->path(),
            'http_status' => $status,
            'is_processed' => false,
            'error_message' => $message,
            'received_at' => now(),
        ]);
    }

    protected function applyCallbackRoutes(array $payload, Payment $payment): array
    {
        $payload['return_url'] ??= route('payments.redirect', [
            'payment' => $payment->getKey(),
            'status' => 'success',
        ]);

        $payload['cancel_url'] ??= route('payments.redirect', [
            'payment' => $payment->getKey(),
            'status' => 'cancelled',
        ]);

        return $payload;
    }

    protected function applyMidtransDetails(Santri $santri, Payment $payment, array $payload): array
    {
        if (! Arr::get($payload, 'item_details')) {
            $items = Arr::get($payload, 'items');

            if (is_array($items)) {
                $payload['item_details'] = collect($items)->map(function ($item, $index) {
                    return [
                        'id' => (string) ($item['id'] ?? $item['sku'] ?? $index + 1),
                        'price' => (int) ($item['price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'name' => mb_strimwidth((string) ($item['name'] ?? 'Item'), 0, 50, ''),
                    ];
                })->values()->all();
            } elseif (isset($payload['product'], $payload['price'], $payload['qty'])) {
                $names = Arr::wrap($payload['product']);
                $prices = Arr::wrap($payload['price']);
                $qtys = Arr::wrap($payload['qty']);

                $payload['item_details'] = collect($names)->map(function ($name, $index) use ($prices, $qtys) {
                    return [
                        'id' => (string) ($index + 1),
                        'price' => (int) ($prices[$index] ?? 0),
                        'quantity' => (int) ($qtys[$index] ?? 1),
                        'name' => mb_strimwidth((string) $name, 0, 50, ''),
                    ];
                })->values()->all();
            } else {
                $payload['item_details'] = [[
                    'id' => (string) $payment->id,
                    'price' => (int) $payment->amount,
                    'quantity' => 1,
                    'name' => mb_strimwidth("Top-up {$santri->name}", 0, 50, ''),
                ]];
            }
        }

        if (! Arr::get($payload, 'customer_details')) {
            $wali = $santri->wali;
            $email = $santri->user?->email ?? $wali?->email;
            $phone = $wali?->phone ?? $wali?->alternate_phone;

            $payload['customer_details'] = array_filter([
                'first_name' => $santri->name,
                'email' => $email,
                'phone' => $phone,
                'billing_address' => $wali ? array_filter([
                    'first_name' => $wali->name,
                    'phone' => $wali->phone,
                    'address' => $wali->address,
                ]) : null,
            ]);
        }

        return $payload;
    }

    protected function applyMidtransTransactionDetails(Transaction $transaction, Payment $payment, array $payload): array
    {
        if (! Arr::get($payload, 'item_details')) {
            $items = Arr::get($payload, 'items');

            if (! is_array($items) || empty($items)) {
                $items = $transaction->items->map(function ($item) {
                    return [
                        'id' => (string) ($item->product_id ?? $item->id),
                        'price' => (int) $item->unit_price,
                        'quantity' => (int) $item->quantity,
                        'name' => mb_strimwidth((string) $item->product_name, 0, 50, ''),
                    ];
                })->values()->all();
            }

            $payload['item_details'] = collect($items)->map(function ($item, $index) {
                return [
                    'id' => (string) ($item['id'] ?? $item['sku'] ?? $index + 1),
                    'price' => (int) ($item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'name' => mb_strimwidth((string) ($item['name'] ?? 'Item POS'), 0, 50, ''),
                ];
            })->values()->all();
        }

        if (! Arr::get($payload, 'customer_details')) {
            $santri = $transaction->santri;
            $wali = $santri?->wali;
            $kasir = $transaction->kasir;

            $payload['customer_details'] = array_filter([
                'first_name' => $santri?->name ?? $kasir?->name ?? 'POS Customer',
                'email' => $santri?->user?->email ?? $wali?->email ?? $kasir?->email,
                'phone' => $wali?->phone ?? $santri?->user?->phone ?? $kasir?->phone,
                'billing_address' => $wali ? array_filter([
                    'first_name' => $wali->name,
                    'phone' => $wali->phone,
                    'address' => $wali->address,
                ]) : null,
            ]);
        }

        return $payload;
    }

    protected function resolveProviderForCapability(string $capability, ?string $preferred = null): string
    {
        if ($preferred) {
            $preferredConfig = \App\Models\PaymentProviderConfig::query()
                ->where('provider', $preferred)
                ->where('is_active', true)
                ->first();

            if ($preferredConfig && $this->providerConfigured($preferred, $preferredConfig->config ?? [])) {
                try {
                    $provider = $this->manager->provider($preferred);
                    if ($provider->supports($capability)) {
                        return $preferred;
                    }
                } catch (\Throwable $exception) {
                    // Fall through to active providers list.
                }
            }

            $preferred = null;
        }

        $activeProviders = \App\Models\PaymentProviderConfig::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($activeProviders as $providerConfig) {
            $key = $providerConfig->provider;
            if (! $this->providerConfigured($key, $providerConfig->config ?? [])) {
                continue;
            }

            try {
                $provider = $this->manager->provider($key);
            } catch (\Throwable $exception) {
                continue;
            }

            if ($provider->supports($capability)) {
                return $key;
            }
        }

        $fallback = config('smart.payments.default_provider', 'ipaymu');
        $provider = $this->manager->provider($fallback);

        if (! $provider->supports($capability)) {
            throw new PaymentProviderException('Belum ada payment gateway aktif yang mendukung fitur ini. Silakan atur di menu Payment Gateway.');
        }

        return $fallback;
    }

    protected function providerConfigured(string $providerKey, array $dbConfig): bool
    {
        return $this->manager->isProviderConfigured($providerKey, $dbConfig);
    }

    protected function syncPaymentPayable(Payment $payment): void
    {
        if (! $payment->payable) {
            return;
        }

        if ($payment->payable instanceof Transaction) {
            $transaction = $payment->payable;
            $status = strtolower((string) $payment->status);

            if ($transaction->status === 'cancelled') {
                return;
            }

            if (in_array($status, ['settlement', 'capture', 'completed', 'paid', 'success'], true)) {
                $transaction->status = 'completed';
                $transaction->paid_amount = $transaction->cash_amount + $transaction->wallet_amount + $transaction->gateway_amount;
                $transaction->change_amount = max(0, $transaction->paid_amount - $transaction->total_amount);
                $transaction->processed_at = $transaction->processed_at ?? now();
                $transaction->save();

                $this->accountingService->recordPosTransaction($transaction);

                return;
            }

            if (in_array($status, ['cancelled', 'cancel', 'expired', 'expire', 'deny', 'failed'], true)) {
                $transaction->status = 'cancelled';
                $transaction->save();
            }
        }
    }
}
