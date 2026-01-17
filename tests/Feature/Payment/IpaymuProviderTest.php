<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Providers\IpaymuProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpaymuProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('smart.payments.providers.ipaymu', array_merge(config('smart.payments.providers.ipaymu'), [
            'mode' => 'sandbox',
            'credentials' => [
                'virtual_account' => '0000001234',
                'api_key' => 'test-api-key',
                'private_key' => 'secret-key',
                'merchant_code' => 'M123',
            ],
            'endpoints' => [
                'sandbox' => 'https://example.test/api',
            ],
            'callback_url' => 'https://webhook.test/ipaymu',
            'redirect_url' => 'https://app.test/return',
        ]));
    }

    public function test_create_charge_calls_ipaymu_api(): void
    {
        Http::fake([
            'https://example.test/api/payment' => Http::response([
                'Status' => 'Pending',
                'Data' => [
                    'TransactionId' => 'TRX123',
                    'Url' => 'https://example.test/pay/TRX123',
                ],
            ], 200),
        ]);

        $payment = Payment::create([
            'provider' => 'ipaymu',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $provider = app(IpaymuProvider::class);
        $provider->boot(config('smart.payments.providers.ipaymu'));

        $provider->createCharge($payment, [
            'payment_method' => 'qris',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.test/api/payment'
                && $request->header('va')[0] === '0000001234'
                && $request->header('signature');
        });

        $payment->refresh();
        $this->assertSame('trx123', strtolower($payment->provider_reference));
        $this->assertSame('pending', $payment->status);
        $this->assertEquals('https://example.test/pay/TRX123', Arr::get($payment->metadata, 'redirect_url'));
    }

    public function test_handle_webhook_validates_signature(): void
    {
        $provider = app(IpaymuProvider::class);
        $provider->boot(config('smart.payments.providers.ipaymu'));

        $payment = Payment::create([
            'provider' => 'ipaymu',
            'provider_reference' => 'TRX-1',
            'amount' => 10000,
            'status' => 'pending',
        ]);

        $payload = [
            'reference' => 'TRX-1',
            'status' => 'completed',
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $raw, 'secret-key');

        $request = Request::create('/api/payments/webhook/ipaymu', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
        $request->headers->set('signature', $signature);

        $provider->handleWebhook($request);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
    }

    public function test_handle_webhook_rejects_invalid_signature(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $provider = app(IpaymuProvider::class);
        $provider->boot(config('smart.payments.providers.ipaymu'));

        Payment::create([
            'provider' => 'ipaymu',
            'provider_reference' => 'TRX-2',
            'amount' => 10000,
            'status' => 'pending',
        ]);

        $payload = [
            'reference' => 'TRX-2',
            'status' => 'completed',
        ];

        $request = Request::create('/api/payments/webhook/ipaymu', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_UNESCAPED_SLASHES));
        $request->headers->set('signature', 'invalid');

        $provider->handleWebhook($request);
    }
}
