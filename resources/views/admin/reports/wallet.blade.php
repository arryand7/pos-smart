@extends('layouts.admin')

@section('title', 'Laporan Wallet')
@section('subtitle', 'Monitoring transaksi deposit dan penggunaan saldo santri.')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Summary Cards -->
    <div class="admin-card bg-white border-l-4 border-l-emerald-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500">Total Deposit (Masuk)</p>
                <h3 class="text-2xl font-bold text-emerald-600 mt-1">Rp{{ number_format($summary['deposit'], 0, ',', '.') }}</h3>
            </div>
            <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            </div>
        </div>
    </div>

    <div class="admin-card bg-white border-l-4 border-l-rose-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500">Total Penggunaan (Keluar)</p>
                <h3 class="text-2xl font-bold text-rose-600 mt-1">Rp{{ number_format($summary['usage'], 0, ',', '.') }}</h3>
            </div>
            <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
            </div>
        </div>
    </div>

    <div class="admin-card bg-white border-l-4 border-l-blue-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500">Net Movement</p>
                <h3 class="text-2xl font-bold text-blue-600 mt-1">Rp{{ number_format($summary['net'], 0, ',', '.') }}</h3>
            </div>
            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="w-full md:w-64">
            <label class="block text-sm font-medium text-slate-700 mb-1">Periode Tanggal</label>
            <input type="text" id="daterange" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500" value="{{ $startDate }} to {{ $endDate }}">
            <input type="hidden" name="start_date" value="{{ $startDate }}">
            <input type="hidden" name="end_date" value="{{ $endDate }}">
        </div>
        <button type="submit" class="btn btn-secondary">
            Filter Data
        </button>
    </form>
</div>

<div class="admin-card">
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Santri</th>
                <th>Tipe</th>
                <th>Channel</th>
                <th>Nominal</th>
                <th>Saldo Akhir</th>
                <th>Admin/Ket</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td class="text-sm">{{ $trx->occurred_at->format('d/m/Y H:i') }}</td>
                <td>
                    <div class="font-medium text-sm">{{ $trx->santri->nama }}</div>
                    <div class="text-xs text-slate-400">{{ $trx->santri->nis }}</div>
                </td>
                <td>
                    @if($trx->type == 'credit')
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">MASUK</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-700">KELUAR</span>
                    @endif
                </td>
                <td class="text-sm capitalize">{{ str_replace('_', ' ', $trx->channel) }}</td>
                <td class="font-bold {{ $trx->type == 'credit' ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $trx->type == 'credit' ? '+' : '-' }}Rp{{ number_format($trx->amount, 0, ',', '.') }}
                </td>
                <td class="text-sm text-slate-600">Rp{{ number_format($trx->balance_after, 0, ',', '.') }}</td>
                <td class="text-xs text-slate-500 max-w-[200px] truncate">
                    @if($trx->performer)
                        <span class="font-semibold">{{ $trx->performer->name }}:</span> 
                    @endif
                    {{ $trx->description }}
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#daterange", {
            mode: "range",
            dateFormat: "Y-m-d",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    const start = instance.formatDate(selectedDates[0], "Y-m-d");
                    const end = instance.formatDate(selectedDates[1], "Y-m-d");
                    document.querySelector("input[name='start_date']").value = start;
                    document.querySelector("input[name='end_date']").value = end;
                }
            }
        });
    });
</script>
@endsection
