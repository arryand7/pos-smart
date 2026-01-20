<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $transaction->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 11px; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
        .muted { color: #64748b; }
        .row { display: flex; justify-content: space-between; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-weight: 700; font-size: 10px; }
        .badge.valid { background: #dcfce7; color: #15803d; }
        .badge.pending { background: #fef3c7; color: #b45309; }
        .badge.invalid { background: #fee2e2; color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 4px; text-align: left; }
        th { background: #f8fafc; }
        .right { text-align: right; }
        .qr img { width: 110px; height: 110px; }
        .barcode img { width: 100%; max-width: 240px; }
    </style>
</head>
<body>
    @php
        $status = strtolower($transaction->status ?? '');
        $badge = $status === 'completed' ? 'valid' : ($status === 'pending' ? 'pending' : 'invalid');
    @endphp
    <div class="card">
        <div class="row">
            <div>
                <div style="font-size:16px; font-weight:700;">SMART POS</div>
                <div class="muted">Invoice Transaksi</div>
            </div>
            <div style="text-align:right;">
                <div class="muted">Ref ID</div>
                <div style="font-family: monospace; font-weight:700;">{{ $transaction->reference }}</div>
                <div class="muted">{{ ($transaction->processed_at ?? $transaction->created_at)?->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <div style="margin-top: 12px;">
            <span class="badge {{ $badge }}">{{ strtoupper($transaction->status ?? 'UNKNOWN') }}</span>
        </div>

        <div style="margin-top: 12px;" class="row">
            <div>
                <div class="muted">Lokasi</div>
                <div>{{ $transaction->location?->name ?? '-' }}</div>
            </div>
            <div>
                <div class="muted">Kasir</div>
                <div>{{ $transaction->kasir?->name ?? '-' }}</div>
            </div>
            <div>
                <div class="muted">Santri</div>
                <div>{{ $transaction->santri?->name ?? '-' }}</div>
            </div>
        </div>

        <div style="margin-top: 12px;" class="row">
            <div class="qr">
                <img src="{{ $qrDataUri }}" alt="QR">
            </div>
            <div class="barcode">
                <img src="{{ $barcodeDataUri }}" alt="Barcode">
                <div class="muted">Ref: {{ $transaction->reference }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="right">Qty</th>
                    <th class="right">Harga</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">Rp{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td class="right">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 10px;" class="row">
            <div>
                <div class="muted">Pembayaran</div>
                <div>Tunai: Rp{{ number_format($transaction->cash_amount, 0, ',', '.') }}</div>
                <div>Saldo: Rp{{ number_format($transaction->wallet_amount, 0, ',', '.') }}</div>
                <div>Gateway: Rp{{ number_format($transaction->gateway_amount, 0, ',', '.') }}</div>
            </div>
            <div style="text-align:right;">
                <div class="muted">Ringkasan</div>
                <div>Subtotal: Rp{{ number_format($transaction->sub_total, 0, ',', '.') }}</div>
                <div>Diskon: Rp{{ number_format($transaction->discount_amount, 0, ',', '.') }}</div>
                <div>Pajak: Rp{{ number_format($transaction->tax_amount, 0, ',', '.') }}</div>
                <div style="font-weight:700;">Total: Rp{{ number_format($transaction->total_amount, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</body>
</html>
