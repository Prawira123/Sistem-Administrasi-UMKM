<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\HppPeriode;
use App\Models\KartuStok;
use App\Models\Kas;
use App\Models\Penjualan;
use App\Support\Uang;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proses 7.0 Buat Laporan (rancangan bagian 4). Semua laporan dihitung langsung dari data transaksi
 * sesuai periode. Hasilnya array biasa yang dipakai halaman web, view PDF, dashboard, dan AI Assistant (modul 9).
 * Nominal uang berupa string 2 desimal (bcmath lewat App\Support\Uang), bukan float.
 */
class LaporanService
{
    public const LABEL_KATEGORI_KAS = [
        'penjualan' => 'Penjualan',
        'pembelian_barang' => 'Pembelian barang',
        'operasional' => 'Beban operasional',
        'modal' => 'Modal',
        'prive' => 'Prive',
        'penyesuaian' => 'Penyesuaian (validasi kas)',
    ];

    public function __construct(private readonly KasService $kas) {}

    /**
     * 1. Pergerakan stok per barang (atau semua barang) dalam periode.
     * Stok awal = stok_sesudah mutasi terakhir sebelum periode; saldo tiap baris = stok_sesudah;
     * stok akhir = saldo mutasi terakhir dalam periode (atau stok awal bila tidak ada mutasi).
     */
    public function pergerakanStok(CarbonInterface $dari, CarbonInterface $sampai, ?int $idBarang = null): array
    {
        [$dari, $sampai] = $this->rentang($dari, $sampai);

        $daftarBarang = Barang::with('kategori')
            ->when($idBarang, fn ($q) => $q->whereKey($idBarang))
            ->orderBy('nama_barang')
            ->get();

        $mutasi = KartuStok::with(['barangMasuk:id_barang_masuk,kode_barang_masuk', 'penjualan:id_penjualan,no_transaksi',
            'stokOpname:id_stok_opname,kode_opname', 'user:id_user,nama'])
            ->whereIn('id_barang', $daftarBarang->pluck('id_barang'))
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderBy('tanggal')
            ->orderBy('id_kartu_stok')
            ->get()
            ->groupBy('id_barang');

        $bagian = [];
        foreach ($daftarBarang as $barang) {
            $stokAwal = (int) (KartuStok::where('id_barang', $barang->id_barang)
                ->where('tanggal', '<', $dari)
                ->orderByDesc('tanggal')
                ->orderByDesc('id_kartu_stok')
                ->value('stok_sesudah') ?? 0);

            $baris = ($mutasi[$barang->id_barang] ?? collect())->map(fn (KartuStok $m) => [
                'tanggal' => $m->tanggal,
                'jenis' => $m->jenis_mutasi,
                'sumber' => $m->barangMasuk?->kode_barang_masuk ?? $m->penjualan?->no_transaksi ?? $m->stokOpname?->kode_opname ?? '-',
                'masuk' => $m->jumlah_masuk,
                'keluar' => $m->jumlah_keluar,
                'saldo' => $m->stok_sesudah,
                'hpp_satuan' => $m->hpp_satuan,
                'keterangan' => $m->keterangan,
                'oleh' => $m->user?->nama,
            ])->values();

            // Untuk "semua barang": lewati barang tanpa persediaan dan tanpa gerakan di periode ini.
            if (! $idBarang && $baris->isEmpty() && $stokAwal === 0) {
                continue;
            }

            $bagian[] = [
                'barang' => $barang,
                'stok_awal' => $stokAwal,
                'mutasi' => $baris,
                'total_masuk' => $baris->sum('masuk'),
                'total_keluar' => $baris->sum('keluar'),
                'stok_akhir' => $baris->isEmpty() ? $stokAwal : $baris->last()['saldo'],
            ];
        }

        return ['dari' => $dari, 'sampai' => $sampai, 'bagian' => $bagian];
    }

