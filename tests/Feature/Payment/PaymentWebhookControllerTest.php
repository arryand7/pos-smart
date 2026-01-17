<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
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
        ]));
    }

    public function test_invalid_signature_is_logged_and_rejected(): void
    {
        Payment::create([
            'provider' => 'ipaymu',
            'provider_reference' => 'TRX-3',
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/payments/webhook/ipaymu', [
            'reference' => 'TRX-3',
            'status' => 'completed',
        ], [
            'signature' => 'invalid',
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider' => 'ipaymu',
            'is_processed' => false,
            'http_status' => 401,
        ]);
    }
}
