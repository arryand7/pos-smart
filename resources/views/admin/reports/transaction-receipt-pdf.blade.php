<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $transaction->reference }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            color: #111;
        }
        .receipt {
            width: 100%;
            padding: 8px 10px;
        }
        .center { text-align: center; }
        .divider { border-top: 1px dashed #444; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; font-size: 10px; }
        .items { margin-top: 6px; }
        .item { font-size: 10px; margin-bottom: 4px; }
        .item .name { font-weight: 700; }
        .item .sub { display: flex; justify-content: space-between; }
        .total { font-weight: 700; }
        .qr-wrap { text-align: center; margin-top: 6px; }
        .qr-wrap img { display: inline-block; width: 120px; height: 120px; }
        .barcode img { display: block; width: 100%; max-width: 240px; margin: 6px auto 0; }
        .watermark {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: 4px;
            text-transform: uppercase;
            opacity: 0.08;
            transform: rotate(-20deg);
            color: #0f172a;
            z-index: -1;
        }
        .watermark.valid { color: #16a34a; }
        .watermark.pending { color: #f59e0b; }
        .watermark.invalid { color: #dc2626; }
    </style>
</head>
<body>
    @php
        $status = strtolower($transaction->status ?? '');
        $watermark = $status === 'completed' ? 'VALID' : ($status === 'pending' ? 'PENDING' : 'INVALID');
        $wmClass = $status === 'completed' ? 'valid' : ($status === 'pending' ? 'pending' : 'invalid');
    @endphp
    <div class="watermark {{ $wmClass }}">{{ $watermark }}</div>
    <div class="receipt">
        <div class="center">
            <div style="font-weight:700;">SMART POS</div>
            <div>Sabira Mart</div>
        </div>

        <div class="divider"></div>

        <div class="row"><span>Ref</span><span>{{ $transaction->reference }}</span></div>
        <div class="row"><span>Waktu</span><span>{{ ($transaction->processed_at ?? $transaction->created_at)?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $transaction->kasir?->name ?? '-' }}</span></div>
        <div class="row"><span>Lokasi</span><span>{{ $transaction->location?->name ?? '-' }}</span></div>
        @if($transaction->santri)
            <div class="row"><span>Santri</span><span>{{ $transaction->santri->name }}</span></div>
        @endif

        <div class="divider"></div>

        <div class="items">
            @foreach($transaction->items as $item)
                <div class="item">
                    <div class="name">{{ $item->product_name }}</div>
                    <div class="sub">
                        <span>{{ $item->quantity }} x {{ number_format($item->unit_price, 0, ',', '.') }}</span>
                        <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="divider"></div>

        <div class="row"><span>Subtotal</span><span>{{ number_format($transaction->sub_total, 0, ',', '.') }}</span></div>
        <div class="row"><span>Diskon</span><span>{{ number_format($transaction->discount_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Pajak</span><span>{{ number_format($transaction->tax_amount, 0, ',', '.') }}</span></div>
        <div class="row total"><span>Total</span><span>{{ number_format($transaction->total_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Tunai</span><span>{{ number_format($transaction->cash_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Saldo</span><span>{{ number_format($transaction->wallet_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Gateway</span><span>{{ number_format($transaction->gateway_amount, 0, ',', '.') }}</span></div>
        <div class="row"><span>Kembalian</span><span>{{ number_format($transaction->change_amount ?? 0, 0, ',', '.') }}</span></div>

        <div class="divider"></div>

        @if(!empty($qrDataUri))
            <div class="qr-wrap">
                <img src="{{ $qrDataUri }}" alt="QR">
            </div>
        @endif
        @if(!empty($barcodeDataUri))
            <div class="barcode">
                <img src="{{ $barcodeDataUri }}" alt="Barcode">
            </div>
        @endif
        <div class="center">Scan untuk verifikasi struk</div>

        <div class="divider"></div>

        <div class="center">Terima kasih</div>
    </div>
</body>
</html>
