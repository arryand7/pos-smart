@php
    $title = 'Laporan Neraca';
    $date = $asOf->format('d M Y');
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
        .balance-check { font-size: 12px; font-weight: bold; margin-top: 15px; padding: 10px; border-radius: 5px; }
        .balance-check.balanced { color: #007A5C; background: #e8f5e9; }
        .balance-check.unbalanced { color: #c62828; background: #ffebee; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SMART - Sabira Mart</h1>
        <h2>{{ $title }}</h2>
        <p>Per tanggal: {{ $date }}</p>
    </div>

    <div class="section">
        <div class="section-title">Aset</div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td class="text-right">Rp{{ number_format($account->balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;">Tidak ada data</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total Aset</td>
                    <td class="text-right">Rp{{ number_format($totalAssets, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Liabilitas</div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liabilities as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td class="text-right">Rp{{ number_format($account->balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;">Tidak ada data</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total Liabilitas</td>
                    <td class="text-right">Rp{{ number_format($totalLiabilities, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Ekuitas</div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equities as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td class="text-right">Rp{{ number_format($account->balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;">Tidak ada data</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total Ekuitas</td>
                    <td class="text-right">Rp{{ number_format($totalEquities, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @php
        $liabilitiesEquity = $totalLiabilities + $totalEquities;
        $isBalanced = abs($totalAssets - $liabilitiesEquity) < 0.01;
    @endphp
    <div class="balance-check {{ $isBalanced ? 'balanced' : 'unbalanced' }}">
        Total Liabilitas + Ekuitas: Rp{{ number_format($liabilitiesEquity, 0, ',', '.') }}
        @if($isBalanced)
            ✓ Neraca Seimbang
        @else
            ✗ Neraca Tidak Seimbang (Selisih: Rp{{ number_format(abs($totalAssets - $liabilitiesEquity), 0, ',', '.') }})
        @endif
    </div>

    <div class="footer">
        Dicetak pada: {{ now()->format('d M Y H:i') }} | SMART - Sabira Mart Integrated System
    </div>
</body>
</html>
