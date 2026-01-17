<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Providers\DokuProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokuProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('smart.payments.providers.doku', [
            'capabilities' => ['virtual_account'],
            'mode' => 'sandbox',
            'credentials' => [
                'client_id' => 'client-id',
                'secret_key' => 'secret-key',
                'merchant_code' => 'M123',
            ],
            'endpoints' => [
                'sandbox' => 'https://api-sandbox.doku.com',
                'production' => 'https://api.doku.com',
            ],
            'callback_url' => 'https://example.com/webhook',
        ]);
    }

    public function test_create_charge_hits_checkout_api(): void
    {
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
                'order' => [
                    'invoice_number' => 'INV-123',
                ],
            ], 200),
        ]);

        $payment = Payment::create([
            'provider' => 'doku',
            'amount' => 75000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $provider = app(DokuProvider::class);
        $provider->boot(config('smart.payments.providers.doku'));

        $provider->createCharge($payment, [
            'order' => [
                'invoice_number' => 'INV-123',
                'amount' => 75000,
            ],
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-sandbox.doku.com/checkout/v1/payment'
                && $request->header('Client-Id')[0] === 'client-id'
                && str_contains($request->header('Signature')[0] ?? '', 'HMACSHA256=');
        });

        $payment->refresh();
        $this->assertSame('INV-123', $payment->provider_reference);
        $this->assertSame('pending', $payment->status);
    }

    public function test_handle_webhook_validates_signature(): void
    {
        $provider = app(DokuProvider::class);
        $provider->boot(config('smart.payments.providers.doku'));

        $payment = Payment::create([
            'provider' => 'doku',
            'provider_reference' => 'INV-456',
            'amount' => 43000,
            'status' => 'pending',
        ]);

        $payload = [
            'invoice_number' => 'INV-456',
            'payment_status' => 'success',
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = base64_encode(hash_hmac('sha256', $raw, 'secret-key', true));

        $request = Request::create('/api/payments/webhook/doku', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
        $request->headers->set('signature', 'HMACSHA256='.$signature);

        $provider->handleWebhook($request);

        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_handle_webhook_rejects_invalid_signature(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $provider = app(DokuProvider::class);
        $provider->boot(config('smart.payments.providers.doku'));

        Payment::create([
            'provider' => 'doku',
            'provider_reference' => 'INV-789',
            'amount' => 30000,
            'status' => 'pending',
        ]);

        $payload = [
            'invoice_number' => 'INV-789',
            'payment_status' => 'success',
        ];

        $request = Request::create('/api/payments/webhook/doku', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_UNESCAPED_SLASHES));
        $request->headers->set('signature', 'HMACSHA256=invalid');

        $provider->handleWebhook($request);
    }

    public function test_check_transaction_updates_status(): void
    {
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/status' => Http::response([
                'transaction' => [
                    'status' => 'success',
                ],
            ], 200),
        ]);

        $provider = app(DokuProvider::class);
        $provider->boot(config('smart.payments.providers.doku'));

        $payment = Payment::create([
            'provider' => 'doku',
            'provider_reference' => 'INV-001',
            'amount' => 15000,
            'status' => 'pending',
        ]);

        $provider->checkTransaction($payment);

        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }
}
