/**
 * Helper uang di browser (hanya untuk tampilan; server tetap menghitung ulang dengan bcmath).
 * Hitungan memakai sen (bilangan bulat) agar tidak ada galat pembulatan float.
 */
export const keSen = (nilai) => {
    const angka = Number(String(nilai ?? '').replace(',', '.'));
    return Number.isFinite(angka) ? Math.round(angka * 100) : 0;
};

export const formatRupiah = (sen) =>
    (sen < 0 ? '-' : '') + 'Rp' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Math.abs(sen) / 100);
