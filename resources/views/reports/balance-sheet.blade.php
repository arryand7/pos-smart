@extends('layouts.finance')

@section('title', 'Laporan Neraca')
@section('subtitle')
    Posisi per {{ $asOf->toDateString() }}
@endsection

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.balance-sheet.pdf', request()->query()) }}" class="btn btn-danger btn-sm">Export PDF</a>
        <a href="{{ route('reports.export-excel', request()->query()) }}" class="btn btn-outline btn-sm">Export Excel</a>
    </div>
@endsection

@section('content')
    @php
        $currency = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    @endphp
    <form class="card form-grid" method="GET">
        <label class="form-label">
            Tanggal
            <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="form-input">
        </label>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Terapkan</button>
        </div>
    </form>

    <div class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Total Aset</p>
            <h2 class="stat-value">{{ $currency($totalAssets) }}</h2>
            <p class="stat-meta">Akumulasi aset lancar & tetap</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Total Liabilitas</p>
            <h2 class="stat-value">{{ $currency($totalLiabilities) }}</h2>
            <p class="stat-meta">Utang usaha & saldo santri</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Total Ekuitas</p>
            <h2 class="stat-value">{{ $currency($totalEquities) }}</h2>
            <p class="stat-meta">Modal & saldo laba</p>
        </article>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Aset</h3>
            @if($assets->isEmpty())
                <p class="text-sm text-slate-400">Belum ada akun aset yang tercatat.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($assets as $line)
                        <tr>
                            <td>{{ $line->code }} · {{ $line->name }}</td>
                            <td class="text-right font-semibold">{{ $currency($line->balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Liabilitas</h3>
            @if($liabilities->isEmpty())
                <p class="text-sm text-slate-400">Belum ada akun liabilitas yang tercatat.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($liabilities as $line)
                        <tr>
                            <td>{{ $line->code }} · {{ $line->name }}</td>
                            <td class="text-right font-semibold">{{ $currency($line->balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card md:col-span-2">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Ekuitas</h3>
            @if($equities->isEmpty())
                <p class="text-sm text-slate-400">Belum ada akun ekuitas yang tercatat.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($equities as $line)
                        <tr>
                            <td>{{ $line->code }} · {{ $line->name }}</td>
                            <td class="text-right font-semibold">{{ $currency($line->balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
