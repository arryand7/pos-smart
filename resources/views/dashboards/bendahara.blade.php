@extends('layouts.finance')

@section('title', 'Dashboard Bendahara')
@section('subtitle', 'Monitoring keuangan dan analitik penjualan SMART Sabira Mart')

@section('content')
    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Penjualan Hari Ini</p>
            <p class="stat-value text-emerald-700" id="kpi-today-sales">-</p>
            <p class="stat-meta" id="kpi-today-tx">Memuat...</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Omzet Bulan Ini</p>
            <p class="stat-value text-emerald-700" id="kpi-month-sales">-</p>
            <p class="stat-meta">Akumulasi transaksi bulan berjalan</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Dompet Aktif (7 Hari)</p>
            <p class="stat-value text-emerald-700" id="kpi-active-wallets">-</p>
            <p class="stat-meta">Santri dengan aktivitas terkini</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-emerald-700">Omzet Mingguan</h3>
                <span class="badge badge-info">Grafik</span>
            </div>
            <div class="relative h-72">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-emerald-700">Arus Kas Mingguan</h3>
                <span class="badge badge-success">Tren</span>
            </div>
            <div class="relative h-72">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.profit-loss') }}" class="btn btn-primary">Laporan Laba/Rugi</a>
        <a href="{{ route('reports.balance-sheet') }}" class="btn btn-outline">Neraca</a>
        <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline">Arus Kas</a>
        <a href="{{ route('catalog.index') }}" class="btn btn-ghost">Katalog Produk</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const apiToken = '{{ session("api_token") ?? "" }}';
        const headers = apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {};

        function formatRupiah(value) {
            return 'Rp' + new Intl.NumberFormat('id-ID').format(value);
        }

        // Load KPIs
        async function loadKpis() {
            try {
                const res = await fetch('/api/analytics/kpis', { headers });
                if (!res.ok) throw new Error('Failed to load KPIs');
                const data = await res.json();
                
                document.getElementById('kpi-today-sales').textContent = formatRupiah(data.today_sales || 0);
                document.getElementById('kpi-month-sales').textContent = formatRupiah(data.month_sales || 0);
                document.getElementById('kpi-active-wallets').textContent = data.active_wallets || 0;
                document.getElementById('kpi-today-tx').textContent = `${data.today_transactions || 0} transaksi`;
            } catch (e) {
                console.error('KPI Error:', e);
            }
        }

        // Load Sales Chart
        async function loadSalesChart() {
            try {
                const res = await fetch('/api/analytics/sales-weekly', { headers });
                if (!res.ok) throw new Error('Failed to load sales data');
                const data = await res.json();

                new Chart(document.getElementById('salesChart'), {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => formatRupiah(value)
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Sales Chart Error:', e);
            }
        }

        // Load Cash Flow Chart
        async function loadCashFlowChart() {
            try {
                const res = await fetch('/api/analytics/cash-flow-weekly', { headers });
                if (!res.ok) throw new Error('Failed to load cash flow data');
                const data = await res.json();

                new Chart(document.getElementById('cashFlowChart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map(ds => ({
                            ...ds,
                            tension: 0.3,
                            fill: true
                        }))
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => formatRupiah(value)
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Cash Flow Chart Error:', e);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadKpis();
            loadSalesChart();
            loadCashFlowChart();
        });
    </script>
@endsection
