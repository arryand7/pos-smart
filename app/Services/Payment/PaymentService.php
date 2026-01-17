<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Santri;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Exceptions\PaymentProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly PaymentManager $manager)
    {
    }

    public function initiateTopUp(Santri $santri, float $amount, ?string $providerKey = null, array $payload = []): Payment
    {
        $providerKey = $providerKey ?: config('smart.payments.default_provider', 'ipaymu');

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

            return $this->manager->provider($providerKey)->createCharge($payment, $payload);
        });
    }

    public function handleWebhook(string $providerKey, Request $request): Payment
    {
        try {
            $payment = $this->manager->handleWebhook($providerKey, $request);

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
        return $this->manager->checkTransaction($payment);
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
}
