@extends('layouts.finance')

@section('title', 'Laporan Arus Kas')
@section('subtitle')
    Periode {{ $start->toDateString() }} s/d {{ $end->toDateString() }}
@endsection

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.cash-flow.pdf', request()->query()) }}" class="btn btn-danger btn-sm">Export PDF</a>
        <a href="{{ route('reports.export-excel', request()->query()) }}" class="btn btn-outline btn-sm">Export Excel</a>
    </div>
@endsection

@section('content')
    @php
        $currency = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    @endphp
    <form class="card form-grid" method="GET">
        <label class="form-label">
            Mulai
            <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-input">
        </label>
        <label class="form-label">
            Selesai
            <input type="date" name="end" value="{{ $end->toDateString() }}" class="form-input">
        </label>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Terapkan</button>
        </div>
    </form>

    @if(! $cashAccount)
        <div class="alert alert-warning">
            Akun kas belum terdaftar. Pastikan kode akun kas diatur pada konfigurasi akuntansi.
        </div>
    @endif

    <div class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Saldo Awal</p>
            <h2 class="stat-value">{{ $currency($openingBalance) }}</h2>
            <p class="stat-meta">Saldo kas sebelum periode</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Kas Masuk</p>
            <h2 class="stat-value">{{ $currency($totalIn) }}</h2>
            <p class="stat-meta">Total arus kas masuk</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Kas Keluar</p>
            <h2 class="stat-value">{{ $currency($totalOut) }}</h2>
            <p class="stat-meta">Total arus kas keluar</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Saldo Akhir</p>
            <h2 class="stat-value">{{ $currency($closingBalance) }}</h2>
            <p class="stat-meta">Saldo kas setelah periode</p>
        </article>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Kas Masuk</h3>
            @if($inflows->isEmpty())
                <p class="text-sm text-slate-400">Belum ada arus kas masuk di periode ini.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Referensi</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($inflows as $line)
                        <tr>
                            <td>{{ $line->entry_date }}</td>
                            <td>{{ $line->reference }} · {{ $line->description ?: 'Aktivitas kas' }}</td>
                            <td class="text-right font-semibold">{{ $currency($line->net_amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Kas Keluar</h3>
            @if($outflows->isEmpty())
                <p class="text-sm text-slate-400">Belum ada arus kas keluar di periode ini.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Referensi</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($outflows as $line)
                        <tr>
                            <td>{{ $line->entry_date }}</td>
                            <td>{{ $line->reference }} · {{ $line->description ?: 'Aktivitas kas' }}</td>
                            <td class="text-right font-semibold">{{ $currency(abs($line->net_amount)) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
