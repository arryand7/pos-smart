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
        <div class="w-full md:w-60">
            <label class="block text-sm font-medium text-slate-700 mb-1">Cari</label>
            <input type="text" name="search" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-emerald-500" placeholder="Ref, santri, kasir..." value="{{ request('search') }}">
        </div>
        <div class="w-full md:w-40">
            <label class="block text-sm font-medium text-slate-700 mb-1">Per halaman</label>
            <select name="per_page" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-emerald-500">
                @foreach([10,15,25,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">
            Filter Data
        </button>
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="direction" value="{{ request('direction') }}">
        <div class="flex items-center gap-2 ml-auto">
            @php $exportQuery = request()->except(['export', 'page']); @endphp
            <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'excel'])) }}">Excel</a>
            <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'csv'])) }}">CSV</a>
            <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'pdf'])) }}">PDF</a>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <x-sortable-th field="reference" label="REF ID" />
                <x-sortable-th field="created_at" label="Waktu" />
                <x-sortable-th field="santri" label="Santri" />
                <x-sortable-th field="kasir" label="Kasir" />
                <x-sortable-th field="type" label="Sales Type" />
                <x-sortable-th field="total_amount" label="Total" />
                <x-sortable-th field="status" label="Status" />
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
                        <div class="font-medium">{{ $trx->santri->name }}</div>
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
                        @if(data_get($trx->metadata, 'cancel_reason'))
                            <div class="text-xs text-red-600 mt-1">Alasan: {{ data_get($trx->metadata, 'cancel_reason') }}</div>
                        @endif
                    @else
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">{{ strtoupper($trx->status) }}</span>
                    @endif
                </td>
                <td class="space-x-2">
                    <button type="button" class="text-slate-500 hover:text-emerald-600 p-1 inline-flex transaction-detail-btn"
                        data-url="{{ route('admin.reports.transactions.show', $trx) }}?embed=1"
                        data-receipt-58="{{ route('admin.reports.transactions.receipt', $trx) }}?size=58"
                        data-receipt-80="{{ route('admin.reports.transactions.receipt', $trx) }}?size=80"
                        data-receipt-pdf-58="{{ route('admin.reports.transactions.receipt.pdf', $trx) }}?size=58"
                        data-receipt-pdf-80="{{ route('admin.reports.transactions.receipt.pdf', $trx) }}?size=80"
                        title="Lihat Detail">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                    @if(auth()->user()?->hasRole(\App\Enums\UserRole::SUPER_ADMIN) && $trx->status !== 'cancelled')
                        <form method="POST" action="{{ route('admin.reports.transactions.cancel', $trx) }}" class="inline-block cancel-transaction-form">
                            @csrf
                            <input type="hidden" name="reason" value="">
                            <button type="button" class="text-red-600 hover:text-red-700 text-xs font-semibold border border-red-200 px-2 py-1 rounded">
                                Batalkan
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $transactions->links() }}
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

        document.querySelectorAll('.cancel-transaction-form').forEach((form) => {
            const button = form.querySelector('button');
            if (! button) {
                return;
            }
            button.addEventListener('click', () => {
                const reason = window.prompt('Alasan pembatalan transaksi (opsional):');
                if (reason === null) {
                    return;
                }
                form.querySelector('input[name="reason"]').value = reason.trim();
                form.submit();
            });
        });

        const modal = document.getElementById('transaction-modal');
        const frame = document.getElementById('transaction-frame');
        const closeBtn = document.getElementById('transaction-modal-close');
        const printBtn = document.getElementById('transaction-print-btn');
        const thermal58Btn = document.getElementById('transaction-thermal-58');
        const thermal80Btn = document.getElementById('transaction-thermal-80');
        const pdf58Btn = document.getElementById('transaction-pdf-58');
        const pdf80Btn = document.getElementById('transaction-pdf-80');
        let receipt58Url = '';
        let receipt80Url = '';
        let receiptPdf58Url = '';
        let receiptPdf80Url = '';

        function closeModal() {
            modal.hidden = true;
            frame.src = 'about:blank';
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.transaction-detail-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const url = button.dataset.url;
                if (! url) {
                    return;
                }
                frame.src = url;
                receipt58Url = button.dataset.receipt58 || '';
                receipt80Url = button.dataset.receipt80 || '';
                receiptPdf58Url = button.dataset.receiptPdf58 || '';
                receiptPdf80Url = button.dataset.receiptPdf80 || '';
                if (thermal58Btn) {
                    thermal58Btn.href = receipt58Url || '#';
                }
                if (thermal80Btn) {
                    thermal80Btn.href = receipt80Url || '#';
                }
                if (pdf58Btn) {
                    pdf58Btn.href = receiptPdf58Url || '#';
                }
                if (pdf80Btn) {
                    pdf80Btn.href = receiptPdf80Url || '#';
                }
                modal.hidden = false;
                document.body.classList.add('modal-open');
            });
        });

        closeBtn?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
        printBtn?.addEventListener('click', () => {
            if (frame.contentWindow) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            }
        });

        [thermal58Btn, thermal80Btn, pdf58Btn, pdf80Btn].forEach((btn) => {
            btn?.addEventListener('click', (event) => {
                if (! btn.getAttribute('href') || btn.getAttribute('href') === '#') {
                    event.preventDefault();
                }
            });
        });
    });
</script>

<div class="transaction-modal" id="transaction-modal" hidden>
    <div class="transaction-modal-card">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-slate-800">Detail Transaksi</h3>
            <button type="button" class="btn btn-outline btn-sm" id="transaction-modal-close">✕</button>
        </div>
        <iframe id="transaction-frame" class="transaction-frame" title="Detail Transaksi"></iframe>
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            <a class="btn btn-outline" id="transaction-thermal-58" href="#" target="_blank" rel="noopener">Thermal 58mm</a>
            <a class="btn btn-outline" id="transaction-thermal-80" href="#" target="_blank" rel="noopener">Thermal 80mm</a>
            <a class="btn btn-outline" id="transaction-pdf-58" href="#" target="_blank" rel="noopener">PDF 58mm</a>
            <a class="btn btn-outline" id="transaction-pdf-80" href="#" target="_blank" rel="noopener">PDF 80mm</a>
            <button type="button" class="btn btn-secondary" id="transaction-print-btn">Cetak</button>
        </div>
    </div>
</div>
@endsection