    /**
     * 2. Arus kas: saldo awal + Σ masuk per kategori − Σ keluar per kategori = saldo akhir.
     */
    public function arusKas(CarbonInterface $dari, CarbonInterface $sampai, bool $rincian = false): array
    {
        [$dari, $sampai] = $this->rentang($dari, $sampai);

        $saldoAwal = $this->kas->saldoSebelum($dari);

        $perKategori = Kas::whereBetween('tanggal', [$dari, $sampai])
            ->groupBy('jenis', 'kategori')
            ->select('jenis', 'kategori', DB::raw('SUM(jumlah) AS total'), DB::raw('COUNT(*) AS jumlah_transaksi'))
            ->get();

        $kelompok = fn (string $jenis) => $perKategori->where('jenis', $jenis)
            ->map(fn ($r) => [
                'kategori' => $r->kategori,
                'label' => self::LABEL_KATEGORI_KAS[$r->kategori],
                'total' => Uang::normal($r->total),
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
            ])
            ->sortBy('label')
            ->values();

        $masuk = $kelompok(KasService::MASUK);
        $keluar = $kelompok(KasService::KELUAR);
        $totalMasuk = $this->jumlahkan($masuk, 'total');
        $totalKeluar = $this->jumlahkan($keluar, 'total');

        $baris = collect();
        if ($rincian) {
            $saldo = $saldoAwal;
            $baris = Kas::whereBetween('tanggal', [$dari, $sampai])
                ->orderBy('tanggal')->orderBy('id_kas')
                ->get()
                ->map(function (Kas $k) use (&$saldo) {
                    $saldo = $k->jenis === KasService::MASUK ? Uang::tambah($saldo, $k->jumlah) : Uang::kurang($saldo, $k->jumlah);

                    return [
                        'tanggal' => $k->tanggal,
                        'jenis' => $k->jenis,
                        'kategori' => self::LABEL_KATEGORI_KAS[$k->kategori],
                        'keterangan' => $k->keterangan,
                        'jumlah' => $k->jumlah,
                        'saldo' => $saldo,
                    ];
                });
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'saldo_awal' => $saldoAwal,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'arus_bersih' => Uang::kurang($totalMasuk, $totalKeluar),
            'saldo_akhir' => Uang::kurang(Uang::tambah($saldoAwal, $totalMasuk), $totalKeluar),
            'rincian' => $baris,
        ];
    }

