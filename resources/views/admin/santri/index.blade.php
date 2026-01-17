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

        <form method="GET" class="mb-4">
            <input class="form-input" type="text" name="search" placeholder="Cari nama / NIS" value="{{ request('search') }}">
        </form>

        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <th>Santri</th>
                    <th>NIS</th>
                    <th>Saldo</th>
                    <th>Limit Harian</th>
                    <th>Status Dompet</th>
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
                        <td>Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</td>
                        <td>Rp{{ number_format($santri->daily_limit, 0, ',', '.') }}</td>
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
                    <tr><td colspan="6" class="text-center py-6 text-slate-400">Belum ada santri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $santris->links() }}
        </div>
    </div>
@endsection
