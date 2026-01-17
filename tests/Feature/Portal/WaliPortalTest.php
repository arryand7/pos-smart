<?php

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class WaliPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_can_view_portal_dashboard(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create([
            'wallet_balance' => 125000,
            'daily_limit' => 50000,
            'monthly_limit' => 250000,
        ]);

        WalletTransaction::create([
            'santri_id' => $santri->id,
            'performed_by' => null,
            'type' => 'credit',
            'channel' => 'topup',
            'amount' => 125000,
            'balance_before' => 0,
            'balance_after' => 125000,
            'status' => 'completed',
            'description' => 'Saldo awal',
            'occurred_at' => now(),
        ]);

        Payment::create([
            'provider' => 'ipaymu',
            'status' => 'pending',
            'amount' => 75000,
            'currency' => 'IDR',
            'santri_id' => $santri->id,
            'metadata' => ['redirect_to' => route('portal.wali')],
        ]);

        $response = $this
            ->actingAs($waliUser)
            ->withSession($this->sessionPayload($waliUser))
            ->get(route('portal.wali'));

        $response->assertOk()
            ->assertSeeText($wali->name)
            ->assertSeeText($santri->name)
            ->assertSeeText('Portal Wali SMART')
            ->assertSeeText('Pending');
    }

    public function test_wali_can_update_child_limits(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create([
            'daily_limit' => 20000,
            'monthly_limit' => 80000,
        ]);

        $payload = [
            'daily_limit' => 50000,
            'monthly_limit' => 200000,
            'is_wallet_locked' => '1',
            'notes' => 'Batasi jajan malam',
        ];

        $response = $this
            ->actingAs($waliUser)
            ->withSession($this->sessionPayload($waliUser))
            ->post(route('portal.wali.limits', $santri), $payload);

        $response->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('santris', [
            'id' => $santri->id,
            'daily_limit' => 50000,
            'monthly_limit' => 200000,
            'is_wallet_locked' => true,
            'notes' => 'Batasi jajan malam',
        ]);
    }

    public function test_wali_can_initiate_topup_and_receive_redirect_link(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create();

        config([
            'smart.payments.default_provider' => 'ipaymu',
            'smart.payments.providers.ipaymu' => [
                'capabilities' => ['wallet_topup'],
                'mode' => 'sandbox',
                'credentials' => [
                    'virtual_account' => '000000',
                    'api_key' => 'key',
                    'private_key' => 'secret',
                    'merchant_code' => 'M123',
                ],
                'endpoints' => [
                    'sandbox' => 'https://sandbox.ipaymu.com/api/v2',
                ],
                'callback_url' => 'https://smart.test/api/payments/webhook/ipaymu',
                'redirect_url' => 'https://smart.test/portal/wali',
            ],
        ]);

        Http::fake([
            'https://sandbox.ipaymu.com/api/v2/payment' => Http::response([
                'Status' => 'success',
                'Data' => [
                    'TransactionId' => 'TRX-001',
                    'Url' => 'https://sandbox.ipaymu.com/payment/TRX-001',
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($waliUser)
            ->withSession($this->sessionPayload($waliUser))
            ->post(route('portal.wali.topup', $santri), [
                'amount' => 50000,
                'provider' => 'ipaymu',
            ]);

        $response->assertRedirect(route('portal.wali'))
            ->assertSessionHas('payment_redirect_url', 'https://sandbox.ipaymu.com/payment/TRX-001');

        $this->assertDatabaseHas('payments', [
            'santri_id' => $santri->id,
            'amount' => 50000,
            'provider' => 'ipaymu',
        ]);

        $this->assertSame('https://sandbox.ipaymu.com/payment/TRX-001', Payment::first()->metadata['redirect_url']);
        $this->assertSame(route('portal.wali'), Payment::first()->metadata['redirect_to']);
    }

    public function test_wali_cannot_update_other_children(): void
    {
        $waliAUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $waliA = Wali::factory()->for($waliAUser)->create();

        $waliBUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $waliB = Wali::factory()->for($waliBUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($waliB)->create();

        $this
            ->actingAs($waliAUser)
            ->withSession($this->sessionPayload($waliAUser))
            ->post(route('portal.wali.limits', $santri), [
                'daily_limit' => 10000,
            ])
            ->assertForbidden();
    }

    public function test_payment_redirect_sets_flash_message(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create();

        $payment = Payment::create([
            'provider' => 'ipaymu',
            'status' => 'pending',
            'amount' => 100000,
            'currency' => 'IDR',
            'santri_id' => $santri->id,
            'metadata' => ['redirect_to' => route('portal.wali')],
        ]);

        $this->mockPaymentServiceStatus('completed', $payment);

        $response = $this
            ->actingAs($waliUser)
            ->withSession($this->sessionPayload($waliUser))
            ->get(route('payments.redirect', ['payment' => $payment->id, 'status' => 'success']));

        $response->assertRedirect(route('portal.wali'))
            ->assertSessionHas('status');
    }

    public function test_wali_can_refresh_payment_status_from_portal(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create();

        $payment = Payment::create([
            'provider' => 'ipaymu',
            'status' => 'pending',
            'amount' => 54000,
            'currency' => 'IDR',
            'santri_id' => $santri->id,
        ]);

        $this->mockPaymentServiceStatus('completed', $payment);

        $response = $this
            ->actingAs($waliUser)
            ->withSession($this->sessionPayload($waliUser))
            ->post(route('portal.wali.payments.refresh', $payment));

        $response->assertRedirect()
            ->assertSessionHas('status', 'Pembayaran dikonfirmasi berhasil.');
    }

    protected function sessionPayload(User $user): array
    {
        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        return [
            'smart_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $role,
            ],
        ];
    }

    protected function mockPaymentServiceStatus(string $status, ?Payment $payment = null): void
    {
        $mock = Mockery::mock(\App\Services\Payment\PaymentService::class);
        $mock->shouldReceive('refreshPaymentStatus')
            ->andReturnUsing(function (Payment $incoming) use ($status, $payment) {
                $target = $payment ? $payment->fresh() : $incoming;
                $target->status = $status;

                return $target;
            });

        $this->app->instance(\App\Services\Payment\PaymentService::class, $mock);
        $this->beforeApplicationDestroyed(function (): void {
            $this->app->forgetInstance(\App\Services\Payment\PaymentService::class);
        });
    }
}
