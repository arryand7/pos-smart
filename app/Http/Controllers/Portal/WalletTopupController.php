<?php

namespace App\Http\Controllers\Portal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Services\Payment\PaymentService;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletTopupController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(Request $request, Santri $santri): RedirectResponse
    {
        $user = $request->user();
        $wali = $user?->wali;

        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if (! $user->hasRole(UserRole::SUPER_ADMIN) && (! $wali || $santri->wali_id !== $wali->id)) {
            abort(403, 'Anda tidak diizinkan melakukan top up untuk santri tersebut.');
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'provider' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
        ]);
        $data['amount'] = Rupiah::from($data['amount'], 'top_up_amount');

        $provider = $data['provider'] ?? null;
        if ($provider && ! $this->providerConfigured($provider)) {
            $provider = null;
        }
        $redirectParams = $user->hasRole(UserRole::SUPER_ADMIN) ? ['wali_id' => $santri->wali_id] : [];
        $portalRedirect = route('portal.wali', $redirectParams);

        $payment = $this->paymentService->initiateTopUp(
            $santri,
            $data['amount'],
            $provider,
            [
                'channel' => 'portal',
                'product' => ["Top-up {$santri->name}"],
                'qty' => [1],
                'price' => [$data['amount']],
                'payment_method' => $data['payment_method'] ?? 'qris',
                'redirect_to' => $portalRedirect,
                'cancel_url' => $portalRedirect,
            ]
        );

        $redirectUrl = data_get($payment->metadata, 'redirect_url')
            ?? data_get($payment->response_payload, 'Data.Url');

        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return redirect()
            ->route('portal.wali', $redirectParams)
            ->with('error', 'Link pembayaran belum tersedia. Silakan cek konfigurasi payment gateway.');
    }

    protected function providerConfigured(string $providerKey): bool
    {
        $config = \App\Models\PaymentProviderConfig::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->first();

        return app(\App\Services\Payment\PaymentManager::class)
            ->isProviderConfigured($providerKey, $config?->config ?? []);
    }
}
