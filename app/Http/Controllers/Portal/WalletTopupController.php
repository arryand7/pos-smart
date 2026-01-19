<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\PaymentProviderConfig;
use App\Models\Santri;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletTopupController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

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
            'amount' => ['required', 'numeric', 'min:1000'],
            'provider' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $provider = $data['provider'] ?? null;
        if ($provider && ! $this->providerConfigured($provider)) {
            $provider = null;
        }
        $redirectParams = $user->hasRole(UserRole::SUPER_ADMIN) ? ['wali_id' => $santri->wali_id] : [];
        $portalRedirect = route('portal.wali', $redirectParams);

        $payment = $this->paymentService->initiateTopUp(
            $santri,
            (float) $data['amount'],
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
        $config = PaymentProviderConfig::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->first();

        $baseConfig = config("smart.payments.providers.$providerKey", []);
        $merged = array_merge($baseConfig, $config?->config ?? []);
        $credentials = $merged['credentials'] ?? [];

        if ($providerKey === 'ipaymu') {
            $credentials['virtual_account'] = $credentials['virtual_account'] ?? ($merged['virtual_account'] ?? null);
            $credentials['api_key'] = $credentials['api_key'] ?? ($merged['api_key'] ?? null);
            $credentials['private_key'] = $credentials['private_key'] ?? ($merged['private_key'] ?? null);

            return ! empty($credentials['virtual_account'])
                && ! empty($credentials['api_key'])
                && ! empty($credentials['private_key']);
        }

        if ($providerKey === 'midtrans') {
            $credentials['server_key'] = $credentials['server_key'] ?? ($merged['server_key'] ?? null);

            return ! empty($credentials['server_key']);
        }

        if ($providerKey === 'doku') {
            $credentials['client_id'] = $credentials['client_id'] ?? ($merged['client_id'] ?? null);
            $credentials['secret_key'] = $credentials['secret_key'] ?? ($merged['secret_key'] ?? null);

            return ! empty($credentials['client_id']) && ! empty($credentials['secret_key']);
        }

        return true;
    }
}
