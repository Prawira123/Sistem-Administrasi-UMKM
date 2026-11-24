/**
 * Alpine.js: halaman kasir (POS).
 * - Kiri : kotak scan/cari (selalu fokus), hasil dari endpoint JSON barang.cari.
 * - Kanan: keranjang, metode bayar, jumlah bayar (tombol cepat), kembalian.
 * Total dan kembalian di sini hanya tampilan; server menghitung ulang dan mengecek stok saat menyimpan.
 */
import { formatRupiah, keSen } from './uang';

const NOMINAL_UANG = [2000, 5000, 10000, 20000, 50000, 100000];

export default ({ urlCari, keranjang = [], metode = 'tunai', bayar = '' } = {}) => ({
    urlCari,
    keranjang: keranjang.map((item) => ({ ...item, jumlah: Number(item.jumlah) || 1 })),
    metode,
    bayar,
    cari: '',
    hasil: [],
    memuat: false,
    pesanCari: '',
    menyimpan: false,
    pengendali: null,
    jedaCari: null,

    init() {
        this.fokusScan();
    },

    fokusScan() {
        this.$nextTick(() => this.$refs.scan?.focus());
    },

    /** Pencarian saat mengetik (jeda 250 ms agar tidak memanggil server setiap huruf). */
    ketik() {
        clearTimeout(this.jedaCari);
        this.jedaCari = setTimeout(() => this.muat(this.cari), 250);
    },

    async muat(kata) {
        kata = kata.trim();
        this.pesanCari = '';
        if (kata === '') {
            this.hasil = [];
            return [];
        }

        this.pengendali?.abort();
        this.pengendali = new AbortController();
        this.memuat = true;

        try {
            const respons = await fetch(`${this.urlCari}?q=${encodeURIComponent(kata)}`, {
                headers: { Accept: 'application/json' },
                signal: this.pengendali.signal,
            });
            if (!respons.ok) throw new Error(respons.status);
            this.hasil = (await respons.json()).data;
            if (this.hasil.length === 0) this.pesanCari = 'Barang tidak ditemukan atau stok habis.';
        } catch (e) {
            if (e.name === 'AbortError') return this.hasil;
            this.hasil = [];
            this.pesanCari = 'Gagal memuat data barang. Coba lagi.';
        } finally {
            this.memuat = false;
        }

        return this.hasil;
    },

    /** Enter (atau akhir scan barcode): kode yang cocok persis, atau satu-satunya hasil, langsung masuk keranjang. */
    async enter() {
        clearTimeout(this.jedaCari);
        const kata = this.cari.trim().toLowerCase();
        const hasil = await this.muat(this.cari);
        const tepat = hasil.find((b) => b.tepat || b.kode.toLowerCase() === kata);
        if (tepat || hasil.length === 1) this.tambah(tepat ?? hasil[0]);
    },

    tambah(barang) {
        const ada = this.keranjang.find((item) => item.id === barang.id);
        if (ada) {
            ada.stok = barang.stok;
            if (ada.jumlah < ada.stok) ada.jumlah++;
            else this.pesanCari = `Stok ${barang.nama} hanya ${barang.stok}.`;
        } else {
            this.keranjang.push({ ...barang, jumlah: 1 });
            this.pesanCari = '';
        }
        this.cari = '';
        this.hasil = [];
        this.fokusScan();
    },

    ubah(item, selisih) {
        item.jumlah = Math.min(item.stok, Math.max(1, (parseInt(item.jumlah, 10) || 0) + selisih));
    },

    /** Saat input jumlah ditinggalkan: batasi 1 s.d. stok. */
    rapikan(item) {
        item.jumlah = Math.min(item.stok, Math.max(1, parseInt(item.jumlah, 10) || 1));
    },

    hapus(index) {
        this.keranjang.splice(index, 1);
        this.fokusScan();
    },

    kosongkan() {
        this.keranjang = [];
        this.bayar = '';
        this.fokusScan();
    },

    subtotalSen(item) {
        return keSen(item.harga_jual) * (parseInt(item.jumlah, 10) || 0);
    },

    totalSen() {
        return this.keranjang.reduce((total, item) => total + this.subtotalSen(item), 0);
    },

    totalItem() {
        return this.keranjang.reduce((total, item) => total + (parseInt(item.jumlah, 10) || 0), 0);
    },

    bayarSen() {
        return this.metode === 'tunai' ? keSen(this.bayar) : this.totalSen();
    },

    kembalianSen() {
        return this.bayarSen() - this.totalSen();
    },

    /** Tombol cepat: uang pas, lalu pecahan uang kertas terdekat di atas total. */
    tombolCepat() {
        const total = this.totalSen() / 100;
        if (total <= 0) return [];

        const pilihan = new Set([total]);
        for (const nominal of NOMINAL_UANG) {
            const dibulatkan = Math.ceil(total / nominal) * nominal;
            if (dibulatkan > total) pilihan.add(dibulatkan);
        }

        return [...pilihan].sort((a, b) => a - b).slice(0, 5);
    },

    pilihNominal(nilai) {
        this.bayar = nilai;
    },

    bisaSimpan() {
        return this.keranjang.length > 0 && this.totalSen() > 0 && this.kembalianSen() >= 0 && !this.menyimpan;
    },

    simpan(event) {
        if (!this.bisaSimpan()) {
            event.preventDefault();
            return;
        }
        this.menyimpan = true;
    },

    rupiah: formatRupiah,
    sen: keSen,
    angka: (nilai) => new Intl.NumberFormat('id-ID').format(nilai),
});
