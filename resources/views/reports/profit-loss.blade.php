@extends('layouts.finance')

@section('title', 'Laporan Laba Rugi')
@section('subtitle')
    Periode {{ $start->toDateString() }} s/d {{ $end->toDateString() }}
@endsection

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.profit-loss.pdf', request()->query()) }}" class="btn btn-danger btn-sm">Export PDF</a>
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

    <div class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Total Pendapatan</p>
            <h2 class="stat-value">{{ $currency($totalRevenue) }}</h2>
            <p class="stat-meta">Akumulasi akun pendapatan</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Total Beban</p>
            <h2 class="stat-value">{{ $currency($totalExpense) }}</h2>
            <p class="stat-meta">Akumulasi akun beban</p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Laba (Rugi) Bersih</p>
            <h2 class="stat-value {{ $netIncome >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                {{ $currency($netIncome) }}
            </h2>
            <p class="stat-meta">Selisih pendapatan dan beban</p>
        </article>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Pendapatan</h3>
            @if($revenues->isEmpty())
                <p class="text-sm text-slate-400">Belum ada jurnal pendapatan di periode ini.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($revenues as $line)
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
            <h3 class="text-lg font-semibold text-emerald-700 mb-4">Beban</h3>
            @if($expenses->isEmpty())
                <p class="text-sm text-slate-400">Belum ada jurnal beban di periode ini.</p>
            @else
                <div class="table-scroll">
                    <table class="table table-compact">
                    <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($expenses as $line)
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
