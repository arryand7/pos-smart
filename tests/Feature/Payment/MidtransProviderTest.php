<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Services\Payment\Exceptions\InvalidSignatureException;
use App\Services\Payment\Providers\MidtransProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('smart.payments.providers.midtrans', [
            'capabilities' => ['pos_checkout'],
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'server-key',
                'client_key' => 'client-key',
            ],
            'endpoints' => [
                'sandbox' => 'https://api.sandbox.midtrans.com',
                'production' => 'https://api.midtrans.com',
                'snap_sandbox' => 'https://app.sandbox.midtrans.com',
                'snap_production' => 'https://app.midtrans.com',
            ],
        ]);
    }

    public function test_create_charge_requests_snap_transaction(): void
    {
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://snap.example/redirect',
            ], 201),
        ]);

        $payment = Payment::create([
            'provider' => 'midtrans',
            'amount' => 10000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $provider = app(MidtransProvider::class);
        $provider->boot(config('smart.payments.providers.midtrans'));

        $provider->createCharge($payment, [
            'transaction_details' => [
                'order_id' => 'ORDER-123',
                'gross_amount' => 10000,
            ],
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request->header('Authorization')[0] === 'Basic '.base64_encode('server-key:');
        });

        $payment->refresh();
        $this->assertSame('ORDER-123', $payment->provider_reference);
        $this->assertSame('pending', $payment->status);
        $this->assertEquals('https://snap.example/redirect', Arr::get($payment->metadata, 'redirect_url'));
    }

    public function test_handle_webhook_updates_status_and_validates_signature(): void
    {
        $provider = app(MidtransProvider::class);
        $provider->boot(config('smart.payments.providers.midtrans'));

        $payment = Payment::create([
            'provider' => 'midtrans',
            'provider_reference' => 'ORDER-456',
            'amount' => 25000,
            'status' => 'pending',
        ]);

        $payload = [
            'order_id' => 'ORDER-456',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '25000',
        ];

        $signature = hash('sha512', 'ORDER-456'.'200'.'25000'.'server-key');

        $request = Request::create('/api/payments/webhook/midtrans', 'POST', $payload);
        $request->merge(['signature_key' => $signature]);

        $provider->handleWebhook($request);

        $payment->refresh();
        $this->assertSame('settlement', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_handle_webhook_rejects_invalid_signature(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $provider = app(MidtransProvider::class);
        $provider->boot(config('smart.payments.providers.midtrans'));

        Payment::create([
            'provider' => 'midtrans',
            'provider_reference' => 'ORDER-789',
            'amount' => 12000,
            'status' => 'pending',
        ]);

        $request = Request::create('/api/payments/webhook/midtrans', 'POST', [
            'order_id' => 'ORDER-789',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '12000',
            'signature_key' => 'invalid',
        ]);

        $provider->handleWebhook($request);
    }

    public function test_check_transaction_refreshes_status(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/ORDER-321/status' => Http::response([
                'transaction_status' => 'capture',
                'status_code' => '200',
                'gross_amount' => '50000',
            ], 200),
        ]);

        $provider = app(MidtransProvider::class);
        $provider->boot(config('smart.payments.providers.midtrans'));

        $payment = Payment::create([
            'provider' => 'midtrans',
            'provider_reference' => 'ORDER-321',
            'amount' => 50000,
            'status' => 'pending',
        ]);

        $provider->checkTransaction($payment);

        $payment->refresh();
        $this->assertSame('capture', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }
}
