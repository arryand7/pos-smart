@php
    $title = 'Laporan Laba/Rugi';
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
        .net-income { font-size: 14px; font-weight: bold; color: #007A5C; margin-top: 15px; padding: 10px; background: #e8f5e9; border-radius: 5px; }
        .net-income.negative { color: #c62828; background: #ffebee; }
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
        <div class="section-title">Pendapatan</div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($revenues as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td class="text-right">Rp{{ number_format($account->balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;">Tidak ada data</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total Pendapatan</td>
                    <td class="text-right">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Beban</div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td class="text-right">Rp{{ number_format($account->balance, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#999;">Tidak ada data</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total Beban</td>
                    <td class="text-right">Rp{{ number_format($totalExpense, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="net-income {{ $netIncome < 0 ? 'negative' : '' }}">
        Laba/Rugi Bersih: Rp{{ number_format($netIncome, 0, ',', '.') }}
    </div>

    <div class="footer">
        Dicetak pada: {{ now()->format('d M Y H:i') }} | SMART - Sabira Mart Integrated System
    </div>
</body>
</html>
