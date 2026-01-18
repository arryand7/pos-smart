@php
    $title = $title ?? 'Grafik Pemasukan';
    $subtitle = $subtitle ?? 'Total pembelian semua barang (pemasukan).';
@endphp

<div class="card" id="revenue-chart-card">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-emerald-700">{{ $title }}</h3>
            <p class="text-sm text-slate-500">{{ $subtitle }}</p>
        </div>
        <details class="range-menu" id="revenue-frame-group">
            <summary class="range-trigger">
                <span id="revenue-range-label">1 Hari</span>
                <svg class="w-4 h-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/>
                </svg>
            </summary>
            <div class="range-panel">
                <div class="range-group-title">Jam</div>
                <button type="button" class="range-item" data-revenue-frame="1h">1 Jam</button>
                <button type="button" class="range-item" data-revenue-frame="4h">4 Jam</button>
                <div class="range-group-title">Hari</div>
                <button type="button" class="range-item" data-revenue-frame="1d">1 Hari</button>
                <div class="range-group-title">Bulan</div>
                <button type="button" class="range-item" data-revenue-frame="1m">1 Bulan</button>
                <button type="button" class="range-item" data-revenue-frame="3m">3 Bulan</button>
                <button type="button" class="range-item" data-revenue-frame="6m">6 Bulan</button>
                <div class="range-group-title">Tahun</div>
                <button type="button" class="range-item" data-revenue-frame="1y">1 Tahun</button>
                <button type="button" class="range-item" data-revenue-frame="3y">3 Tahun</button>
                <button type="button" class="range-item" data-revenue-frame="5y">5 Tahun</button>
            </div>
        </details>
    </div>
    <div class="relative h-72">
        <canvas id="revenueChart"></canvas>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chartEl = document.getElementById('revenueChart');
        if (!chartEl || !window.Chart) {
            return;
        }

        const buttons = document.querySelectorAll('[data-revenue-frame]');
        const label = document.getElementById('revenue-range-label');
        const menu = document.getElementById('revenue-frame-group');
        const defaultFrame = '1d';
        let chart = null;

        const formatRupiah = (value) => 'Rp' + new Intl.NumberFormat('id-ID').format(value || 0);

        const setActive = (frame) => {
            buttons.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.revenueFrame === frame);
            });
        };

        const renderChart = (payload) => {
            const ctx = chartEl.getContext('2d');
            const labels = payload.labels || [];
            const revenue = payload.data || [];
            const maShort = payload.ma_short || [];
            const maLong = payload.ma_long || [];
            const shortLabel = payload.meta?.ma_short ? `MA ${payload.meta.ma_short}` : 'MA Pendek';
            const longLabel = payload.meta?.ma_long ? `MA ${payload.meta.ma_long}` : 'MA Panjang';

            const datasets = [
                {
                    label: 'Pemasukan',
                    data: revenue,
                    borderColor: '#007A5C',
                    backgroundColor: 'rgba(0, 122, 92, 0.15)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    borderWidth: 2,
                },
                {
                    label: shortLabel,
                    data: maShort,
                    borderColor: '#22c55e',
                    backgroundColor: 'transparent',
                    tension: 0.35,
                    fill: false,
                    pointRadius: 0,
                    borderWidth: 2,
                    borderDash: [6, 4],
                },
                {
                    label: longLabel,
                    data: maLong,
                    borderColor: '#94a3b8',
                    backgroundColor: 'transparent',
                    tension: 0.35,
                    fill: false,
                    pointRadius: 0,
                    borderWidth: 2,
                    borderDash: [4, 4],
                }
            ];

            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets = datasets;
                chart.update();
                return;
            }

            chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.dataset.label}: ${formatRupiah(ctx.parsed.y)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: (value) => formatRupiah(value)
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.15)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        };

        const loadFrame = async (frame) => {
            try {
                setActive(frame);
                if (label) {
                    const active = document.querySelector(`[data-revenue-frame=\"${frame}\"]`);
                    label.textContent = active ? active.textContent : '1 Hari';
                }
                if (menu) {
                    menu.removeAttribute('open');
                }
                const res = await fetch(`/analytics/revenue-series?frame=${frame}`);
                if (!res.ok) {
                    throw new Error('Gagal memuat data grafik');
                }
                const payload = await res.json();
                renderChart(payload);
            } catch (error) {
                console.error(error);
            }
        };

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => loadFrame(btn.dataset.revenueFrame));
        });

        loadFrame(defaultFrame);
    });
</script>
