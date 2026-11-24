import Alpine from 'alpinejs';
import formBarangMasuk from './components/form-barang-masuk';
import kasirPos from './components/kasir-pos';

// Alpine.js untuk interaksi ringan: dropdown, modal konfirmasi, sidebar mobile, baris item dinamis, kasir (POS).
Alpine.data('formBarangMasuk', formBarangMasuk);
Alpine.data('kasirPos', kasirPos);

window.Alpine = Alpine;
Alpine.start();
