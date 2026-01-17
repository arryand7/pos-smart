@extends('layouts.admin')

@section('title', 'Top Up Wallet - ' . $santri->name)

@section('content')
<div class="card max-w-xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Top Up Manual</h2>
        <p class="text-sm text-slate-500">Catat top up secara manual untuk dompet santri.</p>
    </div>
    
    <div class="soft-panel p-4 mb-6">
        <p class="text-xs text-slate-500">Santri</p>
        <p class="text-lg font-semibold text-slate-800">{{ $santri->name }}</p>
        <p class="text-xs text-slate-500">NIS: {{ $santri->nis }}</p>
        <p class="text-xs text-slate-500 mt-3">Saldo Saat Ini</p>
        <p class="text-2xl font-bold text-emerald-700">Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.wallets.topup.store', $santri) }}">
        @csrf

        <div class="form-stack">
            <label class="form-label">
                Nominal Top Up
                <input type="number" name="amount" min="1000" step="1000" required 
                    value="{{ old('amount') }}"
                    placeholder="Min. Rp1.000"
                    class="form-input form-input-lg">
                @error('amount') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Metode Pembayaran
                <select class="form-select" name="channel" required>
                    <option value="">Pilih Metode</option>
                    <option value="cash" {{ old('channel') === 'cash' ? 'selected' : '' }}>💵 Tunai (Cash)</option>
                    <option value="bank_transfer" {{ old('channel') === 'bank_transfer' ? 'selected' : '' }}>🏦 Transfer Bank</option>
                    <option value="other" {{ old('channel') === 'other' ? 'selected' : '' }}>📝 Lainnya</option>
                </select>
                @error('channel') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Keterangan <span class="form-help">(opsional)</span>
                <input type="text" name="description" value="{{ old('description') }}" 
                    placeholder="Cth: Setoran dari wali"
                    class="form-input">
                @error('description') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>
        </div>

        <div class="flex flex-wrap gap-3 mt-6">
            <button type="submit" class="btn btn-primary flex-1">Proses Top Up</button>
            <a href="{{ route('admin.wallets.show', $santri) }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
