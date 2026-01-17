@extends('layouts.admin')

@section('title', 'Smartphone Wallet Santri')
@section('subtitle', 'Kelola saldo, topup manual, dan status wallet santri.')

@section('content')
<div class="admin-card">
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
            @foreach($santris as $santri)
            <tr>
                <td>
                    <div class="font-medium text-slate-800">{{ $santri->nama }}</div>
                    @if($santri->wali)
                        <div class="text-xs text-slate-500">Wali: {{ $santri->wali->name }}</div>
                    @endif
                </td>
                <td class="text-sm text-slate-600">
                    <div>{{ $santri->nis }}</div>
                    <div class="text-xs text-slate-400">{{ $santri->nisn }}</div>
                </td>
                <td>
                    <span class="font-bold text-emerald-600">Rp{{ number_format($santri->balance, 0, ',', '.') }}</span>
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
            @endforeach
        </tbody>
        </table>
    </div>
</div>
@endsection
