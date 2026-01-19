@extends('layouts.admin')

@section('title', 'Data Santri')

@section('content')
    <div class="card">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Santri & Wallet</h2>
                <p class="text-sm text-slate-500">Kelola saldo, limit harian, dan status dompet santri.</p>
            </div>
        </div>

        <form method="GET" class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-xs font-semibold text-slate-500">
                    Cari
                    <input class="form-input mt-1 w-64" type="text" name="search" placeholder="Nama / NIS / Wali" value="{{ request('search') }}">
                </label>
                <label class="text-xs font-semibold text-slate-500">
                    Per halaman
                    <select name="per_page" class="form-select mt-1">
                        @foreach([10,15,25,50,100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="direction" value="{{ request('direction') }}">
                <button type="submit" class="btn btn-secondary">Terapkan</button>
            </div>
            <div class="flex items-center gap-2">
                @php $exportQuery = request()->except(['export', 'page']); @endphp
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'excel'])) }}">Excel</a>
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'csv'])) }}">CSV</a>
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'pdf'])) }}">PDF</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <x-sortable-th field="name" label="Santri" />
                    <x-sortable-th field="nis" label="NIS" />
                    <x-sortable-th field="qr_code" label="QR Code" />
                    <x-sortable-th field="wallet_balance" label="Saldo" />
                    <x-sortable-th field="daily_limit" label="Limit" />
                    <x-sortable-th field="is_wallet_locked" label="Status Dompet" />
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($santris as $santri)
                    <tr>
                        <td>
                            <strong>{{ $santri->name }}</strong><br>
                            <small>Wali: {{ $santri->wali?->name ?? '-' }}</small>
                        </td>
                        <td>{{ $santri->nis }}</td>
                        <td class="text-xs font-mono text-slate-500">{{ $santri->qr_code ?? '-' }}</td>
                        <td>Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</td>
                        <td class="text-xs text-slate-500 space-y-1">
                            <div>Harian: Rp{{ number_format($santri->daily_limit ?? 0, 0, ',', '.') }}</div>
                            <div>Mingguan: Rp{{ number_format($santri->weekly_limit ?? 0, 0, ',', '.') }}</div>
                            <div>Bulanan: Rp{{ number_format($santri->monthly_limit ?? 0, 0, ',', '.') }}</div>
                        </td>
                        <td>
                            <span class="status {{ $santri->is_wallet_locked ? 'inactive' : 'active' }}">
                                {{ $santri->is_wallet_locked ? 'Diblokir' : 'Aktif' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.santri.edit', $santri) }}" class="btn btn-outline btn-sm">Pengaturan</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-6 text-slate-400">Belum ada santri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $santris->links() }}
        </div>
    </div>
@endsection
