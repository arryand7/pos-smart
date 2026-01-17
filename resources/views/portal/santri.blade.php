@extends('layouts.portal')

@section('title', 'Portal Santri SMART')

@section('content')
@if($isSuperAdmin ?? false)
    <div class="portal-card p-4 mb-4">
        <form method="GET" action="{{ route('portal.santri') }}" class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-slate-500 flex-1 min-w-[200px]">
                Pilih Santri
                <select name="santri_id" class="form-select mt-2">
                    @foreach($santriOptions as $option)
                        <option value="{{ $option->id }}" @selected($activeSantriId == $option->id)>
                            {{ $option->name }} @if($option->nis) ({{ $option->nis }}) @endif
                        </option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-primary btn-sm">Buka Portal</button>
        </form>
    </div>
@endif

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Portal Santri</p>
        <h1 class="text-xl font-bold text-slate-800">Halo, {{ $santri->nickname ?? $santri->name }}</h1>
    </div>
    <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500">
        {{ substr($santri->name, 0, 1) }}
    </div>
</div>

<!-- Main Balance Card -->
<div class="portal-card portal-card-brand p-5 mb-5 shadow-lg shadow-emerald-900/20 border-none relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-10 -mt-10 blur-2xl"></div>
    <div class="relative z-10">
        <p class="text-slate-200 text-xs font-semibold mb-1">Saldo Dompet</p>
        <h2 class="text-3xl font-bold mb-4 text-white">Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}</h2>
        
        <div class="flex gap-4 border-t border-emerald-200/40 pt-4">
            <div>
                <p class="text-[10px] text-slate-300 uppercase tracking-wider mb-1">Limit Harian</p>
                <p class="font-bold text-sm text-white">Rp{{ number_format($santri->daily_limit ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                 <p class="text-[10px] text-slate-300 uppercase tracking-wider mb-1">Limit Bulanan</p>
                <p class="font-bold text-sm text-white">Rp{{ number_format($santri->monthly_limit ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Status Badge -->
<div class="mb-6">
    <div class="flex items-center gap-2 p-3 rounded-xl {{ $santri->is_wallet_locked ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' }}">
        <span class="text-xl">{{ $santri->is_wallet_locked ? '🔒' : '✅' }}</span>
        <div>
            <p class="text-xs font-bold uppercase tracking-wider mb-0.5">Status Dompet</p>
            <p class="font-bold text-sm">{{ $santri->is_wallet_locked ? 'TERKUNCI SEMENTARA' : 'AKTIF & SIAP DIGUNAKAN' }}</p>
        </div>
    </div>
</div>

<!-- Tabs for History -->
<div x-data="{ tab: 'wallet' }">
    <div class="flex p-1 bg-slate-100 rounded-xl mb-4">
        <button class="flex-1 py-2 text-xs font-bold rounded-lg transition-all" 
            :class="tab === 'wallet' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            @click="tab = 'wallet'">
            Mutasi Dompet
        </button>
        <button class="flex-1 py-2 text-xs font-bold rounded-lg transition-all" 
            :class="tab === 'pos' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            @click="tab = 'pos'">
            Riwayat Belanja
        </button>
    </div>

    <!-- Wallet History -->
    <div x-show="tab === 'wallet'" class="space-y-3 pb-20">
        @forelse($walletHistory as $trx)
        <div class="portal-card p-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full {{ $trx->type == 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center font-bold text-xs">
                    {{ $trx->type == 'credit' ? 'IN' : 'OUT' }}
                </div>
                <div>
                     <p class="font-bold text-slate-800 text-sm">{{ ucfirst($trx->type == 'credit' ? 'Top Up / Masuk' : 'Pembayaran') }}</p>
                    <p class="text-xs text-slate-500">{{ $trx->occurred_at?->format('d M Y H:i') }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold text-sm {{ $trx->type == 'credit' ? 'text-emerald-600' : 'text-slate-800' }}">
                    {{ $trx->type == 'credit' ? '+' : '-' }}Rp{{ number_format($trx->amount, 0, ',', '.') }}
                </p>
                <p class="text-[10px] text-slate-400">Sisa: {{ number_format($trx->balance_after, 0, ',', '.') }}</p>
            </div>
        </div>
        @empty
        <div class="text-center py-8 text-slate-400 text-sm">Belum ada mutasi dompet.</div>
        @endforelse
    </div>

    <!-- POS History -->
    <div x-show="tab === 'pos'" class="space-y-3 pb-20" style="display: none;">
        @forelse($posHistory as $trx)
        <div class="portal-card p-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                    POS
                </div>
                <div>
                    <p class="font-bold text-slate-800 text-sm">{{ $trx->reference }}</p>
                    <p class="text-xs text-slate-500">{{ $trx->processed_at?->format('d M Y H:i') }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold text-sm text-slate-800">
                    Rp{{ number_format($trx->total_amount, 0, ',', '.') }}
                </p>
                <p class="text-[10px] text-slate-400">{{ $trx->items_count ?? 0 }} item</p>
            </div>
        </div>
        @empty
        <div class="text-center py-8 text-slate-400 text-sm">Belum ada transaksi belanja.</div>
        @endforelse
    </div>
</div>

<!-- Alpine.js is assumed to be loaded via app.js or CDN -->
<!-- Since app.js has Livewire/Alpine tailored often in Laravel, or we can just stick to vanilla JS toggles if Alpine isn't confirmed. 
     However, standard Laravel Breeze/Jetstream includes Alpine. I'll rely on vanilla JS fallback or add Alpine via CDN if needed, 
     but for now I will use simple script for tabs to be safe mainly because I cannot be 100% sure Alpine is globally available in this specific setup without checking app.js -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const walletBtn = document.querySelector('button[x-data]'); // Wait, I used x-data syntax.
        // Let's just add a simple vanilla script to handle tabs if Alpine isn't present, 
        // to ensure it works regardless.
        
        const tabs = document.querySelectorAll('[x-data] button');
        const contents = document.querySelectorAll('[x-show]');
        
        if(typeof Alpine === 'undefined') {
            tabs.forEach((btn, idx) => {
                btn.removeAttribute('x-click'); // remove alpine attr to avoid confusion if it loads later
                btn.addEventListener('click', () => {
                   const target = btn.textContent.trim().includes('Mutasi') ? 'wallet' : 'pos';
                   
                   // Update Buttons
                   tabs.forEach(t => {
                       t.className = t.textContent.trim().includes(target === 'wallet' ? 'Mutasi' : 'Belanja') 
                        ? 'flex-1 py-2 text-xs font-bold rounded-lg transition-all bg-white text-slate-800 shadow-sm'
                        : 'flex-1 py-2 text-xs font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700';
                   });

                   // Update Content
                   contents.forEach(c => {
                       if(c.getAttribute('x-show').includes(target)) {
                           c.style.display = 'block';
                       } else {
                           c.style.display = 'none';
                       }
                   });
                });
            });
        }
    });
</script>
@endsection
