<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataTest extends TestCase
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

    // 1. Admin bisa membuat kategori, supplier, barang, pengguna
    public function test_admin_bisa_membuat_kategori_supplier_barang_dan_pengguna(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('kategori-barang.store'), ['nama_kategori' => 'Minuman', 'keterangan' => 'Teh, kopi'])
            ->assertRedirect(route('kategori-barang.index'))
            ->assertSessionHasNoErrors();
        $kategori = KategoriBarang::where('nama_kategori', 'Minuman')->firstOrFail();

        $this->post(route('supplier.store'), ['nama_supplier' => 'CV Mitra', 'status' => 'aktif'])
            ->assertRedirect(route('supplier.index'));
        $this->post(route('supplier.store'), ['nama_supplier' => 'UD Kedua', 'status' => 'aktif']);
        $this->assertSame(['SUP-001', 'SUP-002'], Supplier::orderBy('kode_supplier')->pluck('kode_supplier')->all());

        $this->post(route('barang.store'), [
            'kode_barang' => '',
            'id_kategori' => $kategori->id_kategori,
            'nama_barang' => 'Teh Botol',
            'satuan' => 'botol',
            'harga_jual' => 4000,
            'stok_minimum' => 10,
            'status' => 'aktif',
        ])->assertRedirect(route('barang.index'))->assertSessionHasNoErrors();

        $barang = Barang::where('nama_barang', 'Teh Botol')->firstOrFail();
        $this->assertSame('BRG-0001', $barang->kode_barang);
        $this->assertSame('4000.00', $barang->harga_jual);
        $this->assertSame(0, $barang->stok);
        $this->assertSame('0.00', $barang->hpp_rata_rata);

        $this->post(route('pengguna.store'), [
            'nama' => 'Kasir Baru',
            'username' => 'kasir2',
            'email' => '',
            'role' => 'kasir',
            'status' => 'aktif',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('pengguna.index'))->assertSessionHasNoErrors();

        $baru = User::where('username', 'kasir2')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia123', $baru->password));

        foreach (['kategori-barang.index', 'supplier.index', 'barang.index', 'pengguna.index', 'dashboard'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    // 2. Kasir bisa menambah barang, tapi harga_jual yang dikirim diabaikan (tetap 0)
    public function test_kasir_menambah_barang_harga_jual_diabaikan(): void
    {
        $kategori = KategoriBarang::factory()->create();

        $this->actingAs($this->kasir)->post(route('barang.store'), [
            'kode_barang' => '8991234567890',
            'id_kategori' => $kategori->id_kategori,
            'nama_barang' => 'Roti Sobek',
            'satuan' => 'bungkus',
            'harga_jual' => 15000,
            'stok_minimum' => 5,
            'status' => 'aktif',
        ])->assertRedirect(route('barang.index'))->assertSessionHasNoErrors();

        $barang = Barang::where('kode_barang', '8991234567890')->firstOrFail();
        $this->assertSame('0.00', $barang->harga_jual);

        // Admin melihat tanda "Harga belum diatur".
        $this->actingAs($this->admin)->get(route('barang.index'))->assertSee('Harga belum diatur');
    }

    // 3. Kasir mengubah barang: harga_jual tidak berubah
    public function test_kasir_mengubah_barang_harga_jual_tidak_berubah(): void
    {
        $barang = Barang::factory()->create(['harga_jual' => 5000, 'nama_barang' => 'Lama']);

        $this->actingAs($this->kasir)->put(route('barang.update', $barang), [
            'kode_barang' => $barang->kode_barang,
            'id_kategori' => $barang->id_kategori,
            'nama_barang' => 'Nama Baru',
            'satuan' => 'pcs',
            'harga_jual' => 1,
            'stok_minimum' => 3,
            'status' => 'aktif',
        ])->assertRedirect(route('barang.index'))->assertSessionHasNoErrors();

        $barang->refresh();
        $this->assertSame('Nama Baru', $barang->nama_barang);
        $this->assertSame(3, $barang->stok_minimum);
        $this->assertSame('5000.00', $barang->harga_jual);
    }

    // Status barang hanya bisa diatur oleh pengguna dengan gate hapus-barang (Administrator)
    public function test_status_barang_hanya_diatur_administrator(): void
    {
        $kategori = KategoriBarang::factory()->create();
        $data = fn (array $ubah = []) => $ubah + ['id_kategori' => $kategori->id_kategori, 'nama_barang' => 'Teh Botol',
            'satuan' => 'botol', 'stok_minimum' => 2, 'harga_jual' => 4000];

        // Kasir menambah barang dengan status nonaktif → status diabaikan, barang tetap aktif (default kolom).
        $this->actingAs($this->kasir)->post(route('barang.store'), $data(['kode_barang' => 'TEH-01', 'status' => 'nonaktif']))
            ->assertSessionHasNoErrors();
        $dariKasir = Barang::where('kode_barang', 'TEH-01')->sole();
        $this->assertSame('aktif', $dariKasir->status);

        // Kasir mengubah barang: status yang dikirim diabaikan, status lama tetap (aktif maupun nonaktif).
        $this->put(route('barang.update', $dariKasir), $data(['kode_barang' => 'TEH-01', 'status' => 'nonaktif']))
            ->assertSessionHasNoErrors();
        $this->assertSame('aktif', $dariKasir->fresh()->status);

        $nonaktif = Barang::factory()->create(['status' => 'nonaktif', 'id_kategori' => $kategori->id_kategori]);
        $this->put(route('barang.update', $nonaktif), $data(['kode_barang' => $nonaktif->kode_barang, 'status' => 'aktif']))
            ->assertSessionHasNoErrors();
        $this->assertSame('nonaktif', $nonaktif->fresh()->status);

        // Form kasir tidak menampilkan pilihan status.
        $this->get(route('barang.edit', $dariKasir))->assertOk()
            ->assertDontSee('name="status"', false)->assertSee('Status barang hanya dapat diubah oleh Administrator.');

        // Administrator: status wajib dan bisa diubah.
        $this->actingAs($this->admin)->put(route('barang.update', $dariKasir), $data(['kode_barang' => 'TEH-01']))
            ->assertSessionHasErrors('status');
        $this->put(route('barang.update', $dariKasir), $data(['kode_barang' => 'TEH-01', 'status' => 'nonaktif']))
            ->assertSessionHasNoErrors();
        $this->assertSame('nonaktif', $dariKasir->fresh()->status);
        $this->get(route('barang.edit', $dariKasir))->assertOk()->assertSee('name="status"', false);
    }

    // 4. Kasir tidak bisa menghapus barang (403) dan tidak bisa membuka halaman supplier (403)
    public function test_kasir_tidak_bisa_menghapus_barang_dan_membuka_supplier(): void
    {
        $barang = Barang::factory()->create();

        $this->actingAs($this->kasir);

        $this->delete(route('barang.destroy', $barang))->assertForbidden();
        $this->assertModelExists($barang);

        $this->get(route('supplier.index'))->assertForbidden();
        $this->get(route('kategori-barang.index'))->assertForbidden();
        $this->get(route('pengguna.index'))->assertForbidden();

        // Halaman barang tetap bisa dibuka kasir, tanpa tombol hapus dan tanpa input harga jual.
        $this->get(route('barang.index'))->assertOk()->assertDontSee('Hapus data?');
        $this->get(route('barang.edit', $barang))->assertOk()->assertDontSee('name="harga_jual"', false);
    }

    // 5. Mengirim stok lewat form barang tidak mengubah stok
    public function test_mengirim_stok_lewat_form_tidak_mengubah_stok(): void
    {
        $kategori = KategoriBarang::factory()->create();
        $barang = Barang::factory()->stok(20, 3000)->create(['id_kategori' => $kategori->id_kategori]);

        $this->actingAs($this->admin)->put(route('barang.update', $barang), [
            'kode_barang' => $barang->kode_barang,
            'id_kategori' => $kategori->id_kategori,
            'nama_barang' => $barang->nama_barang,
            'satuan' => 'pcs',
            'harga_jual' => 4500,
            'stok_minimum' => 5,
            'status' => 'aktif',
            'stok' => 999,
            'hpp_rata_rata' => 1,
            'harga_beli_terakhir' => 1,
        ])->assertSessionHasNoErrors();

        $barang->refresh();
        $this->assertSame(20, $barang->stok);
        $this->assertSame('3000.00', $barang->hpp_rata_rata);
        $this->assertSame('3000.00', $barang->harga_beli_terakhir);
        $this->assertSame('4500.00', $barang->harga_jual);

        // Barang baru juga selalu mulai dari stok 0.
        $this->post(route('barang.store'), [
            'id_kategori' => $kategori->id_kategori,
            'nama_barang' => 'Barang Stok Palsu',
            'satuan' => 'pcs',
            'harga_jual' => 1000,
            'stok_minimum' => 5,
            'status' => 'aktif',
            'stok' => 50,
        ]);
        $this->assertSame(0, Barang::where('nama_barang', 'Barang Stok Palsu')->value('stok'));
    }

    // 6. Hapus kategori yang masih punya barang ditolak dengan pesan
    public function test_hapus_kategori_yang_masih_punya_barang_ditolak(): void
    {
        $kategori = KategoriBarang::factory()->create();
        Barang::factory()->create(['id_kategori' => $kategori->id_kategori]);

        $this->actingAs($this->admin)
            ->from(route('kategori-barang.index'))
            ->delete(route('kategori-barang.destroy', $kategori))
            ->assertRedirect(route('kategori-barang.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($kategori);

        // Kategori kosong bisa dihapus.
        $kosong = KategoriBarang::factory()->create();
        $this->delete(route('kategori-barang.destroy', $kosong))->assertSessionHas('success');
        $this->assertModelMissing($kosong);
    }

    public function test_admin_tidak_bisa_menonaktifkan_atau_menghapus_akun_sendiri(): void
    {
        $this->actingAs($this->admin);

        $this->put(route('pengguna.update', $this->admin), [
            'nama' => $this->admin->nama,
            'username' => $this->admin->username,
            'role' => 'administrator',
            'status' => 'nonaktif',
        ])->assertSessionHasErrors('status');
        $this->assertSame('aktif', $this->admin->fresh()->status);

        $this->delete(route('pengguna.destroy', $this->admin))->assertSessionHas('error');
        $this->assertModelExists($this->admin);
    }

    public function test_admin_reset_password_dan_nonaktifkan_kasir(): void
    {
        $this->actingAs($this->admin)->put(route('pengguna.update', $this->kasir), [
            'nama' => $this->kasir->nama,
            'username' => $this->kasir->username,
            'role' => 'kasir',
            'status' => 'nonaktif',
            'password' => 'passwordbaru',
            'password_confirmation' => 'passwordbaru',
        ])->assertSessionHasNoErrors();

        $kasir = $this->kasir->fresh();
        $this->assertSame('nonaktif', $kasir->status);
        $this->assertTrue(Hash::check('passwordbaru', $kasir->password));
    }

    public function test_username_unik_dan_password_minimal_delapan_karakter(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.store'), [
            'nama' => 'Ganda',
            'username' => $this->kasir->username,
            'role' => 'kasir',
            'status' => 'aktif',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['username', 'password']);
    }
}
