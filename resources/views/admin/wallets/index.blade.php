@extends('layouts.admin')

@section('title', 'Wallet Santri')
@section('subtitle', 'Kelola saldo, topup manual, dan status wallet santri.')

@section('content')
<div class="admin-card">
    <form method="GET" action="{{ route('admin.wallets.index') }}" class="flex flex-wrap items-end gap-3 mb-6">
        <label class="form-label flex-1 min-w-[200px]">
            Cari Santri
            <input class="form-input" type="text" name="search" value="{{ request('search') }}" placeholder="Nama, NIS, NISN, atau nama wali...">
        </label>
        <label class="form-label">
            Status
            <select class="form-select" name="status">
                <option value="">Semua</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                <option value="locked" {{ request('status') === 'locked' ? 'selected' : '' }}>Dikunci</option>
            </select>
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Cari</button>
        @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('admin.wallets.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
    </form>

    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Santri</th>
                <th>NIS/NISN</th>
                <th>Saldo Saat Ini</th>
                <th>Status Wallet</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($santris as $santri)
            <tr>
                <td>
                    <div class="font-medium text-slate-800">{{ $santri->name }}</div>
                    @if($santri->wali)
                        <div class="text-xs text-slate-500">Wali: {{ $santri->wali->name }}</div>
                    @endif
                </td>
                <td class="text-sm text-slate-600">
                    <div>{{ $santri->nis }}</div>
                    <div class="text-xs text-slate-400">{{ $santri->nisn }}</div>
                </td>
                <td>
                    <span class="font-bold text-emerald-600">Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</span>
                </td>
                <td>
                    @if($santri->is_wallet_locked)
                        <span class="status inactive">LOCKED</span>
                    @else
                        <span class="status active">ACTIVE</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.wallets.show', $santri) }}" class="btn btn-outline btn-sm">
                            Detail
                        </a>
                        <a href="{{ route('admin.wallets.topup', $santri) }}" class="btn btn-ghost btn-sm">
                            Top Up
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-8 text-slate-400">
                    @if(request()->hasAny(['search', 'status']))
                        Tidak ada santri yang cocok dengan pencarian.
                    @else
                        Belum ada data santri.
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
        </table>
    </div>
    @if($santris->hasPages())
        <div class="mt-6">
            {{ $santris->links() }}
        </div>
    @endif
</div>
@endsection
