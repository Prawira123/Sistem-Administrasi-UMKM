import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
import { formatRupiah } from './uang';

// Hanya modul Chart.js yang dipakai (tree-shaking) agar bundle tetap kecil.
Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Grafik batang penjualan harian di dashboard Administrator.
 * Pemakaian: <div x-data="grafikPenjualan(@js($grafik))"> ... <canvas x-ref="kanvas"></canvas></div>
 * $grafik = { "7": {label: [...], total: [...]}, "30": {...} } dari LaporanService::grafikPenjualan().
 */
export default (data) => {
    let chart = null; // di luar state Alpine agar tidak dibungkus Proxy

    return {
        rentang: '7',

        init() {
            chart = new Chart(this.$refs.kanvas, {
                type: 'bar',
                data: {
                    labels: data[this.rentang].label,
                    datasets: [{
                        label: 'Penjualan',
                        data: data[this.rentang].total,
                        backgroundColor: '#0d9488', // senada dengan primary-600 (teal)
                        borderRadius: 4,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: { callbacks: { label: (ctx) => formatRupiah(Math.round(ctx.parsed.y * 100)) } },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: (v) => (v >= 1000 ? `${v / 1000}rb` : v) },
                        },
                        x: { grid: { display: false } },
                    },
                },
            });
        },

        pilih(rentang) {
            this.rentang = rentang;
            chart.data.labels = data[rentang].label;
            chart.data.datasets[0].data = data[rentang].total;
            chart.update();
        },

        destroy() {
            chart?.destroy();
        },
    };
};
