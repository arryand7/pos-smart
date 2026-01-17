<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentRedirectController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        $statusHint = $request->string('status')->lower()->value();
        $payment = $this->paymentService->refreshPaymentStatus($payment);

        $status = $payment->status ?? $statusHint ?? 'pending';

        [$flashKey, $message] = match ($status) {
            'completed', 'paid', 'success' => ['status', 'Top-up berhasil dikonfirmasi. Saldo akan segera bertambah.'],
            'pending', 'process', 'waiting' => ['status', 'Pembayaran diterima dan sedang diverifikasi oleh penyedia.'],
            'failed', 'cancelled', 'expired' => ['error', 'Top-up dibatalkan atau gagal. Silakan buat permintaan baru.'],
            default => ['status', 'Status pembayaran diperbarui.'],
        };

        $redirectTo = data_get($payment->metadata, 'redirect_to', url('/'));

        return redirect($redirectTo)->with($flashKey, $message);
    }
}
