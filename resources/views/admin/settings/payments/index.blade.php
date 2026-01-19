@extends('layouts.admin')

@section('title', 'Pengaturan Payment Gateway')

@section('content')
<div class="card">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Pengaturan Payment Gateway</h2>
        <p class="text-sm text-slate-500">Kelola integrasi pembayaran untuk top-up wallet santri.</p>
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('admin.settings.payments.midtrans.checklist') }}" class="btn btn-outline btn-sm">Midtrans Checklist</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success mb-4 flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-slate-100">
        <div class="table-scroll">
            <table class="table">
            <thead>
                <tr class="bg-slate-50">
                    <th>Prioritas</th>
                    <th>Provider</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($providers as $index => $provider)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-success">
                                    {{ $provider->priority }}
                                </span>
                                <div class="flex flex-col gap-1">
                                    @if($provider->priority > 1)
                                    <form method="POST" action="{{ route('admin.settings.payments.priority', $provider->provider) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="btn btn-ghost btn-sm px-2" title="Naikkan">▲</button>
                                    </form>
                                    @endif
                                    @if($index < $providers->count() - 1)
                                    <form method="POST" action="{{ route('admin.settings.payments.priority', $provider->provider) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="btn btn-ghost btn-sm px-2" title="Turunkan">▼</button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="font-semibold text-slate-800">{{ $provider->name }}</div>
                            <p class="text-xs text-slate-500">{{ strtoupper($provider->provider) }}</p>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.settings.payments.toggle', $provider->provider) }}" class="inline-block">
                                @csrf
                                <button type="submit" class="toggle" data-active="{{ $provider->is_active ? 'true' : 'false' }}">
                                    <span class="toggle-thumb"></span>
                                </button>
                            </form>
                            <p class="text-xs mt-1 {{ $provider->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $provider->is_active ? 'Aktif' : 'Nonaktif' }}
                            </p>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.settings.payments.edit', $provider->provider) }}" class="btn btn-primary btn-sm">
                                Konfigurasi
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-10 text-slate-400">Belum ada provider terkonfigurasi.</td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    <div class="soft-panel p-4 mt-6">
        <h4 class="text-sm font-semibold text-slate-700 mb-1">Tentang Prioritas</h4>
        <p class="text-sm text-slate-500">
            Provider dengan prioritas tertinggi (angka terkecil) akan digunakan pertama kali saat melakukan top-up wallet. 
            Jika provider pertama gagal atau tidak aktif, sistem akan mencoba provider berikutnya.
        </p>
    </div>
</div>
@endsection
