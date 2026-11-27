<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\HppPeriode;
use App\Models\Kas;
use App\Models\KategoriPengeluaran;
use App\Models\PengeluaranOperasional;
use App\Models\Supplier;
use App\Models\User;
use App\Models\ValidasiKas;
use App\Services\BarangMasukService;
use App\Services\KasService;
use App\Services\PengeluaranService;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class KeuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(15, 0));
        $this->admin = User::factory()->administrator()->create();
        $this->kasir = User::factory()->kasir()->create();
    }

    private function kategori(string $nama = 'Listrik'): KategoriPengeluaran
    {
        return KategoriPengeluaran::create(['nama_kategori' => $nama]);
    }

    private function jualTunai(int $jumlah, int $hargaJual = 10000): void
    {
        $barang = Barang::factory()->stok(100, 1000)->create(['harga_jual' => $hargaJual]);
        app(PenjualanService::class)->simpan(
            [['id_barang' => $barang->id_barang, 'jumlah' => $jumlah]], 'tunai', (string) ($jumlah * $hargaJual), $this->kasir,
        );
    }

    // 1. Pengeluaran membuat kas keluar operasional; ubah ikut memperbarui kas; hapus menghapus kas
    public function test_pengeluaran_membuat_kas_keluar_dan_hapus_menghapus_kas(): void
    {
        Storage::fake('public');
        $listrik = $this->kategori();

        $this->actingAs($this->admin)->post(route('pengeluaran.store'), [
            'id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran,
            'tanggal_pengeluaran' => '2026-11-20',
            'deskripsi' => 'Token listrik November',
            'jumlah' => '250000',
            'metode_pembayaran' => 'tunai',
            'bukti' => UploadedFile::fake()->image('nota.jpg')->size(500),
        ])->assertRedirect(route('pengeluaran.index'))->assertSessionHasNoErrors();

        $pengeluaran = PengeluaranOperasional::sole();
        $this->assertSame('OP-20261120-01', $pengeluaran->kode_pengeluaran);
        $this->assertStringStartsWith('bukti/', $pengeluaran->bukti);
        Storage::disk('public')->assertExists($pengeluaran->bukti);

        $kas = Kas::sole();
        $this->assertSame('keluar', $kas->jenis);
        $this->assertSame('operasional', $kas->kategori);
        $this->assertSame($pengeluaran->id_pengeluaran, $kas->id_pengeluaran);
        $this->assertSame('250000.00', $kas->jumlah);
        $this->assertSame('2026-11-20', $kas->tanggal->toDateString());

        $this->get(route('pengeluaran.index'))->assertOk()->assertSee('OP-20261120-01');
        $this->get(route('pengeluaran.bukti', $pengeluaran))->assertOk();

        // Ubah jumlah → kas ikut berubah (masih satu baris).
        $this->put(route('pengeluaran.update', $pengeluaran), [
            'id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran,
            'tanggal_pengeluaran' => '2026-11-21',
            'deskripsi' => 'Token listrik November (koreksi)',
            'jumlah' => '275000.50',
            'metode_pembayaran' => 'transfer',
        ])->assertSessionHasNoErrors();
        $kas->refresh();
        $this->assertSame('275000.50', $kas->jumlah);
        $this->assertSame('2026-11-21', $kas->tanggal->toDateString());
        $this->assertSame(1, Kas::count());

        // Validasi: bukti bukan gambar/pdf dan jumlah 0 ditolak.
        $this->post(route('pengeluaran.store'), [
            'id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran,
            'tanggal_pengeluaran' => '2026-11-25', 'deskripsi' => 'X', 'jumlah' => 0, 'metode_pembayaran' => 'tunai',
            'bukti' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors(['tanggal_pengeluaran', 'jumlah', 'bukti']);

        // Hapus → kas dan file bukti ikut terhapus.
        $bukti = $pengeluaran->bukti;
        $this->delete(route('pengeluaran.destroy', $pengeluaran))->assertRedirect(route('pengeluaran.index'));
        $this->assertSame(0, PengeluaranOperasional::count());
        $this->assertSame(0, Kas::count());
        Storage::disk('public')->assertMissing($bukti);

        // Kategori yang sudah dipakai tidak bisa dihapus; yang belum dipakai bisa.
        $this->post(route('pengeluaran.store'), [
            'id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran,
            'tanggal_pengeluaran' => '2026-11-22', 'deskripsi' => 'Token', 'jumlah' => 100000, 'metode_pembayaran' => 'tunai',
        ]);
        $this->delete(route('kategori-pengeluaran.destroy', $listrik))->assertSessionHas('error');
        $air = $this->kategori('Air');
        $this->delete(route('kategori-pengeluaran.destroy', $air))->assertSessionHas('success');
    }

    // 2. Modal kas masuk, prive kas keluar; KasService menolak prive jenis masuk
    public function test_modal_masuk_prive_keluar_dan_saldo_berjalan(): void
    {
        $this->actingAs($this->admin)->get(route('kas.create', ['kategori' => 'modal']))->assertOk();

        $this->post(route('kas.store'), ['kategori' => 'modal', 'tanggal' => '2026-11-02', 'jumlah' => 5000000, 'keterangan' => 'Modal awal'])
            ->assertRedirect(route('kas.index'))->assertSessionHasNoErrors();
        $this->post(route('kas.store'), ['kategori' => 'prive', 'tanggal' => '2026-11-03', 'jumlah' => 750000, 'keterangan' => 'Keperluan rumah'])
            ->assertSessionHasNoErrors();
        $this->post(route('kas.store'), ['kategori' => 'penjualan', 'tanggal' => '2026-11-03', 'jumlah' => 1, 'keterangan' => 'X'])
            ->assertSessionHasErrors('kategori');
        $this->post(route('kas.store'), ['kategori' => 'modal', 'tanggal' => '2026-11-25', 'jumlah' => 1, 'keterangan' => 'Besok'])
            ->assertSessionHasErrors('tanggal');

        $modal = Kas::where('kategori', 'modal')->sole();
        $prive = Kas::where('kategori', 'prive')->sole();
        $this->assertSame('masuk', $modal->jenis);
        $this->assertSame('5000000.00', $modal->jumlah);
        $this->assertSame('keluar', $prive->jenis);
        $this->assertSame('750000.00', $prive->jumlah);

        // Buku kas: saldo berjalan dan saldo akhir 4.250.000.
        $this->get(route('kas.index', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))->assertOk()
            ->assertSee('Modal awal')->assertSee('Rp4.250.000');
        $this->assertSame('4250000.00', app(KasService::class)->saldoSebelum(now()->addDay()));

        // KasService menolak prive berjenis masuk dan modal berjenis keluar.
        try {
            app(KasService::class)->catat(KasService::MASUK, 'prive', '1000', [], $this->admin);
            $this->fail('Prive masuk seharusnya ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('prive', $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        app(KasService::class)->catat(KasService::KELUAR, 'modal', '1000', [], $this->admin);
    }

    public function test_hanya_kas_modal_prive_yang_bisa_dihapus_dari_buku_kas(): void
    {
        $this->jualTunai(1);
        $this->actingAs($this->admin)->post(route('kas.store'), ['kategori' => 'prive', 'tanggal' => '2026-11-24', 'jumlah' => 5000, 'keterangan' => 'Salah input']);

        $prive = Kas::where('kategori', 'prive')->sole();
        $penjualan = Kas::where('kategori', 'penjualan')->sole();

        $this->delete(route('kas.destroy', $prive))->assertRedirect(route('kas.index'));
        $this->assertNull($prive->fresh());

        $this->expectException(InvalidArgumentException::class);
        app(KasService::class)->hapusManual($penjualan);
    }

    // 3. Validasi kas: sesuai bila sama; selisih membuat kas penyesuaian dengan arah benar; tanggal ganda ditolak
    public function test_validasi_kas_sesuai_selisih_dan_tanggal_ganda(): void
    {
        // 22/11: tunai 30.000 (3 × 10.000), 23/11: tunai 20.000, 24/11: tunai 10.000.
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(10, 0));
        $this->jualTunai(3);
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(10, 0));
        $this->jualTunai(2);
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));
        $this->jualTunai(1);

        $this->actingAs($this->admin)->get(route('validasi-kas.create', ['tanggal' => '2026-11-22']))->assertOk()->assertSee('Rp30.000');

        // Sesuai.
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-22', 'uang_fisik' => 30000])->assertSessionHasNoErrors();
        $sesuai = ValidasiKas::whereDate('tanggal', '2026-11-22')->sole();
        $this->assertSame('sesuai', $sesuai->status);
        $this->assertSame('0.00', $sesuai->selisih);
        $this->assertNull($sesuai->kas);

        // Lebih 1.500 → kas penyesuaian masuk.
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-23', 'uang_fisik' => 21500])
            ->assertSessionHasErrors(['catatan' => 'Catatan wajib diisi karena ada selisih kas.']);
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-23', 'uang_fisik' => 21500, 'catatan' => 'Kembalian tidak diambil'])
            ->assertSessionHasNoErrors();
        $lebih = ValidasiKas::whereDate('tanggal', '2026-11-23')->sole();
        $this->assertSame('selisih', $lebih->status);
        $this->assertSame('20000.00', $lebih->total_tunai_sistem);
        $this->assertSame('1500.00', $lebih->selisih);
        $this->assertSame('masuk', $lebih->kas->jenis);
        $this->assertSame('penyesuaian', $lebih->kas->kategori);
        $this->assertSame('1500.00', $lebih->kas->jumlah);

        // Kurang 2.000 → kas penyesuaian keluar.
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-24', 'uang_fisik' => 8000, 'catatan' => 'Salah kembalian'])
            ->assertSessionHasNoErrors();
        $kurang = ValidasiKas::whereDate('tanggal', '2026-11-24')->sole();
        $this->assertSame('-2000.00', $kurang->selisih);
        $this->assertSame('keluar', $kurang->kas->jenis);
        $this->assertSame('2000.00', $kurang->kas->jumlah);
        $this->assertSame($kurang->id_validasi_kas, $kurang->kas->id_validasi_kas);

        // Tanggal ganda dan tanggal yang akan datang ditolak.
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-24', 'uang_fisik' => 10000])->assertSessionHasErrors('tanggal');
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-25', 'uang_fisik' => 0])->assertSessionHasErrors('tanggal');
        $this->get(route('validasi-kas.create', ['tanggal' => '2026-11-24']))->assertRedirect(route('validasi-kas.show', $kurang));
        $this->assertSame(3, ValidasiKas::count());
        $this->assertSame(2, Kas::where('kategori', 'penyesuaian')->count());

        $this->get(route('validasi-kas.index'))->assertOk();
        $this->get(route('validasi-kas.show', $lebih))->assertOk()->assertSee('Kembalian tidak diambil');
    }

    // 4. HPP periodik sesuai contoh: 30.000 + 72.000 − 40.800 = 61.200; terjual 18 → 3.400/unit
    public function test_hpp_periodik_sesuai_contoh_hitung(): void
    {
        $supplier = Supplier::factory()->create();
        $barang = Barang::factory()->create(['harga_jual' => 5000, 'stok_minimum' => 2]);
        $lain = Barang::factory()->create(); // tidak bergerak → tidak ikut disimpan
        $masuk = app(BarangMasukService::class);

        // Oktober: persediaan 10 × 3.000.
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        $masuk->simpan(['id_supplier' => $supplier->id_supplier, 'tanggal_masuk' => '2026-10-15',
            'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 10, 'harga_beli' => '3000']]], $this->admin);

        // November: beli 20 @ 3.600 (72.000) → HPP rata-rata 3.400; terjual 18 → sisa 12 × 3.400.
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $masuk->simpan(['id_supplier' => $supplier->id_supplier, 'tanggal_masuk' => '2026-11-05',
            'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 20, 'harga_beli' => '3600']]], $this->admin);
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));
        app(PenjualanService::class)->simpan([['id_barang' => $barang->id_barang, 'jumlah' => 18]], 'qris', '0', $this->kasir);

        // Penjualan batal tidak dihitung sebagai terjual.
        app(PenjualanService::class)->simpan([['id_barang' => $barang->id_barang, 'jumlah' => 1]], 'qris', '0', $this->kasir);
        $batal = \App\Models\Penjualan::latest('id_penjualan')->first();
        app(PenjualanService::class)->batalkan($batal, 'Salah scan', $this->admin);

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(15, 0));
        $this->actingAs($this->admin)->post(route('hpp.hitung'), ['bulan' => '2026-11'])
            ->assertRedirect(route('hpp.index', ['bulan' => '2026-11']))->assertSessionHasNoErrors();

        $hpp = HppPeriode::sole();
        $this->assertSame($barang->id_barang, $hpp->id_barang);
        $this->assertSame('2026-11-01', $hpp->periode_awal->toDateString());
        $this->assertSame('2026-11-30', $hpp->periode_akhir->toDateString());
        $this->assertSame(10, $hpp->qty_persediaan_awal);
        $this->assertSame('30000.00', $hpp->nilai_persediaan_awal);
        $this->assertSame(20, $hpp->qty_pembelian);
        $this->assertSame('72000.00', $hpp->nilai_pembelian);
        $this->assertSame(12, $hpp->qty_persediaan_akhir);
        $this->assertSame('40800.00', $hpp->nilai_persediaan_akhir);
        $this->assertSame('61200.00', $hpp->total_hpp);
        $this->assertSame(18, $hpp->qty_terjual);
        $this->assertSame('3400.00', $hpp->hpp_per_unit);
        $this->assertSame($this->admin->id_user, $hpp->id_user);
        $this->assertNotSame($lain->id_barang, $hpp->id_barang);

        $this->get(route('hpp.index', ['bulan' => '2026-11']))->assertOk()->assertSee('Rp61.200');

        // Hitung ulang butuh konfirmasi; dengan konfirmasi tetap satu baris per barang.
        $this->post(route('hpp.hitung'), ['bulan' => '2026-11'])->assertSessionHasErrors('periode');
        $this->post(route('hpp.hitung'), ['bulan' => '2026-11', 'hitung_ulang' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, HppPeriode::count());

        // Bulan yang akan datang ditolak.
        $this->post(route('hpp.hitung'), ['bulan' => '2026-12'])->assertSessionHasErrors('periode');

        // Oktober: awal 0, beli 30.000, akhir 10 × 3.000 → HPP 0 (tidak ada penjualan).
        $this->post(route('hpp.hitung'), ['bulan' => '2026-10'])->assertSessionHasNoErrors();
        $oktober = HppPeriode::whereDate('periode_awal', '2026-10-01')->sole();
        $this->assertSame('0.00', $oktober->total_hpp);
        $this->assertSame('0.00', $oktober->hpp_per_unit);
    }

    // Keuangan hanya bulan berjalan: input baru ≥ awal bulan; data bulan lalu tidak bisa diubah/dihapus
    public function test_keuangan_hanya_bulan_berjalan(): void
    {
        $listrik = $this->kategori();
        $pengeluaranService = app(PengeluaranService::class);
        $kasService = app(KasService::class);

        // Data Oktober (dibuat lewat service saat masih Oktober).
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $lama = $pengeluaranService->simpan(['id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran,
            'tanggal_pengeluaran' => '2026-10-20', 'deskripsi' => 'Listrik Oktober', 'jumlah' => '100000',
            'metode_pembayaran' => 'tunai'], null, $this->admin);
        $modalLama = $kasService->catat(KasService::MASUK, 'modal', '500000', [], $this->admin, now(), 'Modal Oktober');

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(15, 0));
        $this->actingAs($this->admin);

        // Input baru: tanggal sebelum 01/11 ditolak, 01/11 diterima.
        $data = ['id_kategori_pengeluaran' => $listrik->id_kategori_pengeluaran, 'deskripsi' => 'Token',
            'jumlah' => 50000, 'metode_pembayaran' => 'tunai'];
        $this->post(route('pengeluaran.store'), $data + ['tanggal_pengeluaran' => '2026-10-31'])
            ->assertSessionHasErrors(['tanggal_pengeluaran' => 'Tanggal pengeluaran harus di bulan berjalan (mulai 01/11/2026).']);
        $this->post(route('pengeluaran.store'), $data + ['tanggal_pengeluaran' => '2026-11-01'])->assertSessionHasNoErrors();
        $baru = PengeluaranOperasional::where('tanggal_pengeluaran', '2026-11-01')->sole();

        $this->post(route('kas.store'), ['kategori' => 'prive', 'tanggal' => '2026-10-31', 'jumlah' => 1000, 'keterangan' => 'X'])
            ->assertSessionHasErrors(['tanggal' => 'Tanggal modal/prive harus di bulan berjalan (mulai 01/11/2026).']);
        $this->post(route('kas.store'), ['kategori' => 'prive', 'tanggal' => '2026-11-01', 'jumlah' => 1000, 'keterangan' => 'Prive November'])
            ->assertSessionHasNoErrors();
        $priveBaru = Kas::where('kategori', 'prive')->sole();

        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-10-31', 'uang_fisik' => 0])
            ->assertSessionHasErrors(['tanggal' => 'Validasi kas hanya untuk tanggal di bulan berjalan (mulai 01/11/2026).']);
        $this->get(route('validasi-kas.create', ['tanggal' => '2026-10-31']))->assertSessionHasErrors('tanggal');
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-01', 'uang_fisik' => 0])->assertSessionHasNoErrors();

        // Pengeluaran Oktober: edit, ubah, dan hapus ditolak; data dan kasnya tetap.
        $this->get(route('pengeluaran.edit', $lama))
            ->assertRedirect(route('pengeluaran.index'))->assertSessionHas('error', PengeluaranService::PESAN_BULAN_LALU);
        $this->put(route('pengeluaran.update', $lama), $data + ['tanggal_pengeluaran' => '2026-11-02'])
            ->assertSessionHas('error', PengeluaranService::PESAN_BULAN_LALU);
        $this->delete(route('pengeluaran.destroy', $lama))->assertSessionHas('error', PengeluaranService::PESAN_BULAN_LALU);
        $lama->refresh();
        $this->assertSame('2026-10-20', $lama->tanggal_pengeluaran->toDateString());
        $this->assertSame('100000.00', $lama->jumlah);
        $this->assertNotNull($lama->kas);

        // Modal Oktober tidak bisa dihapus dari buku kas.
        $this->delete(route('kas.destroy', $modalLama))->assertSessionHas('error', KasService::PESAN_BULAN_LALU);
        $this->assertNotNull($modalLama->fresh());

        // Service juga menolak (pertahanan kedua di luar controller).
        foreach ([fn () => $pengeluaranService->hapus($lama), fn () => $kasService->hapusManual($modalLama)] as $aksi) {
            try {
                $aksi();
                $this->fail('Data bulan lalu seharusnya ditolak.');
            } catch (ValidationException) {
            }
        }

        // Tombol: bulan lalu disembunyikan, bulan berjalan tetap ada.
        $this->get(route('pengeluaran.index'))->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee(route('pengeluaran.edit', $lama), false)
            ->assertDontSee(route('pengeluaran.destroy', $lama), false)
            ->assertSee(route('pengeluaran.edit', $baru), false)
            ->assertSee(route('pengeluaran.destroy', $baru), false);
        $this->get(route('kas.index', ['dari' => '2026-10-01', 'sampai' => '2026-11-24']))->assertOk()
            ->assertSee('Modal Oktober')
            ->assertDontSee(route('kas.destroy', $modalLama), false)
            ->assertSee(route('kas.destroy', $priveBaru), false);

        // Data bulan berjalan tetap bisa diubah dan dihapus.
        $this->put(route('pengeluaran.update', $baru), array_merge($data, ['tanggal_pengeluaran' => '2026-11-02', 'jumlah' => 60000]))
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame('60000.00', $baru->fresh()->jumlah);
        $this->delete(route('kas.destroy', $priveBaru))->assertSessionHas('success');
    }

    // 5. Kasir membuka halaman keuangan → 403
    public function test_kasir_tidak_bisa_membuka_halaman_keuangan(): void
    {
        $kategori = $this->kategori();
        $this->actingAs($this->kasir);

        foreach ([
            route('kategori-pengeluaran.index'), route('pengeluaran.index'), route('pengeluaran.create'),
            route('kas.index'), route('kas.create'), route('validasi-kas.index'), route('validasi-kas.create'),
            route('hpp.index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post(route('kas.store'), ['kategori' => 'prive', 'tanggal' => '2026-11-24', 'jumlah' => 1000, 'keterangan' => 'X'])->assertForbidden();
        $this->post(route('pengeluaran.store'), ['id_kategori_pengeluaran' => $kategori->id_kategori_pengeluaran])->assertForbidden();
        $this->post(route('validasi-kas.store'), ['tanggal' => '2026-11-24', 'uang_fisik' => 0])->assertForbidden();
        $this->post(route('hpp.hitung'), ['bulan' => '2026-11'])->assertForbidden();
        $this->assertSame(0, Kas::count());

        // Admin bisa membuka semua halaman.
        $this->actingAs($this->admin);
        foreach ([route('kategori-pengeluaran.index'), route('kategori-pengeluaran.create'), route('pengeluaran.index'),
            route('pengeluaran.create'), route('kas.index'), route('kas.create', ['kategori' => 'prive']),
            route('validasi-kas.index'), route('validasi-kas.create'), route('hpp.index')] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
