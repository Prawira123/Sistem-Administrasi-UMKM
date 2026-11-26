<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DetailStokOpname;
use App\Models\StokOpname;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses 5.0 Monitoring Stok: stok opname (rancangan bagian 3 "Stok opname").
 *
 * Alur status: draft → diajukan → final (atau diajukan → draft bila ditolak Administrator).
 *   - buatDraft()      : Kasir/Admin; stok_sistem = stok terkini, stok_fisik awal = stok_sistem (selisih 0).
 *   - simpanHitungan() : isi stok_fisik per barang; selisih = stok_fisik − stok_sistem; keterangan wajib bila selisih ≠ 0.
 *   - ajukan()         : oleh pembuat (atau Admin); setelah itu data terkunci.
 *   - setujui()        : khusus Admin, dalam DB::transaction; stok disesuaikan dan kartu stok penyesuaian ditulis.
 *   - tolak()          : khusus Admin; kembali ke draft dengan catatan.
 *
 * Keputusan bila stok berubah antara draft dan persetujuan (mis. ada penjualan setelah barang dihitung):
 * yang diterapkan adalah SELISIH hasil hitung, bukan angka stok fisik:
 *     stok_baru = stok_saat_ini + selisih
 * Contoh: draft stok_sistem 10, fisik 8 (selisih −2), lalu terjual 3 (stok 7) → stok_baru = 7 − 2 = 5.
 * Dengan begitu penjualan/barang masuk setelah penghitungan tetap terhitung. Bila stok_saat_ini + selisih < 0,
 * persetujuan seluruh opname ditolak dan opname harus dikembalikan ke draft lalu dihitung ulang.
 * Detail opname tetap menyimpan angka hitungan asli; perubahan stok sejak draft dicatat di keterangan kartu stok.
 */
class StokOpnameService
{
    public const KETERANGAN_SELISIH = ['Rusak', 'Hilang', 'Kedaluwarsa', 'Salah catat'];

    public function __construct(
        private readonly NomorOtomatisService $nomor,
        private readonly KartuStokService $kartuStok,
        private readonly NotifikasiStokService $notifikasi,
    ) {}

    /** Draft berisi semua barang aktif, atau barang aktif dalam satu kategori. */
    public function buatDraft(User $user, ?int $idKategori = null, ?string $keterangan = null): StokOpname
    {
        return DB::transaction(function () use ($user, $idKategori, $keterangan) {
            $barang = Barang::aktif()
                ->when($idKategori, fn ($q) => $q->where('id_kategori', $idKategori))
                ->orderBy('nama_barang')
                ->get();

            if ($barang->isEmpty()) {
                throw ValidationException::withMessages(['id_kategori' => 'Tidak ada barang aktif untuk dihitung.']);
            }

            $opname = StokOpname::create([
                'kode_opname' => $this->nomor->stokOpname(now()),
                'id_user' => $user->id_user,
                'tanggal_opname' => now()->toDateString(),
                'status' => 'draft',
                'keterangan' => $keterangan !== null && $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
            ]);

            $opname->detail()->createMany($barang->map(fn (Barang $b) => [
                'id_barang' => $b->id_barang,
                'stok_sistem' => $b->stok,
                'stok_fisik' => $b->stok,
                'selisih' => 0,
            ])->all());

            return $opname;
        });
    }

    /**
     * @param  array<int|string, array{stok_fisik: int|string, keterangan?: ?string}>  $hitungan  key = id_detail_opname
     */
    public function simpanHitungan(StokOpname $opname, array $hitungan, User $user): StokOpname
    {
        return DB::transaction(function () use ($opname, $hitungan, $user) {
            $opname = $this->kunci($opname);
            $this->pastikanBolehUbah($opname, $user);

            $detail = $opname->detail()->with('barang')->get()->keyBy('id_detail_opname');
            $galat = [];

            foreach ($hitungan as $idDetail => $baris) {
                /** @var DetailStokOpname|null $item */
                $item = $detail[$idDetail] ?? null;
                if (! $item) {
                    continue; // baris bukan milik opname ini: abaikan
                }

                $fisik = filter_var($baris['stok_fisik'] ?? null, FILTER_VALIDATE_INT);
                if ($fisik === false || $fisik < 0) {
                    $galat["detail.{$idDetail}.stok_fisik"] = "Stok fisik {$item->barang->nama_barang} harus bilangan bulat ≥ 0.";

                    continue;
                }

                $keterangan = trim((string) ($baris['keterangan'] ?? ''));
                $selisih = $fisik - $item->stok_sistem;

                if ($selisih !== 0 && $keterangan === '') {
                    $galat["detail.{$idDetail}.keterangan"] = "Keterangan {$item->barang->nama_barang} wajib diisi karena ada selisih.";

                    continue;
                }

                $item->update([
                    'stok_fisik' => $fisik,
                    'selisih' => $selisih,
                    'keterangan' => $keterangan === '' ? null : mb_substr($keterangan, 0, 255),
                ]);
            }

            if ($galat) {
                throw ValidationException::withMessages($galat);
            }

            return $opname;
        });
    }

    public function ajukan(StokOpname $opname, User $user): StokOpname
    {
        return DB::transaction(function () use ($opname, $user) {
            $opname = $this->kunci($opname);
            $this->pastikanBolehUbah($opname, $user);

            $tanpaKeterangan = $opname->detail()->with('barang')
                ->where('selisih', '!=', 0)
                ->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', ''))
                ->first();

            if ($tanpaKeterangan) {
                throw ValidationException::withMessages([
                    'opname' => "Keterangan {$tanpaKeterangan->barang->nama_barang} wajib diisi karena ada selisih.",
                ]);
            }

            $opname->update(['status' => 'diajukan']);

            return $opname;
        });
    }

