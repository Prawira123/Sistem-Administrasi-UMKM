<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\DetailStokOpname;
use App\Models\KartuStok;
use App\Models\KategoriBarang;
use App\Models\NotifikasiStok;
use App\Models\StokOpname;
use App\Models\User;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokOpnameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    private KategoriBarang $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->kasir = User::factory()->kasir()->create();
        $this->kategori = KategoriBarang::factory()->create();
    }

    private function barang(int $stok, array $atribut = []): Barang
    {
        return Barang::factory()->stok($stok, 3000)->create($atribut + [
            'id_kategori' => $this->kategori->id_kategori,
            'stok_minimum' => 2,
            'harga_jual' => 5000,
        ]);
    }

    /** Kasir membuat draft untuk kategori uji, lalu mengembalikan opname-nya. */
    private function buatDraft(User $user): StokOpname
    {
        $this->actingAs($user)->post(route('stok-opname.store'), ['id_kategori' => $this->kategori->id_kategori])
            ->assertSessionHasNoErrors();

        return StokOpname::latest('id_stok_opname')->firstOrFail();
    }

    private function detail(StokOpname $opname, Barang $barang): DetailStokOpname
    {
        return $opname->detail()->where('id_barang', $barang->id_barang)->firstOrFail();
    }

    /** Isi hitungan: [id_barang => [stok_fisik, keterangan]] */
    private function isiHitungan(User $user, StokOpname $opname, array $hitungan, bool $ajukan = false)
    {
        $detail = [];
        foreach ($hitungan as $idBarang => [$fisik, $keterangan]) {
            $detail[$opname->detail()->where('id_barang', $idBarang)->value('id_detail_opname')] = [
                'stok_fisik' => $fisik,
                'keterangan' => $keterangan,
            ];
        }

        return $this->actingAs($user)->put(route('stok-opname.update', $opname), array_filter([
            'detail' => $detail,
            'ajukan' => $ajukan ? 1 : null,
        ]));
    }

    // 1. Kasir membuat draft, mengisi stok fisik, mengajukan
    public function test_kasir_membuat_draft_mengisi_stok_fisik_dan_mengajukan(): void
    {
        $teh = $this->barang(10);
        $air = $this->barang(5);
        $this->barang(4, ['status' => 'nonaktif']);
        $lain = Barang::factory()->stok(7)->create(); // kategori lain

        $this->actingAs($this->kasir)->get(route('stok-opname.create'))->assertOk();
        $opname = $this->buatDraft($this->kasir);

        $this->assertSame('draft', $opname->status);
        $this->assertMatchesRegularExpression('/^SO-'.now()->format('Ym').'-01$/', $opname->kode_opname);
        $this->assertSame($this->kasir->id_user, $opname->id_user);
        $this->assertSame(2, $opname->detail()->count()); // hanya barang aktif kategori ini
        $this->assertSame(10, $this->detail($opname, $teh)->stok_sistem);
        $this->assertSame(0, $opname->detail()->where('id_barang', $lain->id_barang)->count());

        $this->get(route('stok-opname.edit', $opname))->assertOk()->assertSee($teh->nama_barang);

        $this->isiHitungan($this->kasir, $opname, [
            $teh->id_barang => [8, 'Rusak'],
            $air->id_barang => [5, null],
        ], ajukan: true)->assertRedirect(route('stok-opname.show', $opname))->assertSessionHasNoErrors();

        $opname->refresh();
        $this->assertSame('diajukan', $opname->status);
        $this->assertSame(8, $this->detail($opname, $teh)->stok_fisik);
        $this->assertSame(-2, $this->detail($opname, $teh)->selisih);
        $this->assertSame('Rusak', $this->detail($opname, $teh)->keterangan);
        $this->assertSame(0, $this->detail($opname, $air)->selisih);

        // Stok belum berubah sebelum disetujui.
        $this->assertSame(10, $teh->fresh()->stok);
        $this->assertSame(0, KartuStok::count());
    }

    // 2. Kasir tidak bisa menyetujui (403)
    public function test_kasir_tidak_bisa_menyetujui(): void
    {
        $teh = $this->barang(10);
        $opname = $this->buatDraft($this->kasir);
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [8, 'Hilang']], ajukan: true);

        $this->actingAs($this->kasir)->post(route('stok-opname.setujui', $opname))->assertForbidden();
        $this->actingAs($this->kasir)->post(route('stok-opname.tolak', $opname), ['catatan' => 'Hitung ulang'])->assertForbidden();
        $this->actingAs($this->kasir)->get(route('stok-opname.show', $opname))->assertOk()->assertDontSee('Ya, setujui');

        $this->assertSame('diajukan', $opname->fresh()->status);
        $this->assertSame(10, $teh->fresh()->stok);
    }

    // 3. Admin menyetujui: stok menjadi stok fisik, kartu stok penyesuaian tercatat, HPP tetap
    public function test_admin_menyetujui_stok_disesuaikan_dan_kartu_stok_tercatat(): void
    {
        $teh = $this->barang(10, ['stok_minimum' => 8]);
        $air = $this->barang(5);
        $opname = $this->buatDraft($this->kasir);
        $this->isiHitungan($this->kasir, $opname, [
            $teh->id_barang => [7, 'Kedaluwarsa'],
            $air->id_barang => [6, 'Salah catat'],
        ], ajukan: true);

        $this->actingAs($this->admin)->get(route('stok-opname.show', $opname))->assertOk()->assertSee('Ya, setujui');
        $this->post(route('stok-opname.setujui', $opname))
            ->assertRedirect(route('stok-opname.show', $opname))->assertSessionHasNoErrors();

        $opname->refresh();
        $this->assertSame('final', $opname->status);
        $this->assertSame($this->admin->id_user, $opname->disetujui_oleh);
        $this->assertSame($this->admin->id_user, $opname->approver->id_user);
        $this->assertNotNull($opname->disetujui_pada);

        $this->assertSame(7, $teh->fresh()->stok);
        $this->assertSame(6, $air->fresh()->stok);
        $this->assertSame('3000.00', $teh->fresh()->hpp_rata_rata);
        $this->assertSame('3000.00', $air->fresh()->hpp_rata_rata);

        $kartuTeh = KartuStok::where('id_barang', $teh->id_barang)->sole();
        $this->assertSame('penyesuaian', $kartuTeh->jenis_mutasi);
        $this->assertSame($opname->id_stok_opname, $kartuTeh->id_stok_opname);
        $this->assertSame(0, $kartuTeh->jumlah_masuk);
        $this->assertSame(3, $kartuTeh->jumlah_keluar);
        $this->assertSame(10, $kartuTeh->stok_sebelum);
        $this->assertSame(7, $kartuTeh->stok_sesudah);
        $this->assertSame($this->admin->id_user, $kartuTeh->id_user);

        $kartuAir = KartuStok::where('id_barang', $air->id_barang)->sole();
        $this->assertSame(1, $kartuAir->jumlah_masuk);
        $this->assertSame(6, $kartuAir->stok_sesudah);

        // Teh turun ke 7 ≤ minimum 8 → notifikasi stok minimum dibuat.
        $this->assertSame(1, NotifikasiStok::where('id_barang', $teh->id_barang)->where('status', 'baru')->count());

        // Kartu stok per barang menampilkan mutasi opname.
        $this->get(route('stok.kartu', $teh))->assertOk()->assertSee($opname->kode_opname);
    }

    // 4. Opname final tidak bisa diedit
    public function test_opname_final_tidak_bisa_diedit(): void
    {
        $teh = $this->barang(10);
        $opname = $this->buatDraft($this->kasir);
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [9, 'Hilang']], ajukan: true);

        // Diajukan pun sudah terkunci.
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [1, 'Hilang']])->assertSessionHasErrors('opname');

        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $opname));
        $this->assertSame('final', $opname->fresh()->status);

        $this->actingAs($this->admin)->get(route('stok-opname.edit', $opname))
            ->assertRedirect(route('stok-opname.show', $opname))->assertSessionHas('error');
        $this->isiHitungan($this->admin, $opname, [$teh->id_barang => [3, 'Rusak']])->assertSessionHasErrors('opname');
        $this->post(route('stok-opname.ajukan', $opname))->assertSessionHasErrors('opname');
        $this->post(route('stok-opname.setujui', $opname))->assertSessionHasErrors('opname');
        $this->post(route('stok-opname.tolak', $opname), ['catatan' => 'Coba tolak'])->assertSessionHasErrors('catatan');

        $this->assertSame('final', $opname->fresh()->status);
        $this->assertSame(9, $this->detail($opname, $teh)->stok_fisik);
        $this->assertSame(9, $teh->fresh()->stok);
        $this->assertSame(1, KartuStok::count());
    }

    // 5. Selisih ≠ 0 tanpa keterangan ditolak
    public function test_selisih_tanpa_keterangan_ditolak(): void
    {
        $teh = $this->barang(10, ['nama_barang' => 'Teh Botol']);
        $opname = $this->buatDraft($this->kasir);
        $idDetail = $this->detail($opname, $teh)->id_detail_opname;

        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [8, '']], ajukan: true)
            ->assertSessionHasErrors(["detail.{$idDetail}.keterangan" => 'Keterangan Teh Botol wajib diisi karena ada selisih.']);
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [-1, 'Rusak']])
            ->assertSessionHasErrors("detail.{$idDetail}.stok_fisik");

        $opname->refresh();
        $this->assertSame('draft', $opname->status);
        $this->assertSame(10, $this->detail($opname, $teh)->stok_fisik);
        $this->assertSame(0, $this->detail($opname, $teh)->selisih);

        // Selisih 0 tanpa keterangan boleh.
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [10, '']], ajukan: true)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $opname->fresh()->status);
    }

    // 6. Halaman peringatan hanya menampilkan barang aktif dengan stok ≤ minimum
    public function test_halaman_peringatan_hanya_barang_aktif_dengan_stok_di_bawah_minimum(): void
    {
        $this->barang(3, ['nama_barang' => 'Beras Menipis', 'stok_minimum' => 5]);
        $this->barang(5, ['nama_barang' => 'Gula Pas Minimum', 'stok_minimum' => 5]);
        $this->barang(0, ['nama_barang' => 'Minyak Habis', 'stok_minimum' => 5]);
        $this->barang(6, ['nama_barang' => 'Teh Aman', 'stok_minimum' => 5]);
        $this->barang(1, ['nama_barang' => 'Kopi Nonaktif', 'stok_minimum' => 5, 'status' => 'nonaktif']);

        $this->actingAs($this->kasir)->get(route('stok.peringatan'))->assertOk()
            ->assertSee('Beras Menipis')->assertSee('Gula Pas Minimum')->assertSee('Minyak Habis')
            ->assertDontSee('Teh Aman')->assertDontSee('Kopi Nonaktif');

        // Cek stok: filter status sesuai badge (aman/menipis/habis).
        $this->get(route('stok.index', ['status' => 'menipis']))->assertOk()
            ->assertSee('Beras Menipis')->assertSee('Gula Pas Minimum')
            ->assertDontSee('Minyak Habis')->assertDontSee('Teh Aman');
        $this->get(route('stok.index', ['status' => 'habis']))->assertOk()
            ->assertSee('Minyak Habis')->assertDontSee('Beras Menipis');
        $this->get(route('stok.index', ['q' => 'teh']))->assertOk()
            ->assertSee('Teh Aman')->assertDontSee('Beras Menipis')->assertDontSee('Kopi Nonaktif');
    }

    // Keputusan modul 6 poin 4: stok berubah sejak draft → stok baru = stok saat ini + selisih
    public function test_penjualan_setelah_draft_selisih_yang_diterapkan(): void
    {
        $teh = $this->barang(10);
        $opname = $this->buatDraft($this->kasir);
        $this->isiHitungan($this->kasir, $opname, [$teh->id_barang => [8, 'Hilang']], ajukan: true);

        // Terjual 3 setelah barang dihitung: stok 10 → 7.
        app(PenjualanService::class)->simpan([['id_barang' => $teh->id_barang, 'jumlah' => 3]], 'qris', '0', $this->kasir);
        $this->assertSame(7, $teh->fresh()->stok);

        $this->actingAs($this->admin)->get(route('stok-opname.show', $opname))->assertOk()
            ->assertSee('Stok berubah sejak draft dibuat');

        $this->post(route('stok-opname.setujui', $opname))->assertSessionHasNoErrors();

        $this->assertSame(5, $teh->fresh()->stok); // 7 + (−2), bukan 8
        $kartu = KartuStok::where('jenis_mutasi', 'penyesuaian')->sole();
        $this->assertSame(7, $kartu->stok_sebelum);
        $this->assertSame(2, $kartu->jumlah_keluar);
        $this->assertSame(5, $kartu->stok_sesudah);
        $this->assertStringContainsString('stok berubah 10 → 7 sejak draft', $kartu->keterangan);
        $this->assertSame(8, $this->detail($opname, $teh)->stok_fisik); // angka hitungan asli tetap
    }

    public function test_hasil_penyesuaian_minus_ditolak_lalu_dikembalikan_ke_draft(): void
    {
        $teh = $this->barang(10, ['nama_barang' => 'Teh Botol']);
        $air = $this->barang(5);
        $opname = $this->buatDraft($this->kasir);
        $this->isiHitungan($this->kasir, $opname, [
            $teh->id_barang => [2, 'Hilang'],     // selisih −8
            $air->id_barang => [6, 'Salah catat'],
        ], ajukan: true);

        // Terjual 5 setelah dihitung: stok 5, 5 + (−8) = −3.
        app(PenjualanService::class)->simpan([['id_barang' => $teh->id_barang, 'jumlah' => 5]], 'qris', '0', $this->kasir);

        $this->actingAs($this->admin)->get(route('stok-opname.show', $opname))->assertOk()->assertSee('Minus: tolak dan hitung ulang');

        $this->post(route('stok-opname.setujui', $opname))->assertSessionHasErrors([
            'opname' => 'Stok Teh Botol sudah berubah dan hasil penyesuaian menjadi minus. Kembalikan opname ke draft dan hitung ulang.',
        ]);

        // Tidak ada yang berubah, termasuk barang lain di opname yang sama.
        $this->assertSame('diajukan', $opname->fresh()->status);
        $this->assertSame(5, $teh->fresh()->stok);
        $this->assertSame(5, $air->fresh()->stok);
        $this->assertSame(0, KartuStok::where('jenis_mutasi', 'penyesuaian')->count());

        // Admin mengembalikan ke draft; pembuat bisa mengedit lagi.
        $this->post(route('stok-opname.tolak', $opname), ['catatan' => 'Stok sudah berubah, hitung ulang'])->assertSessionHasNoErrors();
        $opname->refresh();
        $this->assertSame('draft', $opname->status);
        $this->assertStringContainsString('hitung ulang', $opname->keterangan);
        $this->actingAs($this->kasir)->get(route('stok-opname.edit', $opname))->assertOk();
    }

    public function test_draft_hanya_bisa_diubah_pembuat_atau_admin(): void
    {
        $teh = $this->barang(10);
        $kasirLain = User::factory()->kasir()->create();
        $opname = $this->buatDraft($this->kasir);

        $this->actingAs($kasirLain)->get(route('stok-opname.edit', $opname))->assertForbidden();
        $this->isiHitungan($kasirLain, $opname, [$teh->id_barang => [9, 'Rusak']])->assertForbidden();
        $this->actingAs($kasirLain)->post(route('stok-opname.ajukan', $opname))->assertForbidden();
        $this->actingAs($kasirLain)->get(route('stok-opname.show', $opname))->assertOk();

        $this->isiHitungan($this->admin, $opname, [$teh->id_barang => [9, 'Rusak']])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->detail($opname, $teh)->stok_fisik);
        $this->assertSame('draft', $opname->fresh()->status);
    }
}
