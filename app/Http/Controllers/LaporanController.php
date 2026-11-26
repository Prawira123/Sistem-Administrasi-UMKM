<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Services\LaporanService;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proses 7.0 Buat Laporan. Khusus Administrator.
 * Setiap laporan punya satu halaman web (Tailwind, bisa dicetak lewat varian print:) dan
 * satu view PDF (CSS biasa untuk dompdf). Keduanya memakai data yang sama dari LaporanService.
 * Unduh PDF: tambahkan ?unduh=pdf pada URL laporan.
 */
class LaporanController extends Controller implements HasMiddleware
{
    public function __construct(private readonly LaporanService $laporan) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-laporan')];
    }

    public function index(): View
    {
        return view('laporan.index');
    }

    public function pergerakanStok(Request $request): View|Response
    {
        [$dari, $sampai] = $this->periode($request, ['barang' => ['nullable', 'integer', 'exists:barang,id_barang']]);
        $idBarang = $request->integer('barang') ?: null;

        $data = $this->laporan->pergerakanStok($dari, $sampai, $idBarang);
        $data['daftarBarang'] = Barang::orderBy('nama_barang')->get(['id_barang', 'kode_barang', 'nama_barang'])
            ->mapWithKeys(fn ($b) => [$b->id_barang => "{$b->kode_barang} · {$b->nama_barang}"]);
        $data['idBarang'] = $idBarang;

        return $this->tampilkan($request, 'pergerakan-stok', 'Laporan Pergerakan Stok', $this->teksPeriode($dari, $sampai), $data);
    }

    public function arusKas(Request $request): View|Response
    {
        [$dari, $sampai] = $this->periode($request, ['rincian' => ['nullable', 'boolean']]);
        $data = $this->laporan->arusKas($dari, $sampai, $request->boolean('rincian'));

        return $this->tampilkan($request, 'arus-kas', 'Laporan Arus Kas', $this->teksPeriode($dari, $sampai), $data);
    }

    public function labaRugi(Request $request): View|Response
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $bulan = Carbon::createFromFormat('Y-m-d', ($request->query('bulan') ?? now()->format('Y-m')).'-01');
        $data = $this->laporan->labaRugi($bulan->year, $bulan->month);

        return $this->tampilkan($request, 'laba-rugi', 'Laporan Laba Rugi', 'Bulan '.$bulan->translatedFormat('F Y'), $data);
    }

    public function penjualan(Request $request): View|Response
    {
        [$dari, $sampai] = $this->periode($request);
        $data = $this->laporan->penjualan($dari, $sampai);

        return $this->tampilkan($request, 'penjualan', 'Laporan Penjualan', $this->teksPeriode($dari, $sampai), $data);
    }

    public function stokMinimum(Request $request): View|Response
    {
        return $this->tampilkan($request, 'stok-minimum', 'Laporan Stok Minimum',
            'Per '.Format::tanggalWaktu(now()), $this->laporan->stokMinimum());
    }

    /**
     * Filter periode ?dari=&sampai= (Y-m-d); default awal bulan berjalan s.d. hari ini.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periode(Request $request, array $aturanTambahan = []): array
    {
        $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ] + $aturanTambahan, [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        return [
            Carbon::parse($request->query('dari') ?? now()->startOfMonth()->toDateString())->startOfDay(),
            Carbon::parse($request->query('sampai') ?? now()->toDateString())->endOfDay(),
        ];
    }

    private function teksPeriode(Carbon $dari, Carbon $sampai): string
    {
        return 'Periode '.Format::tanggal($dari).' s.d. '.Format::tanggal($sampai);
    }

    /** Halaman web, atau PDF bila ?unduh=pdf. Kop laporan (toko, judul, periode, tanggal cetak, pencetak) ikut di keduanya. */
    private function tampilkan(Request $request, string $jenis, string $judul, string $periode, array $data): View|Response
    {
        $kop = [
            'toko' => config('toko.nama'),
            'alamat' => config('toko.alamat'),
            'judul' => $judul,
            'periode' => $periode,
            'dicetak' => Format::tanggalWaktu(now()),
            'pencetak' => $request->user()->nama,
        ];

        if ($request->query('unduh') === 'pdf') {
            $orientasi = in_array($jenis, ['pergerakan-stok', 'arus-kas'], true) ? 'landscape' : 'portrait';

            return Pdf::loadView("laporan.pdf.{$jenis}", ['kop' => $kop] + $data)
                ->setPaper('a4', $orientasi)
                ->download(Str::slug($judul.' '.$periode).'.pdf');
        }

        return view("laporan.{$jenis}", ['kop' => $kop, 'jenis' => $jenis] + $data);
    }
}
