@extends('layouts.admin')

@section('title', 'Detail Wallet - ' . $santri->name)

@section('content')
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">{{ $santri->name }}</h2>
            <p class="text-sm text-slate-500">NIS: {{ $santri->nis }} • NISN: {{ $santri->nisn ?? '-' }}</p>
            <p class="text-sm text-slate-500">Wali: {{ $santri->wali?->name ?? 'Belum ada' }}</p>
        </div>
        <div class="text-left md:text-right">
            <p class="text-sm text-slate-500">Saldo Saat Ini</p>
            <h2 class="text-2xl font-bold text-emerald-700">Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</h2>
            @if($santri->is_wallet_locked)
                <span class="status inactive mt-2">Dikunci</span>
            @else
                <span class="status active mt-2">Aktif</span>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-3 mt-6">
        <a href="{{ route('admin.wallets.topup', $santri) }}" class="btn btn-primary">+ Top Up Manual</a>
        <a href="{{ route('admin.wallets.index') }}" class="btn btn-outline">← Kembali</a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

<div class="card">
    <h3 class="text-lg font-semibold text-emerald-700 mb-4">Koreksi Saldo</h3>
    <form method="POST" action="{{ route('admin.wallets.adjust', $santri) }}" class="form-grid items-end">
        @csrf
        <label class="form-label">
            Tipe
            <select class="form-select" name="type" required>
                <option value="credit">Tambah Saldo</option>
                <option value="debit">Kurangi Saldo</option>
            </select>
        </label>
        <label class="form-label">
            Nominal
            <input class="form-input" type="number" name="amount" min="1" required>
        </label>
        <label class="form-label">
            Alasan
            <input class="form-input" type="text" name="reason" required placeholder="Alasan koreksi...">
        </label>
        <button type="submit" class="btn btn-outline">Koreksi</button>
    </form>
</div>

<div class="card">
    <h3 class="text-lg font-semibold text-emerald-700 mb-4">Riwayat Transaksi</h3>
    <div class="overflow-x-auto">
        <div class="table-scroll">
            <table class="table table-compact">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Channel</th>
                <th class="text-right">Nominal</th>
                <th class="text-right">Saldo Akhir</th>
                <th>Keterangan</th>
                <th>Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>{{ $tx->occurred_at?->format('d M Y H:i') ?? $tx->created_at?->format('d M Y H:i') }}</td>
                    <td>
                        <span class="{{ $tx->type === 'credit' ? 'text-emerald-700' : 'text-rose-600' }} font-semibold">
                            {{ $tx->type === 'credit' ? '+ Masuk' : '- Keluar' }}
                        </span>
                    </td>
                    <td>{{ ucfirst($tx->channel ?? '-') }}</td>
                    <td class="text-right font-semibold">
                        Rp{{ number_format($tx->amount, 0, ',', '.') }}
                    </td>
                    <td class="text-right">
                        Rp{{ number_format($tx->balance_after, 0, ',', '.') }}
                    </td>
                    <td class="max-w-[200px] truncate">
                        {{ $tx->description ?? '-' }}
                    </td>
                    <td>{{ $tx->performer?->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-6 text-slate-400">Belum ada transaksi.</td>
                </tr>
            @endforelse
        </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">
        {{ $transactions->links() }}
    </div>
</div>
@endsection
