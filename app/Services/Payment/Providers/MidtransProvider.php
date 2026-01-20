<?php

namespace App\Services\Payment\Providers;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Exceptions\PaymentProviderException;
use App\Services\Payment\Exceptions\PaymentProviderHttpException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MidtransProvider implements PaymentProvider
{
    protected array $config = [];

    public function getProviderKey(): string
    {
        return 'midtrans';
    }

    public function boot(array $config = []): void
    {
        $this->config = $config;
    }

    public function createCharge(Payment $payment, array $payload = []): Payment
    {
        $this->assertConfigured();

        $orderId = $this->normalizeOrderId(Arr::get($payload, 'transaction_details.order_id'), $payment);

        $body = array_merge([
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) ($payment->amount ?? Arr::get($payload, 'amount', 0)),
            ],
            'credit_card' => [
                'secure' => true,
            ],
        ], $payload);
        $body['transaction_details']['order_id'] = $orderId;
        $body['transaction_details']['gross_amount'] = (int) ($body['transaction_details']['gross_amount'] ?? $payment->amount ?? 0);

        $response = $this->snapHttp()->post($this->snapEndpoint('/snap/v1/transactions'), $body);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'Midtrans create transaction failed.');
        }

        $data = $response->json();

        $payment->provider = $this->getProviderKey();
        $payment->provider_reference = $orderId;
        $payment->status = 'pending';
        $payment->request_payload = array_merge($payment->request_payload ?? [], $body);
        $payment->response_payload = array_merge($payment->response_payload ?? [], $data);
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'redirect_url' => $data['redirect_url'] ?? null,
            'token' => $data['token'] ?? null,
            'mode' => $this->config['mode'] ?? 'sandbox',
        ]);
        $payment->save();

        return $payment;
    }

    public function handleWebhook(Request $request): Payment
    {
        $orderId = $request->input('order_id');

        $payment = Payment::query()
            ->where('provider', $this->getProviderKey())
            ->where('provider_reference', $orderId)
            ->firstOrFail();

        $this->validateSignature($request->input('signature_key'), $request->all());

        $payment->status = strtolower($request->input('transaction_status', $payment->status ?? 'pending'));
        $payment->response_payload = array_merge($payment->response_payload ?? [], $request->all());

        if (in_array($payment->status, ['settlement', 'capture'], true)) {
            $payment->paid_at = $payment->paid_at ?? now();
        }

        $payment->save();

        return $payment;
    }

    public function checkTransaction(Payment $payment): Payment
    {
        $this->assertConfigured();

        $response = $this->http()->get($this->endpoint('/v2/'.$payment->provider_reference.'/status'));

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'Midtrans check transaction failed.');
        }

        $data = $response->json();
        $status = strtolower($data['transaction_status'] ?? $payment->status);

        if ($status && $status !== $payment->status) {
            $payment->status = $status;
            if (in_array($status, ['settlement', 'capture'], true) && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            $payment->response_payload = array_merge($payment->response_payload ?? [], $data);
            $payment->save();
        }

        return $payment;
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): Payment
    {
        $this->assertConfigured();

        $orderId = $payment->provider_reference;
        if (! $orderId) {
            throw new PaymentProviderException('Midtrans order id tidak ditemukan.');
        }

        $refundKey = 'refund-'.$payment->id.'-'.now()->format('YmdHis');
        $payload = [
            'refund_key' => $refundKey,
            'amount' => (int) $amount,
            'reason' => $reason ?: 'Pembatalan transaksi',
        ];

        $response = $this->http()->post($this->endpoint('/v2/'.$orderId.'/refund'), $payload);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'Midtrans refund failed.');
        }

        $data = $response->json();
        $status = strtolower((string) ($data['status'] ?? $data['transaction_status'] ?? 'refunded'));
        $normalized = str_contains($status, 'pending') ? 'refund_pending' : 'refunded';

        $payment->status = $normalized;
        $payment->response_payload = array_merge($payment->response_payload ?? [], ['refund' => $data]);
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'refund' => $data,
            'refund_key' => $refundKey,
            'refund_requested_at' => now()->toISOString(),
        ]);
        $payment->save();

        return $payment;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['pos_checkout', 'subscription', 'wallet_topup'], true);
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth($this->serverKey(), '')->acceptJson()->timeout(15);
    }

    protected function snapHttp(): PendingRequest
    {
        $snapBase = Arr::get($this->config, $this->config['mode'] === 'production' ? 'endpoints.snap_production' : 'endpoints.snap_sandbox');

        return Http::withBasicAuth($this->serverKey(), '')->acceptJson()->baseUrl($snapBase)->timeout(15);
    }

    protected function endpoint(string $path): string
    {
        $base = Arr::get($this->config, $this->config['mode'] === 'production' ? 'endpoints.production' : 'endpoints.sandbox');

        return rtrim($base, '/').$path;
    }

    protected function snapEndpoint(string $path): string
    {
        $base = Arr::get($this->config, $this->config['mode'] === 'production' ? 'endpoints.snap_production' : 'endpoints.snap_sandbox');

        return rtrim($base, '/').$path;
    }

    protected function serverKey(): string
    {
        $serverKey = Arr::get($this->config, 'credentials.server_key')
            ?? Arr::get($this->config, 'server_key');

        if (! $serverKey) {
            throw new PaymentProviderException('Midtrans server key is not configured.');
        }

        return $serverKey;
    }

    protected function validateSignature(?string $signature, array $payload): void
    {
        $serverKey = $this->serverKey();
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! $signature || ! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException('Invalid Midtrans signature.');
        }
    }

    protected function assertConfigured(): void
    {
        if (! Arr::get($this->config, 'credentials.server_key') && ! Arr::get($this->config, 'server_key')) {
            throw new PaymentProviderException('Midtrans credentials are missing.');
        }
    }

    protected function normalizeOrderId(?string $orderId, Payment $payment): string
    {
        $orderId = $orderId ?: $payment->provider_reference;

        if ($orderId && $this->isValidOrderId($orderId)) {
            return $orderId;
        }

        return $this->generateOrderId($payment);
    }

    protected function isValidOrderId(string $orderId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{1,50}$/', $orderId);
    }

    protected function generateOrderId(Payment $payment): string
    {
        $base = 'midtrans-'.$payment->id.'-'.Str::upper(Str::random(8));

        return substr($base, 0, 50);
    }
}
