@extends('layouts.finance')

@section('title', 'Dashboard Bendahara')

@section('content')
    @php
        $currency = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    @endphp
    <div class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Penjualan 30 Hari</p>
            <h2 class="stat-value">{{ $currency($sales) }}</h2>
            <p class="stat-meta">Transaksi status selesai</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Liabilitas Saldo Santri</p>
            <h2 class="stat-value">{{ $currency($walletLiability) }}</h2>
            <p class="stat-meta">Total saldo dompet aktif</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Pembayaran Tertunda</p>
            <h2 class="stat-value">{{ $pendingPayments }}</h2>
            <p class="stat-meta">Gateway pending/initiated</p>
        </article>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-emerald-700">Stock Alert</h3>
                <span class="badge badge-warning">Pantau</span>
            </div>
            @if($stockAlerts->isEmpty())
                <p class="text-sm text-slate-400">Semua stok aman.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Stok</th>
                        <th>Ambang</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($stockAlerts as $product)
                        <tr>
                            <td class="font-medium text-slate-700">{{ $product->name }}</td>
                            <td>{{ $product->stock }}</td>
                            <td>{{ $product->stock_alert }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-emerald-700">Pembayaran Terbaru</h3>
                <span class="badge badge-info">Gateway</span>
            </div>
            @if($recentPayments->isEmpty())
                <p class="text-sm text-slate-400">Belum ada aktivitas gateway.</p>
            @else
                <ul class="space-y-3">
                    @foreach($recentPayments as $payment)
                        <li class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-800">{{ strtoupper($payment->provider) }}</div>
                                <p class="text-sm text-slate-500">{{ $currency($payment->amount) }}</p>
                                <p class="text-xs text-slate-400">{{ $payment->created_at?->diffForHumans() }}</p>
                            </div>
                            <span class="badge {{ $payment->status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($payment->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-emerald-700">Produk Terlaris 30 Hari</h3>
            <span class="badge badge-success">Top</span>
        </div>
        @if($topProducts->isEmpty())
            <p class="text-sm text-slate-400">Belum ada transaksi yang selesai.</p>
        @else
            <div class="table-scroll">
                <table class="table table-compact">
                <thead>
                <tr>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Pendapatan</th>
                </tr>
                </thead>
                <tbody>
                @foreach($topProducts as $item)
                    <tr>
                        <td class="font-medium text-slate-700">{{ $item->product_name }}</td>
                        <td>{{ $item->total_qty }}</td>
                        <td>{{ $currency($item->revenue) }}</td>
                    </tr>
                @endforeach
                </tbody>
                </table>
            </div>
        @endif
    </section>

    @include('partials.revenue-chart', [
        'title' => 'Grafik Pemasukan',
        'subtitle' => 'Total pembelian semua barang (pemasukan) dengan indikator MA Cross.',
    ])

    <section class="card">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-semibold text-emerald-700">Laporan Keuangan</h3>
                <p class="text-sm text-slate-500">Akses laporan detail untuk evaluasi harian dan bulanan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.profit-loss') }}" class="btn btn-outline btn-sm">Laba/Rugi</a>
                <a href="{{ route('reports.balance-sheet') }}" class="btn btn-outline btn-sm">Neraca</a>
                <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline btn-sm">Arus Kas</a>
            </div>
        </div>
    </section>
@endsection
