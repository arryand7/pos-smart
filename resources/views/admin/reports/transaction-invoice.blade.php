@extends('layouts.admin')

@section('title', 'Nota Transaksi')
@section('subtitle', 'Detail pembelian dan ringkasan pembayaran.')

@section('actions')
    @if(!request()->boolean('embed'))
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.reports.transactions') }}" class="btn btn-secondary">Kembali</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak</button>
            <a href="{{ route('admin.reports.transactions.receipt', $transaction) }}?size=58" class="btn btn-outline">Cetak 58mm</a>
            <a href="{{ route('admin.reports.transactions.receipt', $transaction) }}?size=80" class="btn btn-outline">Cetak 80mm</a>
            <a href="{{ route('admin.reports.transactions.receipt.pdf', $transaction) }}?size=80" class="btn btn-outline">Export PDF Struk</a>
            <a href="{{ route('admin.reports.transactions.invoice.pdf', $transaction) }}" class="btn btn-outline">Export PDF Invoice</a>
        </div>
    @endif
@endsection

@section('content')
@php
    $isEmbed = request()->boolean('embed');
    $cardClass = $isEmbed ? 'transaction-embed' : 'admin-card';
@endphp
<style>
    @media print {
        @page {
            margin: 12mm;
        }
        body {
            margin: 12mm !important;
        }
        aside, header, .sidebar-overlay, .page-subtitle, .btn, .admin-card .no-print {
            display: none !important;
        }
        main {
            padding: 0 !important;
        }
        .admin-card {
            box-shadow: none !important;
            border: none !important;
        }
        body {
            background: #fff !important;
        }
        .table-scroll > table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: fixed;
        }
        .table-scroll th,
        .table-scroll td {
            word-break: break-word;
        }
    }

    @if($isEmbed)
        aside, header, .sidebar-overlay {
            display: none !important;
        }
        main {
            padding: 0 !important;
        }
        .page-title, .page-subtitle, .portal-switcher {
            display: none !important;
        }
        .admin-card {
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
        }
        .transaction-embed {
            box-shadow: none !important;
            border: none !important;
            padding: 0.5cm !important;
            background: transparent !important;
        }
        .dt-toolbar, .dt-footer, .dataTables_wrapper {
            display: none !important;
        }
        .table-scroll > table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: fixed;
        }
        .table-scroll th,
        .table-scroll td {
            word-break: break-word;
        }
        .table-scroll th:nth-child(1),
        .table-scroll td:nth-child(1) {
            width: 45%;
        }
        .table-scroll th:nth-child(2),
        .table-scroll td:nth-child(2) {
            width: 10%;
        }
        .table-scroll th:nth-child(3),
        .table-scroll td:nth-child(3),
        .table-scroll th:nth-child(4),
        .table-scroll td:nth-child(4) {
            width: 22.5%;
        }
    @endif
</style>

