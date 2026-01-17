<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuardianPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function refresh(Request $request, Payment $payment): RedirectResponse
    {
        $user = $request->user();
        $wali = $user?->wali;

        if (! $user) {
            abort(401, 'Pengguna belum terautentikasi.');
        }

        if (! $user->hasRole(UserRole::SUPER_ADMIN) && (! $wali || $payment->santri?->wali_id !== $wali->id)) {
            abort(403, 'Anda tidak diizinkan untuk memperbarui status pembayaran tersebut.');
        }

        $payment = $this->paymentService->refreshPaymentStatus($payment);

        $message = match ($payment->status) {
            'completed', 'paid', 'success' => 'Pembayaran dikonfirmasi berhasil.',
            'failed', 'cancelled', 'expired' => 'Pembayaran gagal atau dibatalkan.',
            default => 'Status pembayaran sudah diperbarui.',
        };

        $flashKey = in_array($payment->status, ['failed', 'cancelled', 'expired'], true) ? 'error' : 'status';

        return back()->with($flashKey, $message);
    }
}
