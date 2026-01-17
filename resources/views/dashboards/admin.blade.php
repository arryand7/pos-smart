@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Produk Aktif</p>
            <h2 class="stat-value">{{ $stats['active_products'] }}</h2>
            <p class="stat-meta">Inventaris aktif saat ini</p>
        </div>
        <div class="stat-card {{ $stats['low_stock'] > 0 ? 'border-rose-200 bg-rose-50' : '' }}">
            <p class="stat-label">Stok Rendah</p>
            <h2 class="stat-value {{ $stats['low_stock'] > 0 ? 'text-rose-600' : '' }}">{{ $stats['low_stock'] }}</h2>
            <p class="stat-meta">{{ $stats['low_stock'] > 0 ? 'Perlu restock segera' : 'Stok aman' }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Total Santri</p>
            <h2 class="stat-value">{{ $stats['total_santri'] }}</h2>
            <p class="stat-meta">Akun santri terdaftar</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Penjualan Hari Ini</p>
            <h2 class="stat-value text-emerald-700">Rp{{ number_format($stats['today_sales'], 0, ',', '.') }}</h2>
            <p class="stat-meta">{{ $stats['today_transactions'] }} transaksi</p>
        </div>
    </div>

    @if($lowStockProducts->isNotEmpty())
    <div class="card card-danger">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg">!</div>
                <div>
                    <h3 class="text-lg font-semibold">Peringatan Stok Rendah</h3>
                    <p class="text-sm text-rose-700/80">Prioritaskan restock produk di bawah ambang minimum.</p>
                </div>
            </div>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">Lihat Semua Produk</a>
        </div>
        <div class="overflow-x-auto">
            <div class="table-scroll">
                <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Lokasi</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Minimum</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowStockProducts as $product)
                    <tr>
                        <td>
                            <div class="font-semibold text-slate-800">{{ $product->name }}</div>
                            <div class="text-xs text-slate-500 font-mono">SKU: {{ $product->sku }}</div>
                        </td>
                        <td>{{ $product->location?->name ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge badge-danger">{{ $product->stock }}</span>
                        </td>
                        <td class="text-center text-slate-500">{{ $product->stock_alert }}</td>
                        <td class="text-center">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-ghost btn-sm">Restock</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-3">
            <div>
                <h3 class="text-lg font-semibold text-emerald-700">Aktivitas Terkini</h3>
                <p class="text-sm text-slate-500">Ringkasan aktivitas sistem dan penjualan.</p>
            </div>
            <span class="chip">Ringkasan</span>
        </div>
        <div class="soft-panel p-6 text-center text-sm text-slate-500">
            Grafik analitik lengkap tersedia di Dashboard Bendahara.
        </div>
    </div>
@endsection
