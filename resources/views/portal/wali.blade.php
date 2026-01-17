@extends('layouts.portal')

@section('title', 'Portal Wali SMART')

@section('content')
@php
    $portalQuery = ($isSuperAdmin ?? false) ? ['wali_id' => $activeWaliId] : [];
@endphp

@if($isSuperAdmin ?? false)
    <div class="portal-card p-4 mb-4">
        <form method="GET" action="{{ route('portal.wali') }}" class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-slate-500 flex-1 min-w-[200px]">
                Pilih Wali
                <select name="wali_id" class="form-select mt-2">
                    @foreach($waliOptions as $option)
                        <option value="{{ $option->id }}" @selected($activeWaliId == $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-primary btn-sm">Buka Portal</button>
        </form>
    </div>
@endif

<!-- Header Stats -->
<div class="grid grid-cols-2 gap-3 mb-6">
    <div class="portal-card portal-card-brand p-4">
        <p class="text-emerald-50 text-xs font-semibold mb-1">Total Saldo</p>
        <h2 class="text-2xl font-bold text-white">Rp{{ number_format($stats['total_balance'], 0, ',', '.') }}</h2>
    </div>
    <div class="portal-card p-4">
        <p class="text-slate-500 text-xs font-medium mb-1">Total Anak</p>
        <h2 class="text-2xl font-bold text-slate-800">{{ $stats['total_children'] }} Santri</h2>
    </div>
</div>

<h3 class="font-bold text-slate-800 mb-4 px-1">Daftar Santri</h3>

<div class="space-y-6 pb-20">
    @forelse($santris as $santri)
    <div class="portal-card">
        <!-- Santri Header -->
        <div class="p-4 border-b border-slate-100 flex items-start justify-between bg-slate-50/50">
            <div>
                <h2 class="font-bold text-slate-800 text-lg">{{ $santri->name }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ $santri->class }} • {{ $santri->dormitory }}</p>
            </div>
            <span class="px-2 py-1 rounded-full text-[10px] font-bold {{ $santri->is_wallet_locked ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600' }}">
                {{ $santri->is_wallet_locked ? 'TERKUNCI' : 'AKTIF' }}
            </span>
        </div>

        <!-- Wallet Info -->
        <div class="p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs text-slate-500 mb-1">Sisa Saldo</p>
                    <p class="text-xl font-bold text-slate-800">Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</p>
                </div>
                @php
                    $todaySpent = $santri->walletTransactions
                        ->where('type', 'debit')
                        ->filter(fn($t) => $t->occurred_at && $t->occurred_at->isToday())
                        ->sum('amount');
                    $remainingDaily = max(0, ($santri->daily_limit ?? 0) - $todaySpent);
                @endphp
                <div class="text-right">
                    <p class="text-xs text-slate-500 mb-1">Sisa Limit Hari Ini</p>
                    <p class="text-lg font-bold {{ $remainingDaily <= 10000 ? 'text-red-600' : 'text-emerald-600' }}">
                        Rp{{ number_format($remainingDaily, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            <!-- Actions Tabs/Accordion -->
            <div class="space-y-3">
                <!-- Top Up -->
                <details class="group bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                    <summary class="flex items-center justify-between p-3 cursor-pointer font-medium text-sm text-slate-700 hover:bg-slate-100 transition-colors">
                        <span class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs">💰</span>
                            Top Up Saldo
                        </span>
                        <svg class="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="p-3 border-t border-slate-100 bg-white">
                        <form method="POST" action="{{ route('portal.wali.topup', array_merge(['santri' => $santri], $portalQuery)) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Nominal (Rp)</label>
                                <input type="number" name="amount" min="1000" step="1000" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-emerald-500" placeholder="50000" required>
                            </div>
                            @if(($topupProviders->count() ?? 0) > 1)
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Metode Pembayaran</label>
                                <select name="provider" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-emerald-500">
                                    @foreach($topupProviders as $provider)
                                        <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @else
                                <input type="hidden" name="provider" value="{{ $topupProviders->first()['key'] ?? config('smart.payments.default_provider', 'ipaymu') }}">
                            @endif
                            <button type="submit" class="w-full bg-[#007A5C] text-white py-2 rounded-lg text-sm font-bold hover:bg-[#00624a] transition-colors">Buat Link Pembayaran</button>
                        </form>
                    </div>
                </details>

                <!-- Limits Settings -->
                <details class="group bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                     <summary class="flex items-center justify-between p-3 cursor-pointer font-medium text-sm text-slate-700 hover:bg-slate-100 transition-colors">
                        <span class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs">🛡️</span>
                            Batasan & Keamanan
                        </span>
                        <svg class="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="p-3 border-t border-slate-100 bg-white">
                        <form method="POST" action="{{ route('portal.wali.limits', array_merge(['santri' => $santri], $portalQuery)) }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Limit Harian (Rp)</label>
                                    <input type="number" name="daily_limit" value="{{ old('daily_limit', $santri->daily_limit) }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Limit Bulanan (Rp)</label>
                                    <input type="number" name="monthly_limit" value="{{ old('monthly_limit', $santri->monthly_limit) }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                                </div>
                            </div>
                            
                            <label class="flex items-center gap-2 border p-3 rounded-lg border-slate-200">
                                <input type="checkbox" name="is_wallet_locked" value="1" @checked(old('is_wallet_locked', $santri->is_wallet_locked)) class="text-emerald-600 rounded focus:ring-emerald-500">
                                <span class="text-sm text-slate-700 font-medium">Kunci Dompet (Blokir Sementara)</span>
                            </label>

                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Catatan Khusus (Alergi, dll)</label>
                                <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">{{ old('notes', $santri->notes) }}</textarea>
                            </div>

                            <button type="submit" class="w-full bg-slate-800 text-white py-2 rounded-lg text-sm font-bold hover:bg-slate-700 transition-colors">Simpan Pengaturan</button>
                        </form>
                    </div>
                </details>

                 <!-- Categories -->
                 <details class="group bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                    <summary class="flex items-center justify-between p-3 cursor-pointer font-medium text-sm text-slate-700 hover:bg-slate-100 transition-colors">
                        <span class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 text-xs">🚫</span>
                            Kategori Produk
                        </span>
                        <svg class="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="p-3 border-t border-slate-100 bg-white">
                        <form method="POST" action="{{ route('portal.wali.categories', array_merge(['santri' => $santri], $portalQuery)) }}" class="space-y-4">
                            @csrf
                            <div>
                                <p class="text-xs font-bold text-slate-700 mb-2">🚫 Blokir Kategori</p>
                                <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-slate-50 rounded-lg border border-slate-100">
                                    @foreach($allCategories as $cat)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="blocked_category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $santri->blocked_category_ids ?? [])) class="rounded text-red-600 focus:ring-red-500">
                                        <span class="text-xs text-slate-600">{{ $cat->name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-700 mb-2">✅ Whitelist Kategori (Izinkan)</p>
                                <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-slate-50 rounded-lg border border-slate-100">
                                    @foreach($allCategories as $cat)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="whitelisted_category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $santri->whitelisted_category_ids ?? [])) class="rounded text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-xs text-slate-600">{{ $cat->name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-slate-800 text-white py-2 rounded-lg text-sm font-bold hover:bg-slate-700 transition-colors">Simpan Kategori</button>
                        </form>
                    </div>
                 </details>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="border-t border-slate-100 p-4">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">5 Transaksi Terakhir</h4>
             @if($santri->walletTransactions->isEmpty())
                <p class="text-xs text-slate-400 italic">Belum ada transaksi.</p>
            @else
                <div class="space-y-3">
                    @foreach($santri->walletTransactions as $trx)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full {{ $trx->type == 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center text-xs">
                                {{ $trx->type == 'credit' ? 'IN' : 'OUT' }}
                            </div>
                            <div>
                                <p class="font-medium text-slate-700 line-clamp-1">{{ $trx->description }}</p>
                                <p class="text-[10px] text-slate-400">{{ $trx->occurred_at?->format('d M H:i') }}</p>
                            </div>
                        </div>
                        <span class="font-bold {{ $trx->type == 'credit' ? 'text-emerald-600' : 'text-slate-700' }}">
                            {{ $trx->type == 'credit' ? '+' : '-' }}Rp{{ number_format($trx->amount, 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center py-10 px-4">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">🤷‍♂️</div>
        <h3 class="font-bold text-slate-700">Belum Ada Santri</h3>
        <p class="text-sm text-slate-500 mt-1">Akun Anda belum terhubung dengan data santri manapun. Silakan hubungi admin sekolah.</p>
    </div>
    @endforelse
</div>
@endsection