    /**
     * 3. Laba rugi bulanan (multi-step):
     *   Pendapatan (penjualan selesai) − HPP (Σ hpp_periode) = laba kotor − beban operasional per kategori = laba bersih.
     * Pembelian barang dan prive bukan beban. Bila HPP bulan itu belum dihitung, hpp/laba bernilai null
     * dan hpp_dihitung = false (view menampilkan peringatan, tidak memakai angka pengganti).
     */
    public function labaRugi(int $tahun, int $bulan): array
    {
        $awal = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $akhir = $awal->copy()->endOfMonth();

        $pendapatan = Uang::normal((string) Penjualan::where('status', 'selesai')
            ->whereBetween('tanggal_penjualan', [$awal, $akhir])
            ->sum('total_harga'));

        $hppQuery = HppPeriode::whereDate('periode_awal', $awal)->whereDate('periode_akhir', $akhir->toDateString());
        $hppDihitung = (clone $hppQuery)->exists();
        $hpp = $hppDihitung ? Uang::normal((string) (clone $hppQuery)->sum('total_hpp')) : null;

        // HPP sudah dihitung, tetapi ada mutasi stok baru setelahnya → sarankan hitung ulang.
        $hppUsang = false;
        if ($hppDihitung) {
            $dihitungPada = (clone $hppQuery)->max('updated_at');
            $hppUsang = KartuStok::whereBetween('tanggal', [$awal, $akhir])->where('created_at', '>', $dihitungPada)->exists();
        }

        $beban = DB::table('pengeluaran_operasional as p')
            ->join('kategori_pengeluaran as k', 'k.id_kategori_pengeluaran', '=', 'p.id_kategori_pengeluaran')
            ->whereBetween('p.tanggal_pengeluaran', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('k.id_kategori_pengeluaran', 'k.nama_kategori')
            ->orderBy('k.nama_kategori')
            ->select('k.nama_kategori', DB::raw('SUM(p.jumlah) AS total'))
            ->get()
            ->map(fn ($r) => ['kategori' => $r->nama_kategori, 'total' => Uang::normal($r->total)]);

        $totalBeban = $this->jumlahkan($beban, 'total');
        $labaKotor = $hpp !== null ? Uang::kurang($pendapatan, $hpp) : null;

        // Sebagai informasi saja (bukan bagian laba rugi).
        $diluar = Kas::whereBetween('tanggal', [$awal, $akhir])
            ->whereIn('kategori', ['pembelian_barang', 'prive'])
            ->groupBy('kategori')
            ->pluck(DB::raw('SUM(jumlah)'), 'kategori');

        return [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'dari' => $awal,
            'sampai' => $akhir,
            'pendapatan' => $pendapatan,
            'hpp' => $hpp,
            'hpp_dihitung' => $hppDihitung,
            'hpp_usang' => $hppUsang,
            'laba_kotor' => $labaKotor,
            'beban' => $beban,
            'total_beban' => $totalBeban,
            'laba_bersih' => $labaKotor !== null ? Uang::kurang($labaKotor, $totalBeban) : null,
            'pembelian_barang' => Uang::normal((string) ($diluar['pembelian_barang'] ?? '0')),
            'prive' => Uang::normal((string) ($diluar['prive'] ?? '0')),
        ];
    }

    /**
     * Estimasi laba bulan berjalan untuk dashboard bila HPP periodik belum dihitung (BUKAN laporan resmi):
     *   Σ penjualan.total_harga − Σ penjualan.total_hpp (HPP rata-rata saat transaksi, status selesai) − beban operasional.
     * Selisih stok opname tidak ikut, karena tidak tercatat di penjualan. Laporan laba rugi tetap hanya memakai hpp_periode.
     *
     * @return array{pendapatan: string, hpp_rata_rata: string, laba_kotor: string, total_beban: string, laba_bersih: string}
     */
    public function estimasiLabaBulan(int $tahun, int $bulan): array
    {
        $awal = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $akhir = $awal->copy()->endOfMonth();

        $penjualan = Penjualan::where('status', 'selesai')
            ->whereBetween('tanggal_penjualan', [$awal, $akhir])
            ->selectRaw('COALESCE(SUM(total_harga), 0) AS pendapatan, COALESCE(SUM(total_hpp), 0) AS hpp')
            ->first();

        $beban = (string) DB::table('pengeluaran_operasional')
            ->whereBetween('tanggal_pengeluaran', [$awal->toDateString(), $akhir->toDateString()])
            ->sum('jumlah');

        $labaKotor = Uang::kurang($penjualan->pendapatan, $penjualan->hpp);

        return [
            'pendapatan' => Uang::normal($penjualan->pendapatan),
            'hpp_rata_rata' => Uang::normal($penjualan->hpp),
            'laba_kotor' => $labaKotor,
            'total_beban' => Uang::normal($beban),
            'laba_bersih' => Uang::kurang($labaKotor, $beban),
        ];
    }

    /**
     * 4. Penjualan (status selesai): rekap per hari, per barang (terlaris), dan per metode pembayaran.
     */
    public function penjualan(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        [$dari, $sampai] = $this->rentang($dari, $sampai);
        $selesai = fn () => Penjualan::where('status', 'selesai')->whereBetween('tanggal_penjualan', [$dari, $sampai]);

        $perHari = $selesai()
            ->groupBy(DB::raw('DATE(tanggal_penjualan)'))
            ->orderBy(DB::raw('DATE(tanggal_penjualan)'))
            ->select(DB::raw('DATE(tanggal_penjualan) AS tanggal'), DB::raw('COUNT(*) AS transaksi'),
                DB::raw('SUM(total_item) AS unit'), DB::raw('SUM(total_harga) AS total'))
            ->get()
            ->map(fn ($r) => ['tanggal' => Carbon::parse($r->tanggal), 'transaksi' => (int) $r->transaksi,
                'unit' => (int) $r->unit, 'total' => Uang::normal($r->total)]);

        $perMetode = $selesai()
            ->groupBy('metode_pembayaran')
            ->select('metode_pembayaran', DB::raw('COUNT(*) AS transaksi'), DB::raw('SUM(total_harga) AS total'))
            ->orderBy('metode_pembayaran')
            ->get()
            ->map(fn ($r) => ['metode' => $r->metode_pembayaran, 'transaksi' => (int) $r->transaksi, 'total' => Uang::normal($r->total)]);

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'per_hari' => $perHari,
            'per_barang' => $this->barangTerlaris($dari, $sampai),
            'per_metode' => $perMetode,
            'jumlah_transaksi' => $perHari->sum('transaksi'),
            'total_unit' => $perHari->sum('unit'),
            'total' => $this->jumlahkan($perHari, 'total'),
            'jumlah_batal' => Penjualan::where('status', 'batal')->whereBetween('tanggal_penjualan', [$dari, $sampai])->count(),
        ];
    }

