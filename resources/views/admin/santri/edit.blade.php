@extends('layouts.admin')

@section('title', 'Pengaturan Santri')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Pengaturan Santri</h2>
            <p class="text-sm text-slate-500">{{ $santri->name }} • NIS: {{ $santri->nis }}</p>
        </div>
        <form method="POST" action="{{ route('admin.santri.update', $santri) }}" class="form-stack">
            @method('PUT')
            @csrf
            <div class="form-grid">
                <label class="form-label">Saldo Saat Ini
                    <input class="form-input" type="text" value="Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}" disabled>
                </label>
                <label class="form-label">Limit Harian
                    <input class="form-input" type="number" name="daily_limit" min="0" value="{{ old('daily_limit', $santri->daily_limit) }}">
                </label>
                <label class="form-label">Limit Bulanan
                    <input class="form-input" type="number" name="monthly_limit" min="0" value="{{ old('monthly_limit', $santri->monthly_limit) }}">
                </label>
                <label class="form-label">Status Dompet
                    <select class="form-select" name="is_wallet_locked">
                        <option value="0" @selected(! $santri->is_wallet_locked)>Aktif</option>
                        <option value="1" @selected($santri->is_wallet_locked)>Diblokir</option>
                    </select>
                </label>
            </div>
            <div>
                <label class="form-label">Catatan
                    <textarea class="form-textarea" name="notes" rows="3">{{ old('notes', $santri->notes) }}</textarea>
                </label>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.santri.index') }}" class="btn btn-outline">Kembali</a>
            </div>
        </form>
    </div>
@endsection
