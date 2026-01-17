@extends('layouts.admin')

@section('title', 'Pusat Laporan')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Financial Reports -->
    <div class="col-span-full">
        <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
            <span>💰</span> Laporan Keuangan
        </h3>
    </div>

    <a href="{{ route('reports.profit-loss') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-emerald-200 transition-colors">
                📉
            </div>
            <span class="text-xs font-bold bg-slate-100 text-slate-600 px-2 py-1 rounded-md">Utama</span>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Laba Rugi</h4>
        <p class="text-slate-500 text-sm">Analisis pendapatan dan beban operasional untuk mengetahui profitabilitas.</p>
    </a>

    <a href="{{ route('reports.balance-sheet') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-blue-200 transition-colors">
                ⚖️
            </div>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Neraca Keuangan</h4>
        <p class="text-slate-500 text-sm">Posisi aset, kewajiban, dan ekuitas pesantren per periode tertentu.</p>
    </a>

    <a href="{{ route('reports.cash-flow') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-amber-200 transition-colors">
                💸
            </div>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Arus Kas</h4>
        <p class="text-slate-500 text-sm">Laporan keluar masuk kas operasional, investasi, dan pendanaan.</p>
    </a>

    <!-- Operational Reports -->
    <div class="col-span-full mt-6">
        <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
            <span>📊</span> Laporan Operasional
        </h3>
    </div>

    <a href="{{ route('admin.reports.transactions') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-indigo-200 transition-colors">
                📝
            </div>
            <span class="text-xs font-bold bg-indigo-50 text-indigo-600 px-2 py-1 rounded-md">Detail</span>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Jurnal Transaksi</h4>
        <p class="text-slate-500 text-sm">Log detail semua transaksi keuangan yang tercatat dalam sistem.</p>
    </a>

    <a href="{{ route('admin.reports.sales') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-rose-200 transition-colors">
                🛍️
            </div>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Laporan Penjualan</h4>
        <p class="text-slate-500 text-sm">Analisis penjualan produk, kategori terlaris, dan omset toko.</p>
    </a>

    <a href="{{ route('admin.reports.wallet') }}" class="admin-card hover:translate-y-[-4px] group block decoration-0">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-2xl group-hover:bg-purple-200 transition-colors">
                💳
            </div>
        </div>
        <h4 class="text-xl font-bold text-slate-800 mb-2">Laporan Wallet</h4>
        <p class="text-slate-500 text-sm">Rekapitulasi deposit santri, penggunaan saldo, dan sisa saldo total.</p>
    </a>
</div>
@endsection
