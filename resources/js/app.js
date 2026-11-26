import Alpine from 'alpinejs';
import formBarangMasuk from './components/form-barang-masuk';
import grafikPenjualan from './components/grafik-penjualan';
import kasirPos from './components/kasir-pos';

// Alpine.js untuk interaksi ringan: dropdown, modal konfirmasi, sidebar mobile, baris item dinamis, kasir (POS),
// grafik penjualan dashboard (Chart.js dari npm, bukan CDN).
Alpine.data('formBarangMasuk', formBarangMasuk);
Alpine.data('grafikPenjualan', grafikPenjualan);
Alpine.data('kasirPos', kasirPos);

window.Alpine = Alpine;
Alpine.start();
