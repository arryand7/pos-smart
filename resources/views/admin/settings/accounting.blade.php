@extends('layouts.admin')

@section('title', 'Pengaturan Akuntansi')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Pengaturan Akuntansi</h2>
        <p class="text-sm text-slate-500">Mapping kode akun untuk jurnal otomatis.</p>
    </div>

    @if(session('status'))
        <div class="alert alert-success mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.accounting.update') }}">
        @csrf
        @method('PUT')

        <div class="form-stack">
            <label class="form-label">
                Akun Kas
                <select class="form-select" name="account_cash">
                    <option value="">Pilih Akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->code }}" {{ ($settings->account_cash ?? '') === $account->code ? 'selected' : '' }}>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <span class="form-help">Akun untuk mencatat penerimaan kas.</span>
            </label>

            <label class="form-label">
                Akun Hutang Wallet
                <select class="form-select" name="account_wallet_liability">
                    <option value="">Pilih Akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->code }}" {{ ($settings->account_wallet_liability ?? '') === $account->code ? 'selected' : '' }}>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <span class="form-help">Akun liabilitas saldo wallet santri.</span>
            </label>

            <label class="form-label">
                Akun Pendapatan
                <select class="form-select" name="account_revenue">
                    <option value="">Pilih Akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->code }}" {{ ($settings->account_revenue ?? '') === $account->code ? 'selected' : '' }}>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <span class="form-help">Akun untuk mencatat pendapatan penjualan.</span>
            </label>

            <label class="form-label">
                Akun Persediaan
                <select class="form-select" name="account_inventory">
                    <option value="">Pilih Akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->code }}" {{ ($settings->account_inventory ?? '') === $account->code ? 'selected' : '' }}>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <span class="form-help">Akun aset untuk persediaan barang.</span>
            </label>

            <label class="form-label">
                Akun HPP (Harga Pokok Penjualan)
                <select class="form-select" name="account_cogs">
                    <option value="">Pilih Akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->code }}" {{ ($settings->account_cogs ?? '') === $account->code ? 'selected' : '' }}>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <span class="form-help">Akun beban untuk harga pokok penjualan.</span>
            </label>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>
@endsection
