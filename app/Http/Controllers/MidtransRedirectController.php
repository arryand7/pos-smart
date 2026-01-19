<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MidtransRedirectController extends Controller
{
    public function __invoke(Request $request, PaymentService $paymentService): RedirectResponse
    {
        $orderId = $request->string('order_id')->toString();

        if (! $orderId) {
            return redirect('/')->with('error', 'Order Midtrans tidak ditemukan.');
        }

        $payment = Payment::query()
            ->where('provider', 'midtrans')
            ->where('provider_reference', $orderId)
            ->first();

        if (! $payment) {
            return redirect('/')->with('error', 'Pembayaran tidak ditemukan.');
        }

        $payment = $paymentService->refreshPaymentStatus($payment);

        $statusHint = $request->string('transaction_status')->lower()->value()
            ?: $request->string('status')->lower()->value();
        $status = $payment->status ?? $statusHint ?? 'pending';

        [$flashKey, $message] = match ($status) {
            'completed', 'paid', 'success', 'settlement', 'capture' => ['status', 'Top-up berhasil dikonfirmasi. Saldo akan segera bertambah.'],
            'pending', 'process', 'waiting' => ['status', 'Pembayaran diterima dan sedang diverifikasi oleh penyedia.'],
            'failed', 'cancelled', 'expired', 'deny' => ['error', 'Top-up dibatalkan atau gagal. Silakan buat permintaan baru.'],
            default => ['status', 'Status pembayaran diperbarui.'],
        };

        $redirectTo = data_get($payment->metadata, 'redirect_to', url('/'));

        return redirect($redirectTo)->with($flashKey, $message);
    }
}
