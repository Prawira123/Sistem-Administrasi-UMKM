/**
 * Alpine.js: baris item dinamis di form barang masuk.
 * - tambah/hapus baris, cari barang (kode/barcode atau nama), jumlah, harga beli, kedaluwarsa opsional
 * - subtotal dan total dihitung di browser hanya untuk tampilan; server tetap menghitung ulang.
 * Hitungan memakai sen (bilangan bulat) agar tidak ada galat pembulatan float.
 */
import { formatRupiah, keSen } from './uang';

let urutan = 0;

export default ({ barang = [], items = [], errors = {} } = {}) => ({
    barang,
    errors,
    items: [],

    init() {
        const awal = items.length ? items : [{}];
        this.items = awal.map((item) => this.itemBaru(item));
    },

    itemBaru(item = {}) {
        return {
            key: ++urutan,
            id_barang: item.id_barang ? String(item.id_barang) : '',
            jumlah: item.jumlah ?? 1,
            harga_beli: item.harga_beli ?? '',
            tanggal_kadaluarsa: item.tanggal_kadaluarsa ?? '',
            cari: '',
            buka: false,
        };
    },

    tambah() {
        this.items.push(this.itemBaru());
    },

    hapus(index) {
        this.items.splice(index, 1);
        if (this.items.length === 0) this.tambah();
    },

    dataBarang(id) {
        return this.barang.find((b) => String(b.id) === String(id));
    },

    /** Hasil pencarian: maksimal 8 barang yang belum dipilih di baris lain. */
    hasilCari(item) {
        const kata = item.cari.trim().toLowerCase();
        const dipakai = this.items.filter((i) => i !== item).map((i) => i.id_barang);

        return this.barang
            .filter((b) => !dipakai.includes(String(b.id)))
            .filter((b) => kata === '' || b.kode.toLowerCase().includes(kata) || b.nama.toLowerCase().includes(kata))
            .slice(0, 8);
    },

    pilih(item, b) {
        item.id_barang = String(b.id);
        item.cari = '';
        item.buka = false;
        if (item.harga_beli === '' && Number(b.harga_beli_terakhir) > 0) {
            item.harga_beli = Number(b.harga_beli_terakhir);
        }
    },

    /** Enter di kotak cari: cocok persis dengan kode/barcode (hasil scan) atau satu-satunya hasil. */
    pilihDenganEnter(item) {
        const kata = item.cari.trim().toLowerCase();
        const hasil = this.hasilCari(item);
        const tepat = hasil.find((b) => b.kode.toLowerCase() === kata);
        if (tepat || hasil.length === 1) this.pilih(item, tepat ?? hasil[0]);
    },

    ganti(item) {
        item.id_barang = '';
        item.buka = true;
    },

    subtotalSen(item) {
        return keSen(item.harga_beli) * (parseInt(item.jumlah, 10) || 0);
    },

    totalSen() {
        return this.items.reduce((total, item) => total + this.subtotalSen(item), 0);
    },

    rupiah: formatRupiah,

    /** Pesan error server untuk baris tertentu, mis. galat(0, 'jumlah'). */
    galat(index, kolom) {
        return (this.errors[`items.${index}.${kolom}`] ?? [])[0] ?? '';
    },
});
