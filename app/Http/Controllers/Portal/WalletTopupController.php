<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
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

        $provider = $data['provider'] ?: config('smart.payments.default_provider', 'ipaymu');
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

        return redirect()
            ->route('portal.wali', $redirectParams)
            ->with('status', "Link pembayaran untuk {$santri->name} berhasil dibuat.")
            ->with('payment_redirect_url', $redirectUrl);
    }
}
