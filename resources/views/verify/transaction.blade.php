<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Transaksi</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap">
    <style>
        .verification-watermark {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
            font-weight: 800;
            letter-spacing: 0.35rem;
            text-transform: uppercase;
            opacity: 0.08;
            pointer-events: none;
            transform: rotate(-20deg);
            z-index: 0;
        }
        .verification-watermark.valid {
            color: #16a34a;
        }
        .verification-watermark.invalid {
            color: #dc2626;
        }
        .verification-watermark.pending {
            color: #f59e0b;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans">
    @php
        $status = strtolower($transaction->status ?? '');
        $isValid = $status === 'completed';
        $isPending = $status === 'pending';
    @endphp
    <div class="verification-watermark {{ $isValid ? 'valid' : ($isPending ? 'pending' : 'invalid') }}">
        {{ $isValid ? 'VALID' : ($isPending ? 'PENDING' : 'INVALID') }}
    </div>
    <main class="max-w-3xl mx-auto px-4 py-10 relative z-10">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-800">Verifikasi Struk</h1>
                    <p class="text-sm text-slate-500">SMART POS - Sabira Mart</p>
                </div>
                @php
                    $statusLabel = strtoupper($transaction->status ?? 'UNKNOWN');
                    $statusClass = match ($status) {
                        'completed' => 'bg-emerald-50 text-emerald-600',
                        'pending' => 'bg-amber-50 text-amber-600',
                        'cancelled', 'failed', 'expired', 'deny' => 'bg-rose-50 text-rose-600',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
                <div>
                    <div class="text-slate-500">Ref ID</div>
                    <div class="font-mono">{{ $transaction->reference }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Waktu</div>
                    <div>{{ ($transaction->processed_at ?? $transaction->created_at)?->format('d/m/Y H:i') }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Lokasi</div>
                    <div>{{ $transaction->location?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Kasir</div>
                    <div>{{ $transaction->kasir?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Santri</div>
                    <div>
                        @if($transaction->santri)
                            {{ $transaction->santri->name }} ({{ $transaction->santri->nis }})
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-slate-500">Total</div>
                    <div class="font-semibold">Rp{{ number_format($transaction->total_amount, 0, ',', '.') }}</div>
                </div>
                @if(data_get($transaction->metadata, 'cancel_reason'))
                    <div class="md:col-span-2">
                        <div class="text-slate-500">Alasan Pembatalan</div>
                        <div class="text-rose-600 font-semibold">{{ data_get($transaction->metadata, 'cancel_reason') }}</div>
                    </div>
                @endif
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h2 class="text-sm font-semibold text-slate-600 mb-3">Ringkasan Item</h2>
                <ul class="space-y-2 text-sm">
                    @foreach($transaction->items as $item)
                        <li class="flex justify-between">
                            <span>{{ $item->product_name }} x{{ $item->quantity }}</span>
                            <span class="font-medium">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6 border-t border-slate-200 pt-4">
                <h2 class="text-sm font-semibold text-slate-600 mb-3">Hasil Cek</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Pembayaran</div>
                        <div class="flex justify-between mb-1"><span class="text-slate-500">Tunai</span><span>Rp{{ number_format($transaction->cash_amount, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between mb-1"><span class="text-slate-500">Saldo</span><span>Rp{{ number_format($transaction->wallet_amount, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between mb-1"><span class="text-slate-500">Gateway</span><span>Rp{{ number_format($transaction->gateway_amount, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between mt-2 border-t border-slate-200 pt-2 font-semibold">
                            <span>Total Dibayar</span>
                            <span>Rp{{ number_format($transaction->paid_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Gateway Detail</div>
                        @if($transaction->payments && $transaction->payments->count())
                            <div class="space-y-3">
                                @foreach($transaction->payments as $payment)
                                    @php
                                        $paymentStatus = strtoupper($payment->status ?? 'PENDING');
                                        $refundStatus = in_array($payment->status, ['refund_pending', 'refunded'], true)
                                            ? strtoupper($payment->status)
                                            : (data_get($payment->metadata, 'refund_key') ? 'REFUND_REQUESTED' : '-');
                                    @endphp
                                    <div>
                                        <div class="font-semibold text-slate-700">{{ strtoupper($payment->provider) }}</div>
                                        <div class="text-xs text-slate-500">Status: <span class="font-medium">{{ $paymentStatus }}</span></div>
                                        <div class="text-xs text-slate-500">Refund: <span class="font-medium">{{ $refundStatus }}</span></div>
                                        <div class="text-xs text-slate-500">Ref: {{ $payment->provider_reference ?? '-' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-500">Tidak ada payment gateway.</p>
                        @endif
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 md:col-span-2">
                        <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Lokasi & Petugas</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <div class="text-slate-500 text-xs">Lokasi</div>
                                <div class="font-medium">{{ $transaction->location?->name ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500 text-xs">Kasir</div>
                                <div class="font-medium">{{ $transaction->kasir?->name ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500 text-xs">Channel</div>
                                <div class="font-medium">{{ ucfirst($transaction->channel ?? '-') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
