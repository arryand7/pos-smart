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

class DokuProvider implements PaymentProvider
{
    protected array $config = [];

    public function getProviderKey(): string
    {
        return 'doku';
    }

    public function boot(array $config = []): void
    {
        $this->config = $config;
    }

    public function createCharge(Payment $payment, array $payload = []): Payment
    {
        $this->assertConfigured();

        $body = array_merge([
            'order' => [
                'amount' => $payment->amount,
                'invoice_number' => $payment->provider_reference ?: 'doku-'.Str::uuid()->toString(),
                'currency' => 'IDR',
            ],
            'virtual_account' => [
                'expired_time' => 60,
            ],
        ], $payload);

        $response = $this->http()
            ->withHeaders($this->signatureHeaders('POST', '/checkout/v1/payment', $body))
            ->post($this->endpoint('/checkout/v1/payment'), $body);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'DOKU create charge failed.');
        }

        $data = $response->json();

        $payment->provider = $this->getProviderKey();
        $payment->provider_reference = Arr::get($data, 'order.invoice_number', $body['order']['invoice_number']);
        $payment->status = 'pending';
        $payment->request_payload = array_merge($payment->request_payload ?? [], $body);
        $payment->response_payload = array_merge($payment->response_payload ?? [], $data);
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'mode' => $this->config['mode'] ?? 'sandbox',
        ]);
        $payment->save();

        return $payment;
    }

    public function handleWebhook(Request $request): Payment
    {
        $invoiceId = $request->input('invoice_number');

        $payment = Payment::query()
            ->where('provider', $this->getProviderKey())
            ->where('provider_reference', $invoiceId)
            ->firstOrFail();

        $this->validateSignature($request->header('signature'), $request->getContent());

        $payment->status = strtolower($request->input('payment_status', $payment->status ?? 'pending'));
        $payment->response_payload = array_merge($payment->response_payload ?? [], $request->all());

        if (in_array($payment->status, ['paid', 'success'], true)) {
            $payment->paid_at = $payment->paid_at ?? now();
        }

        $payment->save();

        return $payment;
    }

    public function checkTransaction(Payment $payment): Payment
    {
        $this->assertConfigured();

        $body = [
            'order' => [
                'invoice_number' => $payment->provider_reference,
            ],
        ];

        $response = $this->http()
            ->withHeaders($this->signatureHeaders('POST', '/checkout/v1/status', $body))
            ->post($this->endpoint('/checkout/v1/status'), $body);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'DOKU check transaction failed.');
        }

        $data = $response->json();
        $status = strtolower(Arr::get($data, 'transaction.status', $payment->status));

        if ($status && $status !== $payment->status) {
            $payment->status = $status;
            if (in_array($status, ['paid', 'success'], true) && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            $payment->response_payload = array_merge($payment->response_payload ?? [], $data);
            $payment->save();
        }

        return $payment;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['virtual_account', 'qris'], true);
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(15);
    }

    protected function endpoint(string $path): string
    {
        $base = Arr::get($this->config, $this->config['mode'] === 'production' ? 'endpoints.production' : 'endpoints.sandbox');

        return rtrim($base, '/').$path;
    }

    protected function signatureHeaders(string $method, string $path, array $body): array
    {
        $clientId = Arr::get($this->config, 'credentials.client_id');
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\'T\H:i:s\'Z');
        $digest = base64_encode(hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES), true));
        $rawSignature = "Client-Id:$clientId\nRequest-Timestamp:$timestamp\nRequest-Target:$path\nDigest:$digest";
        $signature = base64_encode(hash_hmac('sha256', $rawSignature, Arr::get($this->config, 'credentials.secret_key'), true));

        return [
            'Client-Id' => $clientId,
            'Request-Timestamp' => $timestamp,
            'Signature' => "HMACSHA256=$signature",
            'Digest' => $digest,
        ];
    }

    protected function validateSignature(?string $signatureHeader, string $payload): void
    {
        $secret = Arr::get($this->config, 'credentials.secret_key');

        if (! $secret) {
            throw new PaymentProviderException('DOKU secret key missing.');
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        $signature = str_replace('HMACSHA256=', '', (string) $signatureHeader);

        if (! $signature || ! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException('Invalid DOKU signature.');
        }
    }

    protected function assertConfigured(): void
    {
        foreach (['credentials.client_id', 'credentials.secret_key'] as $key) {
            if (! Arr::get($this->config, $key)) {
                throw new PaymentProviderException(sprintf('Missing DOKU configuration value [%s].', $key));
            }
        }
    }
}
