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

class IpaymuProvider implements PaymentProvider
{
    protected array $config = [];

    public function getProviderKey(): string
    {
        return 'ipaymu';
    }

    public function boot(array $config = []): void
    {
        $this->config = $config;
    }

    public function createCharge(Payment $payment, array $payload = []): Payment
    {
        $this->assertConfigured();

        $body = $this->buildChargePayload($payment, $payload);
        $response = $this->http()
            ->withHeaders($this->signatureHeaders('POST', '/payment', $body))
            ->post($this->endpoint('/payment'), $body);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'iPaymu create charge failed.');
        }

        $responseData = $response->json();

        $payment->provider = $this->getProviderKey();
        $payment->status = strtolower($responseData['Status'] ?? 'pending');
        $payment->provider_reference = $responseData['Data']['TransactionId'] ?? $payment->provider_reference ?? Str::uuid()->toString();
        $payment->request_payload = array_merge($payment->request_payload ?? [], $body);
        $payment->response_payload = array_merge($payment->response_payload ?? [], $responseData);
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'channel' => Arr::get($payload, 'channel', 'qris'),
            'mode' => $this->config['mode'] ?? 'sandbox',
            'redirect_url' => $responseData['Data']['Url'] ?? null,
        ]);
        $payment->save();

        return $payment;
    }

    public function handleWebhook(Request $request): Payment
    {
        $reference = $request->input('trx_id') ?? $request->input('reference');

        /** @var Payment $payment */
        $payment = Payment::query()
            ->where('provider', $this->getProviderKey())
            ->when($reference, fn ($q) => $q->where('provider_reference', $reference))
            ->firstOrFail();

        $this->validateSignature($request->header('signature'), $request->getContent());

        $status = strtolower($request->input('status', $payment->status ?? 'pending'));

        $payment->status = $status;
        $payment->response_payload = array_merge($payment->response_payload ?? [], $request->all());

        if ($status === 'completed' && ! $payment->paid_at) {
            $payment->paid_at = now();
        }

        $payment->save();

        return $payment;
    }

    public function checkTransaction(Payment $payment): Payment
    {
        $this->assertConfigured();

        $payload = [
            'transactionId' => $payment->provider_reference,
        ];

        $response = $this->http()
            ->withHeaders($this->signatureHeaders('POST', '/transaction', $payload))
            ->post($this->endpoint('/transaction'), $payload);

        if (! $response->successful()) {
            throw new PaymentProviderHttpException($response, 'iPaymu check transaction failed.');
        }

        $data = $response->json();
        $status = strtolower(Arr::get($data, 'Data.Status', $payment->status));

        if ($status && $status !== $payment->status) {
            $payment->status = $status;
            if ($status === 'completed' && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            $payment->response_payload = array_merge($payment->response_payload ?? [], $data);
            $payment->save();
        }

        return $payment;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['wallet_topup', 'pos_checkout', 'qris'], true);
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson();
    }

    protected function endpoint(string $path): string
    {
        $base = Arr::get($this->config, 'endpoints.'.$this->config['mode'], Arr::get($this->config, 'endpoints.sandbox'));

        return rtrim($base, '/').$path;
    }

    protected function signatureHeaders(string $method, string $path, array $body): array
    {
        $signature = $this->signature($method, $path, $body);

        return [
            'signature' => $signature,
            'va' => Arr::get($this->config, 'credentials.virtual_account'),
            'apikey' => Arr::get($this->config, 'credentials.api_key'),
        ];
    }

    protected function signature(string $method, string $path, array $body): string
    {
        $privateKey = Arr::get($this->config, 'credentials.private_key');

        if (! $privateKey) {
            throw new PaymentProviderException('iPaymu private key is not configured.');
        }

        $payload = strtoupper($method).'|'.$path.'|'.json_encode($body, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, $privateKey);
    }

    protected function validateSignature(?string $signature, string $rawPayload): void
    {
        $expected = hash_hmac('sha256', $rawPayload, Arr::get($this->config, 'credentials.private_key', ''));

        if (! $signature || ! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException('Invalid iPaymu signature.');
        }
    }

    protected function assertConfigured(): void
    {
        $required = ['credentials.virtual_account', 'credentials.api_key', 'credentials.private_key'];

        foreach ($required as $key) {
            if (! Arr::get($this->config, $key)) {
                throw new PaymentProviderException(sprintf('Missing iPaymu configuration value [%s].', $key));
            }
        }
    }

    protected function buildChargePayload(Payment $payment, array $payload): array
    {
        $body = array_merge([
            'referenceId' => $payment->id,
            'product' => Arr::get($payload, 'product', ['SMART POS']),
            'qty' => Arr::get($payload, 'qty', [1]),
            'price' => Arr::get($payload, 'price', [$payment->amount]),
            'amount' => $payment->amount,
            'notifyUrl' => Arr::get($this->config, 'callback_url', url('/api/payments/webhook/'.$this->getProviderKey())),
            'returnUrl' => Arr::get($payload, 'return_url', Arr::get($this->config, 'redirect_url', url('/payments/'.$this->getProviderKey().'/return'))),
            'cancelUrl' => Arr::get($payload, 'cancel_url', url('/payments/'.$this->getProviderKey().'/cancel')),
            'paymentMethod' => Arr::get($payload, 'payment_method', 'qris'),
        ], Arr::except($payload, ['product', 'price', 'qty']));

        $body['merchantCode'] = Arr::get($this->config, 'credentials.merchant_code');

        return $body;
    }
}
