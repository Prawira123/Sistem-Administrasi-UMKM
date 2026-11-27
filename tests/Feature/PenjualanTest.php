<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\KartuStok;
use App\Models\Kas;
use App\Models\NotifikasiStok;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\PenjualanService;
use App\Services\ValidasiKasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenjualanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->kasir = User::factory()->kasir()->create();
    }

    private function jual(User $user, array $items, string $metode = 'tunai', $bayar = null)
    {
        return $this->actingAs($user)->post(route('penjualan.store'), array_filter([
            'items' => $items,
            'metode_pembayaran' => $metode,
            'jumlah_bayar' => $bayar,
        ], fn ($v) => $v !== null));
    }

    private function barang(int $stok, float $hpp, array $atribut = []): Barang
    {
        return Barang::factory()->stok($stok, $hpp)->create($atribut + ['harga_jual' => 5000, 'stok_minimum' => 2]);
    }

    // 1. Stok berkurang, kartu stok keluar tercatat, kas masuk sama dengan total
    public function test_penjualan_mengurangi_stok_mencatat_kartu_stok_dan_kas(): void
    {
        $teh = $this->barang(20, 3400);
        $air = $this->barang(10, 2500, ['harga_jual' => '3500.50']);

        $response = $this->jual($this->kasir, [
            ['id_barang' => $teh->id_barang, 'jumlah' => 3],
            ['id_barang' => $air->id_barang, 'jumlah' => 2],
        ], 'tunai', 30000);

        $penjualan = Penjualan::sole();
        $response->assertRedirect(route('penjualan.show', $penjualan))->assertSessionHasNoErrors();

        $this->assertSame(17, $teh->fresh()->stok);
        $this->assertSame(8, $air->fresh()->stok);

        // 3 × 5.000 + 2 × 3.500,50 = 22.001,00; HPP 3 × 3.400 + 2 × 2.500 = 15.200
        $this->assertSame(5, $penjualan->total_item);
        $this->assertSame('22001.00', $penjualan->total_harga);
        $this->assertSame('15200.00', $penjualan->total_hpp);
        $this->assertSame('selesai', $penjualan->status);
        $this->assertSame($this->kasir->id_user, $penjualan->id_user);

        $kartu = KartuStok::where('id_barang', $teh->id_barang)->sole();
        $this->assertSame('keluar', $kartu->jenis_mutasi);
        $this->assertSame($penjualan->id_penjualan, $kartu->id_penjualan);
        $this->assertNull($kartu->id_barang_masuk);
        $this->assertSame(0, $kartu->jumlah_masuk);
        $this->assertSame(3, $kartu->jumlah_keluar);
        $this->assertSame(20, $kartu->stok_sebelum);
        $this->assertSame(17, $kartu->stok_sesudah);
        $this->assertSame(2, KartuStok::count());

        $kas = Kas::sole();
        $this->assertSame('masuk', $kas->jenis);
        $this->assertSame('penjualan', $kas->kategori);
        $this->assertSame($penjualan->id_penjualan, $kas->id_penjualan);
        $this->assertSame($penjualan->total_harga, $kas->jumlah);

        $this->get(route('penjualan.show', $penjualan))->assertOk()->assertSee($penjualan->no_transaksi);
        $this->get(route('penjualan.nota', [$penjualan, 'ukuran' => 58]))->assertOk()
            ->assertSee('size: 58mm auto', false)->assertSee('Rp22.001');
        $this->get(route('penjualan.index'))->assertOk()->assertSee($penjualan->no_transaksi);
    }

    // 2. Harga jual dan HPP tersalin ke detail; perubahan harga barang setelahnya tidak mengubah detail lama
    public function test_harga_jual_dan_hpp_tersalin_ke_detail(): void
    {
        $barang = $this->barang(10, 3100, ['harga_jual' => 4000]);

        $this->jual($this->kasir, [['id_barang' => $barang->id_barang, 'jumlah' => 2]], 'qris')
            ->assertSessionHasNoErrors();

        $barang->update(['harga_jual' => 4500]);
        $barang->hpp_rata_rata = '3600.00';
        $barang->save();

        $detail = DetailPenjualan::sole();
        $this->assertSame('4000.00', $detail->harga_jual);
        $this->assertSame('3100.00', $detail->hpp_satuan);
        $this->assertSame('8000.00', $detail->subtotal);
        $this->assertSame('6200.00', $detail->subtotal_hpp);
        $this->assertSame('8000.00', Penjualan::sole()->total_harga);
    }

    // 3. Jumlah melebihi stok ditolak dan tidak ada data yang tersimpan
    public function test_jumlah_melebihi_stok_ditolak_tanpa_menyimpan_apa_pun(): void
    {
        $aman = $this->barang(10, 1000);
        $kurang = $this->barang(3, 1000, ['nama_barang' => 'Beras 5kg']);

        $this->jual($this->kasir, [
            ['id_barang' => $aman->id_barang, 'jumlah' => 2],
            ['id_barang' => $kurang->id_barang, 'jumlah' => 4],
        ], 'tunai', 100000)->assertSessionHasErrors(['items' => 'Stok Beras 5kg tidak cukup (sisa 3).']);

        $this->assertSame(0, Penjualan::count());
        $this->assertSame(0, DetailPenjualan::count());
        $this->assertSame(0, KartuStok::count());
        $this->assertSame(0, Kas::count());
        $this->assertSame(0, NotifikasiStok::count());
        $this->assertSame(10, $aman->fresh()->stok);
        $this->assertSame(3, $kurang->fresh()->stok);
    }

    // Barang dengan harga jual Rp0 tidak bisa dijual dan tidak muncul di pencarian kasir
    public function test_barang_harga_jual_nol_ditolak_dan_tidak_muncul_di_pencarian(): void
    {
        $normal = $this->barang(10, 1000, ['nama_barang' => 'Kopi Sachet']);
        $gratis = $this->barang(10, 1000, ['nama_barang' => 'Kopi Bubuk', 'harga_jual' => 0]);

        $this->jual($this->kasir, [
            ['id_barang' => $normal->id_barang, 'jumlah' => 1],
            ['id_barang' => $gratis->id_barang, 'jumlah' => 1],
        ], 'tunai', 100000)->assertSessionHasErrors(['items' => 'Harga jual Kopi Bubuk belum diatur (Rp0) sehingga tidak bisa dijual.']);

        $this->assertSame(0, Penjualan::count());
        $this->assertSame(0, KartuStok::count());
        $this->assertSame(0, Kas::count());
        $this->assertSame(10, $normal->fresh()->stok);
        $this->assertSame(10, $gratis->fresh()->stok);

        $nama = collect($this->getJson(route('barang.cari', ['q' => 'kopi']))->assertOk()->json('data'))->pluck('nama');
        $this->assertSame(['Kopi Sachet'], $nama->all());
    }

    // 4. Tunai: bayar kurang ditolak; kembalian benar
    public function test_tunai_bayar_kurang_ditolak_dan_kembalian_benar(): void
    {
        $barang = $this->barang(10, 1000, ['harga_jual' => 7500]);
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 2]]; // 15.000

        $this->jual($this->kasir, $item, 'tunai', 14999)
            ->assertSessionHasErrors(['jumlah_bayar' => 'Jumlah bayar kurang dari total belanja (Rp15.000).']);
        $this->jual($this->kasir, $item, 'tunai')->assertSessionHasErrors('jumlah_bayar');
        $this->assertSame(0, Penjualan::count());
        $this->assertSame(10, $barang->fresh()->stok);

        $this->jual($this->kasir, $item, 'tunai', 20000)->assertSessionHasNoErrors();
        $tunai = Penjualan::sole();
        $this->assertSame('20000.00', $tunai->jumlah_bayar);
        $this->assertSame('5000.00', $tunai->kembalian);

        // Non-tunai: jumlah_bayar = total, kembalian 0, meskipun browser mengirim angka lain.
        $this->jual($this->kasir, $item, 'transfer', 99999)->assertSessionHasNoErrors();
        $transfer = Penjualan::latest('id_penjualan')->first();
        $this->assertSame('15000.00', $transfer->jumlah_bayar);
        $this->assertSame('0.00', $transfer->kembalian);
    }

    // 5. Notifikasi dibuat saat stok mencapai minimum, dan tidak dobel pada penjualan berikutnya
    public function test_notifikasi_dibuat_saat_stok_mencapai_minimum_dan_tidak_dobel(): void
    {
        $barang = $this->barang(8, 1000, ['nama_barang' => 'Pulpen Hitam', 'satuan' => 'pcs', 'stok_minimum' => 5]);
        $item = fn (int $jumlah) => [['id_barang' => $barang->id_barang, 'jumlah' => $jumlah]];

        $this->jual($this->kasir, $item(2), 'qris'); // sisa 6 > 5
        $this->assertSame(0, NotifikasiStok::count());

        $this->jual($this->kasir, $item(1), 'qris'); // sisa 5 = minimum
        $notif = NotifikasiStok::sole();
        $this->assertSame('baru', $notif->status);
        $this->assertSame(5, $notif->stok_saat_ini);
        $this->assertSame(5, $notif->stok_minimum);
        $this->assertSame('Stok Pulpen Hitam tinggal 5 pcs (minimum 5).', $notif->pesan);

        // Dibaca lewat lonceng, lalu dijual lagi: tetap satu notifikasi terbuka.
        $this->actingAs($this->admin)->post(route('notifikasi-stok.baca', $notif))->assertRedirect();
        $notif->refresh();
        $this->assertSame('dibaca', $notif->status);
        $this->assertSame($this->admin->id_user, $notif->dibaca_oleh);
        $this->assertNotNull($notif->dibaca_pada);

        $this->jual($this->kasir, $item(2), 'qris'); // sisa 3
        $this->assertSame(1, NotifikasiStok::count());
        $this->assertSame(3, $barang->fresh()->stok);
    }

    // 6. Admin membatalkan: stok kembali, kas terhapus, status batal. Kasir membatalkan → 403
    public function test_admin_membatalkan_penjualan_dan_kasir_ditolak(): void
    {
        $barang = $this->barang(6, 2000, ['stok_minimum' => 5]);
        $this->jual($this->kasir, [['id_barang' => $barang->id_barang, 'jumlah' => 4]], 'tunai', 20000);
        $penjualan = Penjualan::sole();
        $this->assertSame(2, $barang->fresh()->stok);
        $this->assertSame(1, NotifikasiStok::where('status', 'baru')->count());

        // Kasir → 403, tidak ada yang berubah.
        $this->actingAs($this->kasir)->post(route('penjualan.batal', $penjualan), ['alasan' => 'Salah input barang'])
            ->assertForbidden();
        $this->actingAs($this->kasir)->get(route('penjualan.show', $penjualan))->assertOk()->assertDontSee('Ya, batalkan');
        $this->assertSame('selesai', $penjualan->fresh()->status);

        // Alasan wajib.
        $this->actingAs($this->admin)->post(route('penjualan.batal', $penjualan), ['alasan' => ''])
            ->assertSessionHasErrors('alasan');
        $this->assertSame('selesai', $penjualan->fresh()->status);

        $this->actingAs($this->admin)->post(route('penjualan.batal', $penjualan), ['alasan' => 'Pelanggan membatalkan pembelian'])
            ->assertRedirect(route('penjualan.show', $penjualan))->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame('batal', $penjualan->status);
        $this->assertSame('Pelanggan membatalkan pembelian', $penjualan->keterangan);
        $this->assertSame(6, $barang->fresh()->stok);
        $this->assertSame('2000.00', $barang->fresh()->hpp_rata_rata); // HPP tidak berubah
        $this->assertSame(0, Kas::count());

        $kartu = KartuStok::where('jenis_mutasi', 'penyesuaian')->sole();
        $this->assertSame($penjualan->id_penjualan, $kartu->id_penjualan);
        $this->assertSame(4, $kartu->jumlah_masuk);
        $this->assertSame(2, $kartu->stok_sebelum);
        $this->assertSame(6, $kartu->stok_sesudah);
        $this->assertSame("Pembatalan {$penjualan->no_transaksi}", $kartu->keterangan);

        // Stok 6 > minimum 5 → notifikasi ditutup.
        $this->assertSame('selesai', NotifikasiStok::sole()->status);

        // Tidak bisa dibatalkan dua kali.
        $this->actingAs($this->admin)->post(route('penjualan.batal', $penjualan), ['alasan' => 'Dobel klik'])
            ->assertSessionHasErrors('alasan');
        $this->assertSame(6, $barang->fresh()->stok);
        $this->assertSame(1, KartuStok::where('jenis_mutasi', 'penyesuaian')->count());
    }

    // 7. Nomor transaksi berurutan per hari
    public function test_nomor_transaksi_berurutan_per_hari(): void
    {
        $barang = $this->barang(50, 1000);
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 1]];

        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(20, 0));
        $this->jual($this->kasir, $item, 'qris');
        $this->jual($this->kasir, $item, 'qris');

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(8, 0));
        $this->jual($this->kasir, $item, 'qris');

        $this->assertSame([
            'TRX-20261123-0001',
            'TRX-20261123-0002',
            'TRX-20261124-0001',
        ], Penjualan::orderBy('id_penjualan')->pluck('no_transaksi')->all());
    }

    public function test_kasir_hanya_melihat_penjualan_miliknya_admin_melihat_semua(): void
    {
        $kasirLain = User::factory()->kasir()->create();
        $barang = $this->barang(20, 1000);
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 1]];

        $this->jual($this->kasir, $item, 'qris');
        $this->jual($kasirLain, $item, 'qris');
        [$milikSendiri, $milikLain] = Penjualan::orderBy('id_penjualan')->get()->all();
        $this->flushSession(); // buang flash "TRX-... berhasil disimpan" dari transaksi terakhir

        // Kasir: riwayat hanya miliknya, parameter ?kasir= milik orang lain diabaikan.
        $this->actingAs($this->kasir)->get(route('penjualan.index'))->assertOk()
            ->assertSee($milikSendiri->no_transaksi)->assertDontSee($milikLain->no_transaksi);
        $this->get(route('penjualan.index', ['kasir' => $kasirLain->id_user]))->assertOk()
            ->assertSee($milikSendiri->no_transaksi)->assertDontSee($milikLain->no_transaksi);

        // Detail dan nota milik kasir lain → 403.
        $this->get(route('penjualan.show', $milikSendiri))->assertOk();
        $this->get(route('penjualan.nota', $milikSendiri))->assertOk();
        $this->get(route('penjualan.show', $milikLain))->assertForbidden();
        $this->get(route('penjualan.nota', $milikLain))->assertForbidden();

        // Administrator: semua transaksi, bisa difilter per kasir.
        $this->actingAs($this->admin)->get(route('penjualan.index'))->assertOk()
            ->assertSee($milikSendiri->no_transaksi)->assertSee($milikLain->no_transaksi);
        $this->get(route('penjualan.index', ['kasir' => $kasirLain->id_user]))->assertOk()
            ->assertDontSee($milikSendiri->no_transaksi)->assertSee($milikLain->no_transaksi);
        $this->get(route('penjualan.show', $milikLain))->assertOk();
        $this->get(route('penjualan.nota', $milikLain))->assertOk();
    }

    public function test_pembatalan_penjualan_tunai_pada_tanggal_yang_kasnya_sudah_divalidasi_ditolak(): void
    {
        $barang = $this->barang(20, 1000);
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 2]]; // 2 × 5.000 = 10.000

        // 20/11: satu penjualan tunai dan satu QRIS, lalu kas tanggal itu divalidasi (tunai 10.000 sesuai).
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        $this->jual($this->kasir, $item, 'tunai', 10000);
        $tunai = Penjualan::latest('id_penjualan')->first();
        $this->jual($this->kasir, $item, 'qris');
        $qris = Penjualan::latest('id_penjualan')->first();
        app(ValidasiKasService::class)->simpan('2026-11-20', '10000', null, $this->admin);

        // 21/11: penjualan tunai pada tanggal yang belum divalidasi.
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(10, 0));
        $this->jual($this->kasir, $item, 'tunai', 10000);
        $tunaiBelumValidasi = Penjualan::latest('id_penjualan')->first();

        // Tombol batal disembunyikan dan pesan tampil; QRIS di tanggal yang sama tetap bisa dibatalkan.
        $this->actingAs($this->admin)->get(route('penjualan.show', $tunai))->assertOk()
            ->assertDontSee('Ya, batalkan')->assertSee(PenjualanService::PESAN_KAS_DIVALIDASI);
        $this->get(route('penjualan.show', $qris))->assertOk()->assertSee('Ya, batalkan');

        // Request langsung tetap ditolak di service; stok, kas, dan kartu stok tidak berubah.
        $this->post(route('penjualan.batal', $tunai), ['alasan' => 'Salah input barang'])
            ->assertSessionHasErrors(['alasan' => PenjualanService::PESAN_KAS_DIVALIDASI]);
        $this->assertSame('selesai', $tunai->fresh()->status);
        $this->assertSame(14, $barang->fresh()->stok);
        $this->assertSame(3, Kas::where('kategori', 'penjualan')->count());
        $this->assertSame(0, KartuStok::where('jenis_mutasi', 'penyesuaian')->count());

        // QRIS di tanggal tervalidasi dan tunai di tanggal yang belum divalidasi masih bisa dibatalkan.
        $this->post(route('penjualan.batal', $qris), ['alasan' => 'Salah scan'])->assertSessionHasNoErrors();
        $this->post(route('penjualan.batal', $tunaiBelumValidasi), ['alasan' => 'Salah scan'])->assertSessionHasNoErrors();
        $this->assertSame('batal', $qris->fresh()->status);
        $this->assertSame('batal', $tunaiBelumValidasi->fresh()->status);
        $this->assertSame(18, $barang->fresh()->stok);
    }

    public function test_pembatalan_transaksi_bulan_lalu_ditolak(): void
    {
        $barang = $this->barang(20, 1000);
        $item = [['id_barang' => $barang->id_barang, 'jumlah' => 2]];

        // Transaksi 31/10/2026 malam, lalu dibuka pada 01/11/2026.
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(23, 0));
        $this->jual($this->kasir, $item, 'qris');
        $bulanLalu = Penjualan::sole();

        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 0));
        $this->jual($this->kasir, $item, 'qris');
        $bulanIni = Penjualan::latest('id_penjualan')->first();

        // Tombol batal disembunyikan untuk transaksi bulan lalu, tetap ada untuk bulan berjalan.
        $this->actingAs($this->admin)->get(route('penjualan.show', $bulanLalu))->assertOk()
            ->assertDontSee('Ya, batalkan')->assertSee(PenjualanService::PESAN_BULAN_LALU);
        $this->get(route('penjualan.show', $bulanIni))->assertOk()->assertSee('Ya, batalkan');

        // Request langsung tetap ditolak di service; tidak ada yang berubah.
        $this->post(route('penjualan.batal', $bulanLalu), ['alasan' => 'Salah input barang'])
            ->assertSessionHasErrors(['alasan' => PenjualanService::PESAN_BULAN_LALU]);
        $this->assertSame('selesai', $bulanLalu->fresh()->status);
        $this->assertSame(16, $barang->fresh()->stok);
        $this->assertSame(2, Kas::count());
        $this->assertSame(0, KartuStok::where('jenis_mutasi', 'penyesuaian')->count());

        // Transaksi bulan berjalan masih bisa dibatalkan.
        $this->post(route('penjualan.batal', $bulanIni), ['alasan' => 'Salah input barang'])->assertSessionHasNoErrors();
        $this->assertSame('batal', $bulanIni->fresh()->status);
        $this->assertSame(18, $barang->fresh()->stok);
    }

    public function test_endpoint_cari_barang_hanya_aktif_dan_stok_tersedia(): void
    {
        $tepat = $this->barang(5, 1000, ['kode_barang' => '8991234567890', 'nama_barang' => 'Teh Melati']);
        $this->barang(5, 1000, ['kode_barang' => 'BRG-0100', 'nama_barang' => 'Teh Hijau 8991234567890']);
        $this->barang(0, 1000, ['kode_barang' => 'BRG-0101', 'nama_barang' => 'Teh Habis']);
        $this->barang(5, 1000, ['kode_barang' => 'BRG-0102', 'nama_barang' => 'Teh Nonaktif', 'status' => 'nonaktif']);

        $hasil = $this->actingAs($this->kasir)->getJson(route('barang.cari', ['q' => '8991234567890']))->assertOk()->json('data');
        $this->assertCount(2, $hasil);
        $this->assertSame($tepat->id_barang, $hasil[0]['id']);
        $this->assertTrue($hasil[0]['tepat']);
        $this->assertFalse($hasil[1]['tepat']);
        $this->assertArrayNotHasKey('hpp_rata_rata', $hasil[0]);

        $nama = $this->getJson(route('barang.cari', ['q' => 'teh']))->json('data');
        $this->assertEqualsCanonicalizing(['Teh Melati', 'Teh Hijau 8991234567890'], array_column($nama, 'nama'));
    }

    public function test_halaman_kasir_dan_lonceng_notifikasi(): void
    {
        $barang = $this->barang(1, 1000, ['nama_barang' => 'Beras 5kg', 'stok_minimum' => 5]);
        NotifikasiStok::create([
            'id_barang' => $barang->id_barang, 'stok_saat_ini' => 1, 'stok_minimum' => 5,
            'pesan' => 'Stok Beras 5kg tinggal 1 dus (minimum 5).', 'status' => 'baru',
        ]);

        $this->actingAs($this->kasir)->get(route('penjualan.create'))->assertOk()
            ->assertSee('Simpan Transaksi')
            ->assertSee('Stok Beras 5kg tinggal 1 dus (minimum 5).')
            ->assertSee('data-jumlah-notifikasi', false);

        $this->post(route('notifikasi-stok.baca-semua'))->assertRedirect();
        $this->assertSame('dibaca', NotifikasiStok::sole()->status);
        $this->assertSame($this->kasir->id_user, NotifikasiStok::sole()->dibaca_oleh);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-jumlah-notifikasi', false);
    }
}