<div class="{{ $cardClass }}">
    <div class="flex flex-wrap justify-between items-start gap-4 border-b border-slate-200 pb-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">SMART POS</h3>
            <p class="text-sm text-slate-500">Nota Transaksi</p>
        </div>
        <div class="text-right">
            <div class="text-sm text-slate-500">Ref ID</div>
            <div class="font-mono text-sm font-semibold">{{ $transaction->reference }}</div>
            <div class="text-xs text-slate-500 mt-1">
                {{ ($transaction->processed_at ?? $transaction->created_at)?->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="space-y-2">
            <div class="text-xs uppercase tracking-wide text-slate-400">Info Transaksi</div>
            @php
                $statusText = strtoupper($transaction->status ?? 'UNKNOWN');
                $statusClass = match (strtolower($transaction->status ?? '')) {
                    'completed' => 'bg-emerald-50 text-emerald-600',
                    'pending' => 'bg-amber-50 text-amber-600',
                    'cancelled', 'failed', 'expired', 'deny' => 'bg-rose-50 text-rose-600',
                    default => 'bg-slate-100 text-slate-600',
                };
            @endphp
            <div class="text-sm flex items-center gap-2">
                <span class="text-slate-500">Status:</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">{{ $statusText }}</span>
            </div>
            <div class="text-sm"><span class="text-slate-500">Lokasi:</span> {{ $transaction->location?->name ?? '-' }}</div>
            <div class="text-sm"><span class="text-slate-500">Kasir:</span> {{ $transaction->kasir?->name ?? '-' }}</div>
            <div class="text-sm"><span class="text-slate-500">Channel:</span> {{ ucfirst($transaction->channel ?? '-') }}</div>
            @if(data_get($transaction->metadata, 'cancel_reason'))
                <div class="text-sm text-red-600"><span class="text-slate-500">Alasan Batal:</span> {{ data_get($transaction->metadata, 'cancel_reason') }}</div>
            @endif
        </div>
        <div class="space-y-2">
            <div class="text-xs uppercase tracking-wide text-slate-400">Pembeli</div>
            <div class="text-sm"><span class="text-slate-500">Santri:</span>
                @if($transaction->santri)
                    {{ $transaction->santri->name }} ({{ $transaction->santri->nis }})
                @else
                    -
                @endif
            </div>
            <div class="text-sm"><span class="text-slate-500">Metode:</span>
                {{ ucfirst($transaction->primary_payment_method ?? ($transaction->gateway_amount > 0 ? 'gateway' : 'cash')) }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="admin-card bg-slate-50 border border-slate-200 md:col-span-2">
            <div class="text-xs uppercase tracking-wide text-slate-400 mb-3">Validasi Struk</div>
            <div class="flex flex-wrap items-center gap-6">
                <div class="text-center">
                    <div id="qr-code" class="mx-auto w-[120px] h-[120px] border border-slate-200 rounded-lg bg-white flex items-center justify-center text-xs text-slate-400">QR</div>
                    <div class="text-xs text-slate-500 mt-2">Scan untuk verifikasi</div>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <svg id="barcode"></svg>
                    <div class="text-xs text-slate-500 mt-2">Ref: {{ $transaction->reference }}</div>
                </div>
            </div>
        </div>
        <div class="admin-card bg-slate-50 border border-slate-200">
            <div class="text-xs uppercase tracking-wide text-slate-400 mb-3">Payment Gateway</div>
            @if($transaction->payments->count())
                <div class="space-y-3">
                    @foreach($transaction->payments as $payment)
                        @php
                            $status = strtoupper($payment->status ?? 'PENDING');
                            $refundStatus = in_array($payment->status, ['refund_pending', 'refunded'], true)
                                ? strtoupper($payment->status)
                                : (data_get($payment->metadata, 'refund_key') ? 'REFUND_REQUESTED' : '-');
                        @endphp
                        <div class="text-sm">
                            <div class="font-semibold text-slate-700">{{ strtoupper($payment->provider) }}</div>
                            <div class="text-xs text-slate-500">Status: <span class="font-medium">{{ $status }}</span></div>
                            <div class="text-xs text-slate-500">Refund: <span class="font-medium">{{ $refundStatus }}</span></div>
                            <div class="text-xs text-slate-500">Ref: {{ $payment->provider_reference ?? '-' }}</div>
                            <div class="text-xs text-slate-500">Jumlah: Rp{{ number_format($payment->amount, 0, ',', '.') }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-500">Tidak ada pembayaran gateway.</p>
            @endif
            @if(data_get($transaction->metadata, 'refund_errors'))
                <div class="mt-3 text-xs text-red-600">
                    Refund gagal: {{ json_encode(data_get($transaction->metadata, 'refund_errors')) }}
                </div>
            @endif
        </div>
    </div>

    <div class="table-scroll">
        <table class="{{ $isEmbed ? '' : 'datatable' }} w-full">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $item->product_name }}</div>
                            @if($item->product_sku)
                                <div class="text-xs text-slate-500">{{ $item->product_sku }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">Rp{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right font-medium">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="admin-card bg-slate-50 border border-slate-200">
            <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">Rincian Pembayaran</div>
            <div class="flex justify-between text-sm mb-1"><span class="text-slate-500">Tunai</span><span>Rp{{ number_format($transaction->cash_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm mb-1"><span class="text-slate-500">Saldo</span><span>Rp{{ number_format($transaction->wallet_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm mb-1"><span class="text-slate-500">Gateway</span><span>Rp{{ number_format($transaction->gateway_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm mt-3 border-t border-slate-200 pt-2 font-semibold">
                <span>Total Dibayar</span>
                <span>Rp{{ number_format($transaction->paid_amount ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="admin-card bg-emerald-50 border border-emerald-100">
            <div class="text-xs uppercase tracking-wide text-emerald-600 mb-2">Ringkasan</div>
            <div class="flex justify-between text-sm mb-1"><span class="text-emerald-700">Subtotal</span><span>Rp{{ number_format($transaction->sub_total, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm mb-1"><span class="text-emerald-700">Diskon</span><span>Rp{{ number_format($transaction->discount_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm mb-1"><span class="text-emerald-700">Pajak</span><span>Rp{{ number_format($transaction->tax_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-base font-bold mt-3 border-t border-emerald-200 pt-2">
                <span>Total</span>
                <span>Rp{{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-sm mt-2 text-emerald-700">
                <span>Kembalian</span>
                <span>Rp{{ number_format($transaction->change_amount ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const qrTarget = document.getElementById('qr-code');
        const qrValue = @json($verificationUrl ?? '');
        if (qrTarget && window.QRCode && qrValue) {
            const canvas = document.createElement('canvas');
            qrTarget.innerHTML = '';
            qrTarget.appendChild(canvas);
            window.QRCode.toCanvas(canvas, qrValue, {
                width: 120,
                margin: 1,
                color: {
                    dark: '#0f172a',
                    light: '#ffffff',
                }
            }).catch((error) => {
                console.warn('QR gagal dibuat', error);
            });
        }

        if (window.JsBarcode) {
            const barcodeEl = document.getElementById('barcode');
            if (barcodeEl) {
                JsBarcode(barcodeEl, @json($transaction->reference), {
                    format: 'CODE128',
                    height: 60,
                    displayValue: false,
                    lineColor: '#0f172a',
                });
            }
        }
    });
</script>
@endsection
