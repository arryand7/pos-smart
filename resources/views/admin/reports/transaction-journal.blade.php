@extends('layouts.admin')

@section('title', 'Jurnal Transaksi')
@section('subtitle', 'Log detail semua transaksi POS yang tercatat.')

@section('content')
<div class="admin-card mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="w-full md:w-64">
            <label class="block text-sm font-medium text-slate-700 mb-1">Periode Tanggal</label>
            <input type="text" name="date_range" class="datepicker w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-emerald-500" placeholder="Pilih tanggal..." value="{{ $startDate }} to {{ $endDate }}" data-mode="range">
            <!-- Hidden inputs for backend processing if needed, though range picker usually sends string -->
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
                <th>REF ID</th>
                <th>Waktu</th>
                <th>Santri</th>
                <th>Kasir</th>
                <th>Sales Type</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td class="font-mono text-xs">{{ $trx->reference }}</td>
                <td>{{ $trx->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    @if($trx->santri)
                        <div class="font-medium">{{ $trx->santri->nama }}</div>
                        <div class="text-xs text-slate-500">{{ $trx->santri->nis }}</div>
                    @else
                        <span class="text-slate-400">-</span>
                    @endif
                </td>
                <td>{{ $trx->kasir->name ?? '-' }}</td>
                <td>
                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $trx->type == 'purchase' ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-600' }}">
                        {{ strtoupper($trx->type) }}
                    </span>
                    <div class="text-xs text-slate-500 mt-0.5">{{ ucfirst($trx->channel) }}</div>
                </td>
                <td class="font-medium">Rp{{ number_format($trx->total_amount, 0, ',', '.') }}</td>
                <td>
                    @if($trx->status == 'completed')
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600">SUKSES</span>
                    @elseif($trx->status == 'pending')
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600">PENDING</span>
                    @elseif($trx->status == 'cancelled')
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-50 text-red-600">BATAL</span>
                    @else
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">{{ strtoupper($trx->status) }}</span>
                    @endif
                </td>
                <td>
                    <button class="text-slate-500 hover:text-emerald-600 p-1" title="Lihat Detail">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const picker = flatpickr("input[name='date_range']", {
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
