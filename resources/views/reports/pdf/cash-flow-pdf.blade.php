@php
    $title = 'Laporan Arus Kas';
    $period = $start->format('d M Y') . ' - ' . $end->format('d M Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #007A5C; padding-bottom: 10px; }
        .header h1 { color: #007A5C; font-size: 18px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 10px; }
        .section { margin-bottom: 15px; }
        .section-title { background: #007A5C; color: white; padding: 5px 10px; font-weight: bold; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f6f7; font-weight: bold; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #f0f0f0; }
        .inflow { color: #2e7d32; }
        .outflow { color: #c62828; }
        .summary-box { margin-top: 15px; padding: 10px; background: #f5f6f7; border-radius: 5px; }
        .summary-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #ddd; }
        .summary-row:last-child { border-bottom: none; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SMART - Sabira Mart</h1>
        <h2>{{ $title }}</h2>
        <p>Periode: {{ $period }}</p>
    </div>

    <div class="section">
        <div class="section-title">Arus Kas Masuk</div>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Referensi</th>
                    <th>Keterangan</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inflows as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line->entry_date)->format('d/m/Y') }}</td>
                    <td>{{ $line->reference ?? '-' }}</td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td class="text-right inflow">Rp{{ number_format($line->net_amount, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:#999;">Tidak ada arus kas masuk</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="3">Total Kas Masuk</td>
                    <td class="text-right inflow">Rp{{ number_format($totalIn, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Arus Kas Keluar</div>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Referensi</th>
                    <th>Keterangan</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($outflows as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line->entry_date)->format('d/m/Y') }}</td>
                    <td>{{ $line->reference ?? '-' }}</td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td class="text-right outflow">Rp{{ number_format(abs($line->net_amount), 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:#999;">Tidak ada arus kas keluar</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="3">Total Kas Keluar</td>
                    <td class="text-right outflow">Rp{{ number_format($totalOut, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="summary-box">
        <div class="summary-row">
            <span>Saldo Awal Kas</span>
            <span>Rp{{ number_format($openingBalance, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>Arus Kas Bersih</span>
            <span class="{{ $netCash >= 0 ? 'inflow' : 'outflow' }}">Rp{{ number_format($netCash, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>Saldo Akhir Kas</span>
            <span>Rp{{ number_format($closingBalance, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="footer">
        Dicetak pada: {{ now()->format('d M Y H:i') }} | SMART - Sabira Mart Integrated System
    </div>
</body>
</html>