    /**
     * Persetujuan Administrator: status final, stok = stok saat ini + selisih untuk barang berselisih.
     */
    public function setujui(StokOpname $opname, User $admin): StokOpname
    {
        $this->pastikanAdmin($admin);

        return DB::transaction(function () use ($opname, $admin) {
            $opname = $this->kunci($opname);

            if (! $opname->isDiajukan()) {
                throw ValidationException::withMessages(['opname' => "Stok opname {$opname->kode_opname} belum diajukan atau sudah final."]);
            }

            $berselisih = $opname->detail()->where('selisih', '!=', 0)->get();

            // Kunci semua barang berselisih sekaligus, urut id (sama seperti penjualan) agar tidak deadlock.
            $daftarBarang = Barang::whereIn('id_barang', $berselisih->pluck('id_barang'))
                ->orderBy('id_barang')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_barang');

            // Periksa dulu semua baris sebelum mengubah apa pun.
            foreach ($berselisih as $item) {
                $barang = $daftarBarang[$item->id_barang];
                if ($barang->stok + $item->selisih < 0) {
                    throw ValidationException::withMessages([
                        'opname' => "Stok {$barang->nama_barang} sudah berubah dan hasil penyesuaian menjadi minus. Kembalikan opname ke draft dan hitung ulang.",
                    ]);
                }
            }

            foreach ($berselisih as $item) {
                $barang = $daftarBarang[$item->id_barang];
                $stokSebelum = $barang->stok;

                // HPP rata-rata tidak berubah; hanya stok.
                $barang->stok = $stokSebelum + $item->selisih;
                $barang->save();

                $keterangan = "Stok opname {$opname->kode_opname}: {$item->keterangan}";
                if ($stokSebelum !== $item->stok_sistem) {
                    $keterangan .= " (stok berubah {$item->stok_sistem} → {$stokSebelum} sejak draft; selisih {$item->selisih} diterapkan)";
                }

                $this->kartuStok->catat(
                    $barang,
                    KartuStokService::PENYESUAIAN,
                    max($item->selisih, 0),
                    max(-$item->selisih, 0),
                    ['id_stok_opname' => $opname->id_stok_opname],
                    $keterangan,
                    $admin,
                );

                // Stok bisa turun (buat notifikasi) atau naik (tutup notifikasi).
                $this->notifikasi->periksa($barang);
                $this->notifikasi->tutupBilaAman($barang);
            }

            $opname->update([
                'status' => 'final',
                'disetujui_oleh' => $admin->id_user,
                'disetujui_pada' => now(),
            ]);

            return $opname;
        });
    }

    /** Admin menolak opname yang diajukan: kembali ke draft dengan catatan. */
    public function tolak(StokOpname $opname, string $catatan, User $admin): StokOpname
    {
        $this->pastikanAdmin($admin);

        return DB::transaction(function () use ($opname, $catatan, $admin) {
            $opname = $this->kunci($opname);

            if (! $opname->isDiajukan()) {
                throw ValidationException::withMessages(['catatan' => "Stok opname {$opname->kode_opname} tidak sedang diajukan."]);
            }

            $opname->update([
                'status' => 'draft',
                'keterangan' => mb_substr("Ditolak {$admin->nama}: ".trim($catatan), 0, 255),
            ]);

            return $opname;
        });
    }

    /**
     * Barang berselisih yang stoknya sudah berubah sejak draft, untuk peringatan sebelum Admin menyetujui.
     *
     * @return Collection<int, array{detail: DetailStokOpname, stok_saat_ini: int, stok_hasil: int, minus: bool}>
     */
    public function perubahanSejakDraft(StokOpname $opname): Collection
    {
        return $opname->detail()->with('barang')
            ->where('selisih', '!=', 0)
            ->get()
            ->filter(fn (DetailStokOpname $d) => $d->barang->stok !== $d->stok_sistem)
            ->map(fn (DetailStokOpname $d) => [
                'detail' => $d,
                'stok_saat_ini' => $d->barang->stok,
                'stok_hasil' => $d->barang->stok + $d->selisih,
                'minus' => $d->barang->stok + $d->selisih < 0,
            ])
            ->values();
    }

    /** Pembuat draft atau Administrator, dan hanya selama status draft. */
    public function bolehUbah(StokOpname $opname, User $user): bool
    {
        return $opname->isDraft() && ($user->isAdministrator() || $opname->id_user === $user->id_user);
    }

    private function pastikanBolehUbah(StokOpname $opname, User $user): void
    {
        if (! $opname->isDraft()) {
            throw ValidationException::withMessages([
                'opname' => "Stok opname {$opname->kode_opname} berstatus {$opname->status} dan tidak bisa diubah.",
            ]);
        }

        if (! $this->bolehUbah($opname, $user)) {
            throw new AuthorizationException('Hanya pembuat draft atau Administrator yang dapat mengubah stok opname ini.');
        }
    }

    private function pastikanAdmin(User $user): void
    {
        if (! $user->isAdministrator()) {
            throw new AuthorizationException('Hanya Administrator yang dapat menyetujui atau menolak stok opname.');
        }
    }

    private function kunci(StokOpname $opname): StokOpname
    {
        return StokOpname::whereKey($opname->getKey())->lockForUpdate()->firstOrFail();
    }
}
