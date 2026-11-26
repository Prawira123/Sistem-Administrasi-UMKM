<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriPengeluaran;
use App\Models\Penjualan;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BarangMasukService;
use App\Services\HppService;
use App\Services\KasService;
use App\Services\LaporanService;
use App\Services\PengeluaranService;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 11, 24, 15, 0));
        $this->admin = User::factory()->administrator()->create();
        $this->kasir = User::factory()->kasir()->create();
        $this->supplier = Supplier::factory()->create();
    }

    private function masuk(Barang $barang, int $jumlah, string $harga, string $tanggal): void
    {
        $this->travelTo(Carbon::parse($tanggal.' 09:00'));
        app(BarangMasukService::class)->simpan(['id_supplier' => $this->supplier->id_supplier, 'tanggal_masuk' => $tanggal,
            'items' => [['id_barang' => $barang->id_barang, 'jumlah' => $jumlah, 'harga_beli' => $harga]]], $this->admin);
    }

    private function jual(Barang $barang, int $jumlah, string $metode = 'tunai'): Penjualan
    {
        return app(PenjualanService::class)->simpan(
            [['id_barang' => $barang->id_barang, 'jumlah' => $jumlah]], $metode, (string) ($jumlah * $barang->harga_jual), $this->kasir,
        );
    }

    private function modalPrive(string $kategori, string $jumlah, string $tanggal): void
    {
        app(KasService::class)->catat(KasService::KATEGORI_MANUAL[$kategori], $kategori, $jumlah, [], $this->admin,
            Carbon::parse($tanggal.' 08:00'), ucfirst($kategori));
    }

    private function beban(string $kategori, string $jumlah, string $tanggal): void
    {
        $id = KategoriPengeluaran::firstOrCreate(['nama_kategori' => $kategori])->id_kategori_pengeluaran;
        app(PengeluaranService::class)->simpan(['id_kategori_pengeluaran' => $id, 'tanggal_pengeluaran' => $tanggal,
            'deskripsi' => $kategori, 'jumlah' => $jumlah, 'metode_pembayaran' => 'tunai'], null, $this->admin);
    }

    /** Data HPP sesuai contoh modul 7: Okt 10 @ 3.000; Nov beli 20 @ 3.600, terjual 18 @ 5.000, 1 penjualan dibatalkan. */
    private function dataContohHpp(): Barang
    {
        $barang = Barang::factory()->create(['harga_jual' => 5000, 'stok_minimum' => 2]);
        $this->masuk($barang, 10, '3000', '2026-10-15');
        $this->masuk($barang, 20, '3600', '2026-11-05');

        $this->travelTo(Carbon::create(2026, 11, 10, 10, 0));
        $this->jual($barang, 18, 'qris');
        $batal = $this->jual($barang, 1);
        app(PenjualanService::class)->batalkan($batal, 'Salah scan', $this->admin);

        $this->travelTo(Carbon::create(2026, 11, 24, 15, 0));

        return $barang;
    }

    // 1. Arus kas: saldo awal + masuk − keluar = saldo akhir
    public function test_arus_kas_saldo_awal_ditambah_masuk_dikurang_keluar_sama_dengan_saldo_akhir(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 20, 8, 0));
        $this->modalPrive('modal', '1000000', '2026-10-20');              // sebelum periode → saldo awal

        $barang = Barang::factory()->create(['harga_jual' => 10000]);
        $this->masuk($barang, 10, '3000', '2026-11-03');                  // keluar 30.000
        $this->travelTo(Carbon::create(2026, 11, 4, 10, 0));
        $this->modalPrive('modal', '500000', '2026-11-04');               // masuk 500.000
        $this->jual($barang, 2);                                          // masuk 20.000
        $this->beban('Listrik', '50000', '2026-11-04');                   // keluar 50.000
        $this->modalPrive('prive', '100000', '2026-11-04');               // keluar 100.000
        $this->travelTo(Carbon::create(2026, 11, 24, 15, 0));

        $data = app(LaporanService::class)->arusKas(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-24'), true);

        $this->assertSame('1000000.00', $data['saldo_awal']);
        $this->assertSame('520000.00', $data['total_masuk']);
        $this->assertSame('180000.00', $data['total_keluar']);
        $this->assertSame('1340000.00', $data['saldo_akhir']);
        $this->assertSame(app(KasService::class)->saldoSebelum(now()), $data['saldo_akhir']);
        $this->assertEqualsCanonicalizing(['modal', 'penjualan'], $data['masuk']->pluck('kategori')->all());
        $this->assertEqualsCanonicalizing(['pembelian_barang', 'operasional', 'prive'], $data['keluar']->pluck('kategori')->all());
        $this->assertCount(5, $data['rincian']);
        $this->assertSame('1340000.00', $data['rincian']->last()['saldo']);

        $this->actingAs($this->admin)
            ->get(route('laporan.arus-kas', ['dari' => '2026-11-01', 'sampai' => '2026-11-24', 'rincian' => 1]))
            ->assertOk()
            ->assertSee('Rp1.000.000')->assertSee('Rp520.000')->assertSee('Rp180.000')->assertSee('Rp1.340.000')
            ->assertSee('Rincian per transaksi');

        $this->get(route('laporan.arus-kas', ['dari' => '2026-11-24', 'sampai' => '2026-11-01']))->assertSessionHasErrors('sampai');
    }

    // 2. Laba rugi memakai hpp_periode; mengabaikan penjualan batal, pembelian barang, dan prive
    public function test_laba_rugi_memakai_hpp_periode_dan_mengabaikan_batal_pembelian_prive(): void
    {
        $this->dataContohHpp();
        $this->beban('Listrik', '20000', '2026-11-12');
        $this->beban('Air', '5000', '2026-11-13');
        $this->beban('Listrik', '9999', '2026-10-31');                    // bulan lain, tidak dihitung
        $this->modalPrive('prive', '100000', '2026-11-14');
        app(HppService::class)->hitungBulan(2026, 11, $this->admin);

        $lr = app(LaporanService::class)->labaRugi(2026, 11);

        $this->assertTrue($lr['hpp_dihitung']);
        $this->assertSame('90000.00', $lr['pendapatan']);                 // 18 × 5.000; penjualan batal diabaikan
        $this->assertSame('61200.00', $lr['hpp']);                        // dari hpp_periode, bukan pembelian 72.000
        $this->assertSame('28800.00', $lr['laba_kotor']);
        $this->assertSame(['Air' => '5000.00', 'Listrik' => '20000.00'], $lr['beban']->pluck('total', 'kategori')->all());
        $this->assertSame('25000.00', $lr['total_beban']);
        $this->assertSame('3800.00', $lr['laba_bersih']);                 // prive dan pembelian tidak mengurangi laba
        $this->assertSame('72000.00', $lr['pembelian_barang']);
        $this->assertSame('100000.00', $lr['prive']);

        $this->actingAs($this->admin)->get(route('laporan.laba-rugi', ['bulan' => '2026-11']))
            ->assertOk()
            ->assertSee('Rp90.000')->assertSee('Rp61.200')->assertSee('Rp28.800')->assertSee('Rp3.800')
            ->assertDontSee('HPP periodik bulan ini belum dihitung');
    }

    // 3. Laba rugi memberi peringatan bila HPP bulan itu belum dihitung
    public function test_laba_rugi_memberi_peringatan_bila_hpp_belum_dihitung(): void
    {
        $this->dataContohHpp();

        $lr = app(LaporanService::class)->labaRugi(2026, 11);
        $this->assertFalse($lr['hpp_dihitung']);
        $this->assertNull($lr['hpp']);
        $this->assertNull($lr['laba_kotor']);
        $this->assertNull($lr['laba_bersih']);
        $this->assertSame('90000.00', $lr['pendapatan']);

        $this->actingAs($this->admin)->get(route('laporan.laba-rugi', ['bulan' => '2026-11']))
            ->assertOk()
            ->assertSee('HPP periodik bulan ini belum dihitung')
            ->assertSee(route('hpp.index', ['bulan' => '2026-11']), false)
            ->assertDontSee('Rp28.800');

        // Setelah HPP dihitung, mutasi stok baru memunculkan saran hitung ulang.
        app(HppService::class)->hitungBulan(2026, 11, $this->admin);
        $this->travelTo(now()->addMinute());
        $this->jual(Barang::first(), 1);
        $this->assertTrue(app(LaporanService::class)->labaRugi(2026, 11)['hpp_usang']);
    }

    // 4. Pergerakan stok: saldo akhir sama dengan barang.stok
    public function test_pergerakan_stok_saldo_akhir_sama_dengan_stok_barang(): void
    {
        $barang = $this->dataContohHpp();
        $lain = Barang::factory()->create(['harga_jual' => 2000]);
        $this->masuk($lain, 7, '1500', '2026-11-20');
        $this->travelTo(Carbon::create(2026, 11, 24, 15, 0));

        $data = app(LaporanService::class)->pergerakanStok(Carbon::parse('2026-11-01'), now(), $barang->id_barang);
        $bagian = $data['bagian'][0];

        $this->assertCount(1, $data['bagian']);
        $this->assertSame(10, $bagian['stok_awal']);                     // dari mutasi Oktober
        $this->assertSame(21, $bagian['total_masuk']);                   // 20 beli + 1 pembatalan
        $this->assertSame(19, $bagian['total_keluar']);                  // 18 + 1 (yang dibatalkan)
        $this->assertSame($barang->fresh()->stok, $bagian['stok_akhir']);
        $this->assertSame(12, $bagian['stok_akhir']);
        $this->assertSame($bagian['stok_awal'] + $bagian['total_masuk'] - $bagian['total_keluar'], $bagian['stok_akhir']);
        $this->assertSame(['masuk', 'keluar', 'keluar', 'penyesuaian'], $bagian['mutasi']->pluck('jenis')->all());
        $this->assertStringStartsWith('BM-', $bagian['mutasi'][0]['sumber']);
        $this->assertStringStartsWith('TRX-', $bagian['mutasi'][1]['sumber']);

        // Semua barang: setiap bagian juga cocok dengan stok barangnya.
        $semua = app(LaporanService::class)->pergerakanStok(Carbon::parse('2026-11-01'), now());
        $this->assertCount(2, $semua['bagian']);
        foreach ($semua['bagian'] as $b) {
            $this->assertSame($b['barang']->fresh()->stok, $b['stok_akhir']);
        }

        $this->actingAs($this->admin)
            ->get(route('laporan.pergerakan-stok', ['dari' => '2026-11-01', 'sampai' => '2026-11-24', 'barang' => $barang->id_barang]))
            ->assertOk()->assertSee('Stok awal periode')->assertSee($barang->nama_barang);
    }

    // 5. PDF bisa diunduh untuk setiap laporan
    public function test_pdf_laporan_bisa_diunduh(): void
    {
        $this->dataContohHpp();
        $this->actingAs($this->admin);

        foreach ([
            route('laporan.pergerakan-stok', ['unduh' => 'pdf']),
            route('laporan.arus-kas', ['unduh' => 'pdf', 'rincian' => 1]),
            route('laporan.laba-rugi', ['unduh' => 'pdf', 'bulan' => '2026-11']),
            route('laporan.penjualan', ['unduh' => 'pdf']),
            route('laporan.stok-minimum', ['unduh' => 'pdf']),
        ] as $url) {
            $respons = $this->get($url);
            $respons->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $respons->getContent());
            $this->assertStringContainsString('attachment', $respons->headers->get('content-disposition'));
            $this->assertLessThan(200_000, strlen($respons->getContent()), 'Font subsetting dompdf harus aktif.');
        }

        // Halaman web laporan penjualan dan stok minimum juga terbuka.
        $this->get(route('laporan.index'))->assertOk();
        $this->get(route('laporan.penjualan', ['dari' => '2026-11-01', 'sampai' => '2026-11-24']))->assertOk()->assertSee('Rp90.000');
        $this->get(route('laporan.stok-minimum'))->assertOk();
    }

    // 6. Kasir → 403
    public function test_kasir_tidak_bisa_membuka_laporan(): void
    {
        $this->actingAs($this->kasir);

        foreach (['laporan.index', 'laporan.pergerakan-stok', 'laporan.arus-kas', 'laporan.laba-rugi', 'laporan.penjualan', 'laporan.stok-minimum'] as $nama) {
            $this->get(route($nama))->assertForbidden();
            $this->get(route($nama, ['unduh' => 'pdf']))->assertForbidden();
        }

        // Dashboard kasir tanpa angka keuangan; dashboard admin dengan grafik dan ringkasan.
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Grafik penjualan')->assertDontSee('Saldo kas');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Grafik penjualan')->assertSee('5 barang terlaris')->assertSee('Saldo kas')->assertSee('Laba bulan ini');
    }

    // Dashboard: estimasi laba (HPP rata-rata dari penjualan) selama HPP periodik belum dihitung; laporan resmi tidak berubah
    public function test_dashboard_menampilkan_estimasi_laba_bila_hpp_periodik_belum_dihitung(): void
    {
        $barang = $this->dataContohHpp();                                // 18 × 5.000 = 90.000; HPP rata-rata 18 × 3.400 = 61.200
        $this->beban('Listrik', '20000', '2026-11-12');
        $this->beban('Air', '5000', '2026-11-13');
        $this->modalPrive('prive', '100000', '2026-11-14');               // prive tidak mengurangi laba
        // Selisih opname (rusak 2 unit) tidak tercatat di penjualan → hanya laba resmi (HPP periodik) yang ikut turun.
        $this->travelTo(Carbon::create(2026, 11, 20, 10, 0));
        $opname = app(\App\Services\StokOpnameService::class)->buatDraft($this->admin, null, null);
        $detail = $opname->detail()->where('id_barang', $barang->id_barang)->sole();
        app(\App\Services\StokOpnameService::class)->simpanHitungan($opname,
            [$detail->id_detail_opname => ['stok_fisik' => 10, 'keterangan' => 'Rusak']], $this->admin);
        app(\App\Services\StokOpnameService::class)->ajukan($opname->fresh(), $this->admin);
        app(\App\Services\StokOpnameService::class)->setujui($opname->fresh(), $this->admin);
        $this->travelTo(Carbon::create(2026, 11, 24, 15, 0));

        $estimasi = app(LaporanService::class)->estimasiLabaBulan(2026, 11);
        $this->assertSame('90000.00', $estimasi['pendapatan']);
        $this->assertSame('61200.00', $estimasi['hpp_rata_rata']);
        $this->assertSame('25000.00', $estimasi['total_beban']);
        $this->assertSame('3800.00', $estimasi['laba_bersih']);

        // Laporan resmi tetap tanpa angka laba selama HPP periodik belum dihitung.
        $this->assertNull(app(LaporanService::class)->labaRugi(2026, 11)['laba_bersih']);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Estimasi (HPP rata-rata)')->assertSee('Rp3.800')
            ->assertSee(route('hpp.index'), false)->assertSee('Hitung HPP periodik');

        // Setelah HPP periodik dihitung: kartu memakai laba resmi (HPP 61.200 + 2 × 3.400 rusak = 68.000 → rugi 3.000).
        app(HppService::class)->hitungBulan(2026, 11, $this->admin);
        $this->assertSame('-3000.00', app(LaporanService::class)->labaRugi(2026, 11)['laba_bersih']);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Laba bersih (HPP periodik)')->assertSee('-Rp3.000')->assertDontSee('Estimasi (HPP rata-rata)');
    }
}