    /**
     * Barang terlaris (qty) dari penjualan selesai. $batas null = semua barang.
     *
     * @return Collection<int, array{kode: string, nama: string, satuan: string, qty: int, omzet: string}>
     */
    public function barangTerlaris(CarbonInterface $dari, CarbonInterface $sampai, ?int $batas = null): Collection
    {
        return DB::table('detail_penjualan as d')
            ->join('penjualan as p', 'p.id_penjualan', '=', 'd.id_penjualan')
            ->join('barang as b', 'b.id_barang', '=', 'd.id_barang')
            ->where('p.status', 'selesai')
            ->whereBetween('p.tanggal_penjualan', [$dari, $sampai])
            ->groupBy('b.id_barang', 'b.kode_barang', 'b.nama_barang', 'b.satuan')
            ->select('b.kode_barang', 'b.nama_barang', 'b.satuan', DB::raw('SUM(d.jumlah) AS qty'), DB::raw('SUM(d.subtotal) AS omzet'))
            ->orderByDesc('qty')
            ->orderByDesc('omzet')
            ->when($batas, fn ($q, $n) => $q->limit($n))
            ->get()
            ->map(fn ($r) => ['kode' => $r->kode_barang, 'nama' => $r->nama_barang, 'satuan' => $r->satuan,
                'qty' => (int) $r->qty, 'omzet' => Uang::normal($r->omzet)]);
    }

    /** 5. Stok minimum: barang aktif dengan stok <= stok_minimum. */
    public function stokMinimum(): array
    {
        return ['barang' => Barang::stokMenipis()->with('kategori')->orderBy('stok')->orderBy('nama_barang')->get()];
    }

    /**
     * Total penjualan selesai per hari untuk grafik dashboard (hari tanpa penjualan bernilai 0).
     *
     * @return array{label: list<string>, total: list<float>}
     */
    public function grafikPenjualan(int $hari): array
    {
        $awal = today()->subDays($hari - 1);
        $data = Penjualan::where('status', 'selesai')
            ->where('tanggal_penjualan', '>=', $awal)
            ->groupBy(DB::raw('DATE(tanggal_penjualan)'))
            ->pluck(DB::raw('SUM(total_harga)'), DB::raw('DATE(tanggal_penjualan)'));

        $label = [];
        $total = [];
        for ($i = 0; $i < $hari; $i++) {
            $tgl = $awal->copy()->addDays($i);
            $label[] = $tgl->format('d/m');
            $total[] = (float) ($data[$tgl->toDateString()] ?? 0); // hanya untuk sumbu grafik
        }

        return ['label' => $label, 'total' => $total];
    }

    /** @return array{0: Carbon, 1: Carbon} awal hari $dari s.d. akhir hari $sampai */
    private function rentang(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        return [Carbon::instance($dari)->startOfDay(), Carbon::instance($sampai)->endOfDay()];
    }

    private function jumlahkan(Collection $baris, string $kolom): string
    {
        return $baris->reduce(fn ($t, $r) => Uang::tambah($t, $r[$kolom]), '0.00');
    }
}
