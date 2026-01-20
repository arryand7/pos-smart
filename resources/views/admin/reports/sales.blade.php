@extends('layouts.admin')

@section('title', 'Laporan Penjualan')
@section('subtitle', 'Analisis performa penjualan produk dan omset harian.')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Filter -->
    <div class="lg:col-span-3 admin-card flex items-center justify-between">
        <h3 class="font-bold text-slate-700">Filter Periode</h3>
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="start_date" value="{{ $startDate }}">
            <input type="hidden" name="end_date" value="{{ $endDate }}">
            <input type="text" id="daterange" class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-emerald-500 w-64" value="{{ $startDate }} to {{ $endDate }}">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
        </form>
    </div>

    <!-- Chart -->
    <div class="lg:col-span-2 admin-card">
        <h4 class="font-bold text-slate-700 mb-4">Grafik Penjualan Harian</h4>
        <div style="height:320px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="space-y-6">
        <div class="admin-card bg-emerald-50 border-emerald-100">
            <div class="text-sm font-medium text-emerald-600 mb-1">Total Omset</div>
            <div class="text-3xl font-bold text-emerald-700">
                Rp{{ number_format($dailySales->sum('total'), 0, ',', '.') }}
            </div>
            <div class="text-xs text-emerald-600 mt-2">Periode {{ Carbon\Carbon::parse($startDate)->format('d M') }} - {{ Carbon\Carbon::parse($endDate)->format('d M Y') }}</div>
        </div>

        <div class="admin-card">
            <h4 class="font-bold text-slate-700 mb-3">Produk Terlaris</h4>
            <div class="space-y-3">
                @foreach($topProducts->take(3) as $index => $prod)
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500 text-xs">
                            #{{ $index + 1 }}
                        </div>
                        <div>
                            <div class="font-medium text-sm text-slate-700 line-clamp-1">{{ $prod->product_name }}</div>
                            <div class="text-xs text-slate-500">{{ $prod->total_qty }} terjual</div>
                        </div>
                    </div>
                    <div class="font-bold text-sm text-slate-800">
                        Rp{{ number_format($prod->total_revenue, 0, ',', '.') }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <h4 class="font-bold text-slate-700 mb-4">Detail Penjualan per Produk</h4>
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Nama Produk</th>
                <th class="text-right">Qty Terjual</th>
                <th class="text-right">Total Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $prod)
            <tr>
                <td class="font-medium">{{ $prod->product_name }}</td>
                <td class="text-right">{{ number_format($prod->total_qty, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-emerald-600">Rp{{ number_format($prod->total_revenue, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datepicker
        flatpickr("#daterange", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: ["{{ $startDate }}", "{{ $endDate }}"],
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    const start = instance.formatDate(selectedDates[0], "Y-m-d");
                    const end = instance.formatDate(selectedDates[1], "Y-m-d");
                    document.querySelector("input[name='start_date']").value = start;
                    document.querySelector("input[name='end_date']").value = end;
                }
            }
        });

        // Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        if (window.__salesChart) {
            window.__salesChart.destroy();
        }
        window.__salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dailySales->pluck('date')) !!},
                datasets: [{
                    label: 'Omset Harian (Rp)',
                    data: {!! json_encode($dailySales->pluck('total')) !!},
                    borderColor: '#007A5C',
                    backgroundColor: 'rgba(0, 122, 92, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                responsiveAnimationDuration: 0,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    });
</script>
@endsection
