<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\BarangMasuk;
use App\Models\Kas;
use App\Models\KartuStok;
use App\Models\NotifikasiStok;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BarangMasukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangMasukTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->kasir = User::factory()->kasir()->create();
        $this->supplier = Supplier::factory()->create();
    }

    private function kirim(User $user, array $items, array $tambahan = [])
    {
        return $this->actingAs($user)->post(route('barang-masuk.store'), $tambahan + [
            'id_supplier' => $this->supplier->id_supplier,
            'tanggal_masuk' => now()->toDateString(),
            'no_faktur' => 'F-001',
            'metode_pembayaran' => 'tunai',
            'items' => $items,
        ]);
    }

    // 1. Stok bertambah, HPP sesuai contoh (10 @ 3.000 + 20 @ 3.600 → 3.400), harga_beli_terakhir terisi
    public function test_simpan_barang_masuk_menambah_stok_dan_menghitung_hpp_rata_rata(): void
    {
        $barang = Barang::factory()->stok(10, 3000)->create();

        $response = $this->kirim($this->admin, [
            ['id_barang' => $barang->id_barang, 'jumlah' => 20, 'harga_beli' => 3600],
        ]);

        $barangMasuk = BarangMasuk::firstOrFail();
        $response->assertRedirect(route('barang-masuk.show', $barangMasuk))->assertSessionHasNoErrors();

        $barang->refresh();
        $this->assertSame(30, $barang->stok);
        $this->assertSame('3400.00', $barang->hpp_rata_rata);
        $this->assertSame('3600.00', $barang->harga_beli_terakhir);

        $this->assertMatchesRegularExpression('/^BM-'.now()->format('Ymd').'-001$/', $barangMasuk->kode_barang_masuk);
        $this->assertSame('72000.00', $barangMasuk->total_pembelian);
        $this->assertSame('72000.00', $barangMasuk->detail()->first()->subtotal);

        $this->get(route('barang-masuk.show', $barangMasuk))->assertOk()->assertSee('tidak bisa diedit');
    }

    // 2. Kartu stok jenis masuk dengan stok sebelum/sesudah benar
    public function test_kartu_stok_jenis_masuk_tercatat(): void
    {
        $barang = Barang::factory()->stok(10, 3000)->create();

        $this->kirim($this->admin, [['id_barang' => $barang->id_barang, 'jumlah' => 20, 'harga_beli' => 3600]]);

        $kartu = KartuStok::sole();
        $this->assertSame('masuk', $kartu->jenis_mutasi);
        $this->assertSame(BarangMasuk::first()->id_barang_masuk, $kartu->id_barang_masuk);
        $this->assertNull($kartu->id_penjualan);
        $this->assertSame(20, $kartu->jumlah_masuk);
        $this->assertSame(0, $kartu->jumlah_keluar);
        $this->assertSame(10, $kartu->stok_sebelum);
        $this->assertSame(30, $kartu->stok_sesudah);
        $this->assertSame('3400.00', $kartu->hpp_satuan);
        $this->assertSame($this->admin->id_user, $kartu->id_user);
    }

    // 3. Kas keluar kategori pembelian_barang sama dengan total
    public function test_kas_keluar_pembelian_barang_sama_dengan_total(): void
    {
        $a = Barang::factory()->create();
        $b = Barang::factory()->create();

        $this->kirim($this->admin, [
            ['id_barang' => $a->id_barang, 'jumlah' => 3, 'harga_beli' => '1250.50'],
            ['id_barang' => $b->id_barang, 'jumlah' => 2, 'harga_beli' => 10000],
        ]);

        $barangMasuk = BarangMasuk::firstOrFail();
        $this->assertSame('23751.50', $barangMasuk->total_pembelian); // 3.751,50 + 20.000

        $kas = Kas::sole();
        $this->assertSame('keluar', $kas->jenis);
        $this->assertSame('pembelian_barang', $kas->kategori);
        $this->assertSame($barangMasuk->id_barang_masuk, $kas->id_barang_masuk);
        $this->assertSame($barangMasuk->total_pembelian, $kas->jumlah);
        $this->assertSame(2, KartuStok::count());
    }

    // 4. Notifikasi terbuka menjadi selesai bila stok naik di atas minimum
    public function test_notifikasi_stok_minimum_ditutup_bila_stok_di_atas_minimum(): void
    {
        $barang = Barang::factory()->stok(2, 1000)->create(['stok_minimum' => 5]);
        $lain = Barang::factory()->stok(1, 1000)->create(['stok_minimum' => 5]);

        $notifBaru = $this->buatNotifikasi($barang, 'baru');
        $notifDibaca = $this->buatNotifikasi($barang, 'dibaca');
        $notifLain = $this->buatNotifikasi($lain, 'baru');

        $this->kirim($this->admin, [
            ['id_barang' => $barang->id_barang, 'jumlah' => 10, 'harga_beli' => 1000], // 12 > 5
            ['id_barang' => $lain->id_barang, 'jumlah' => 2, 'harga_beli' => 1000],   // 3 <= 5
        ])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $notifBaru->fresh()->status);
        $this->assertSame('selesai', $notifDibaca->fresh()->status);
        $this->assertSame('baru', $notifLain->fresh()->status);
    }

    // 5. Validasi: tanpa item, jumlah 0, supplier nonaktif ditolak
    public function test_validasi_tanpa_item_jumlah_nol_dan_supplier_nonaktif_ditolak(): void
    {
        $barang = Barang::factory()->stok(5, 1000)->create();

        $this->kirim($this->admin, [])->assertSessionHasErrors('items');

        $this->kirim($this->admin, [['id_barang' => $barang->id_barang, 'jumlah' => 0, 'harga_beli' => 1000]])
            ->assertSessionHasErrors('items.0.jumlah');

        $nonaktif = Supplier::factory()->nonaktif()->create();
        $this->kirim($this->admin, [['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 1000]],
            ['id_supplier' => $nonaktif->id_supplier])
            ->assertSessionHasErrors('id_supplier');

        $this->kirim($this->admin, [
            ['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 1000],
            ['id_barang' => $barang->id_barang, 'jumlah' => 2, 'harga_beli' => 1000],
        ])->assertSessionHasErrors('items.1.id_barang');

        $this->assertSame(0, BarangMasuk::count());
        $this->assertSame(0, KartuStok::count());
        $this->assertSame(0, Kas::count());
        $this->assertSame(5, $barang->fresh()->stok);
    }

    // 5b. Service juga menolak supplier nonaktif dan tidak meninggalkan data setengah jadi
    public function test_service_menolak_supplier_nonaktif_tanpa_menyimpan_apa_pun(): void
    {
        $barang = Barang::factory()->create();
        $nonaktif = Supplier::factory()->nonaktif()->create();

        try {
            app(BarangMasukService::class)->simpan([
                'id_supplier' => $nonaktif->id_supplier,
                'tanggal_masuk' => now()->toDateString(),
                'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => '1000']],
            ], $this->admin);
            $this->fail('Seharusnya ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('id_supplier', $e->errors());
        }

        $this->assertSame(0, BarangMasuk::count());
    }

    // 6. Kasir boleh mencatat barang masuk
    public function test_kasir_boleh_mencatat_barang_masuk(): void
    {
        $barang = Barang::factory()->create();

        $this->actingAs($this->kasir)->get(route('barang-masuk.create'))->assertOk();
        $this->kirim($this->kasir, [['id_barang' => $barang->id_barang, 'jumlah' => 6, 'harga_beli' => 2000]])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $barang->fresh()->stok);
        $this->assertSame($this->kasir->id_user, BarangMasuk::sole()->id_user);
        $this->get(route('barang-masuk.index'))->assertOk()->assertSee(BarangMasuk::sole()->kode_barang_masuk);
    }

    public function test_tanggal_masuk_bulan_lalu_dan_besok_ditolak(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));
        $barang = Barang::factory()->create();
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 1000]];

        // Bulan lalu (31/10/2026) ditolak
        $this->kirim($this->admin, $item, ['tanggal_masuk' => '2026-10-31'])
            ->assertSessionHasErrors(['tanggal_masuk' => 'Tanggal masuk harus di bulan berjalan (mulai 01/11/2026). Barang masuk bulan lalu tidak bisa dicatat lagi.']);

        // Besok (25/11/2026) ditolak
        $this->kirim($this->admin, $item, ['tanggal_masuk' => '2026-11-25'])
            ->assertSessionHasErrors(['tanggal_masuk' => 'Tanggal masuk tidak boleh melebihi hari ini.']);

        $this->assertSame(0, BarangMasuk::count());
        $this->assertSame(0, $barang->fresh()->stok);

        // Batas yang masih diterima: tanggal 1 bulan berjalan dan hari ini
        $this->kirim($this->admin, $item, ['tanggal_masuk' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->kirim($this->admin, $item, ['tanggal_masuk' => '2026-11-24'])->assertSessionHasNoErrors();
        $this->assertSame(2, BarangMasuk::count());
    }

    public function test_nomor_barang_masuk_berurutan_per_tanggal(): void
    {
        // Pertengahan bulan, agar "kemarin" selalu masih di bulan berjalan.
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(10, 0));
        $barang = Barang::factory()->create();
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 1000]];

        $this->kirim($this->admin, $item);
        $this->kirim($this->admin, $item);
        $this->kirim($this->admin, $item, ['tanggal_masuk' => now()->subDay()->toDateString()]);

        $this->assertEqualsCanonicalizing([
            'BM-'.now()->format('Ymd').'-001',
            'BM-'.now()->format('Ymd').'-002',
            'BM-'.now()->subDay()->format('Ymd').'-001',
        ], BarangMasuk::pluck('kode_barang_masuk')->all());
    }

    public function test_seeder_contoh_transaksi_mengisi_stok_lewat_service(): void
    {
        $this->seed();

        $teh = Barang::where('kode_barang', 'BRG-0001')->firstOrFail();
        $this->assertSame(48, $teh->stok);
        $this->assertSame('3100.00', $teh->hpp_rata_rata); // (24×3.000 + 24×3.200) ÷ 48
        // Stok akhir = Σ masuk − Σ keluar (seeder juga berisi contoh penjualan modul 5).
        $this->assertSame((int) Barang::sum('stok'), (int) KartuStok::sum('jumlah_masuk') - (int) KartuStok::sum('jumlah_keluar'));
        $this->assertSame(0, KartuStok::where('jenis_mutasi', 'masuk')->whereNull('id_barang_masuk')->count());
        $this->assertSame(0, Kas::where('kategori', 'pembelian_barang')->whereNull('id_barang_masuk')->count());
        $this->assertSame(2, Kas::where('kategori', 'pembelian_barang')->count());
    }

    private function buatNotifikasi(Barang $barang, string $status): NotifikasiStok
    {
        return NotifikasiStok::create([
            'id_barang' => $barang->id_barang,
            'stok_saat_ini' => $barang->stok,
            'stok_minimum' => $barang->stok_minimum,
            'pesan' => "Stok {$barang->nama_barang} menipis.",
            'status' => $status,
        ]);
    }
}
