<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $transaction->reference }}</title>
    @vite(['resources/js/receipt.js'])
    <style>
        :root {
            --width: {{ $size }}mm;
        }
        body {
            font-family: "Courier New", monospace;
            background: #fff;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .receipt {
            width: var(--width);
            margin: 0 auto;
            padding: 8px 10px;
        }
        .center { text-align: center; }
        .divider { border-top: 1px dashed #444; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; font-size: 12px; }
        .items { margin-top: 6px; }
        .item { font-size: 12px; margin-bottom: 4px; }
        .item .name { font-weight: 700; }
        .item .sub { display: flex; justify-content: space-between; }
        .total { font-weight: 700; }
        .qr-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 6px; }
        .barcode {
            width: 100%;
            max-width: 240px;
        }
        @media print {
            @page { size: var(--width) auto; margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <div style="font-weight:700;">SMART POS</div>
            <div style="font-size:11px;">Sabira Mart</div>
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

        @if(!empty($verificationUrl))
            <div class="qr-wrap">
                <div id="receipt-qr" data-value="{{ $verificationUrl }}"></div>
                <svg id="receipt-barcode" class="barcode" data-value="{{ $transaction->reference }}"></svg>
            </div>
            <div class="center" style="font-size:10px;">Scan untuk verifikasi struk</div>
            <div class="divider"></div>
        @endif

        <div class="center" style="font-size:11px;">Terima kasih</div>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
