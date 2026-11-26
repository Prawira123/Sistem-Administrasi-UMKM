<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\RiwayatAi;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Proses 8.0 AI Assistant (rancangan bagian 5).
 * Alur: klasifikasi pertanyaan (kata kunci) → ambil data lewat LaporanService → ringkas jadi JSON →
 * kirim ke Gemini sebagai konteks → simpan semuanya ke riwayat_ai.
 * Gemini tidak pernah mengakses database; yang dikirim hanya ringkasan JSON tanpa data pengguna.
 */
class AiAssistantService
{
    public const INSTRUKSI_SISTEM = 'Kamu asisten toko. Jawab dalam bahasa Indonesia yang singkat dan jelas, '
        .'hanya berdasarkan data JSON yang diberikan. Format uang Rupiah. '
        .'Bila data tidak tersedia, katakan bahwa datanya tidak ada. Jangan mengarang angka.';

    public const PESAN_GAGAL = 'AI Assistant sedang tidak dapat dihubungi. Coba lagi nanti.';

    public const MAKS_PERTANYAAN = 500;

    /** Kata kunci klasifikasi (dicocokkan pada pertanyaan huruf kecil). */
    public const KATA_KUNCI = [
        'stok' => ['stok', 'habis', 'barang', 'opname', 'persediaan', 'menipis', 'restok', 'kulakan'],
        'keuangan' => ['laba', 'rugi', 'kas', 'untung', 'pengeluaran', 'penjualan', 'hpp', 'omzet', 'pendapatan',
            'beban', 'modal', 'prive', 'uang', 'terjual', 'terlaris', 'laku'],
    ];

    /** Batas baris per daftar agar konteks tetap ringkas (total ±50 baris data). */
    private const MAKS_STOK_MENIPIS = 20;

    private const MAKS_TERLARIS = 10;

    private const MAKS_BEBAN = 10;

    public function __construct(private readonly LaporanService $laporan) {}

    /**
     * stok / keuangan bila hanya kata kunci satu kelompok yang muncul (atau jauh lebih banyak);
     * umum bila tidak ada kata kunci atau keduanya seimbang (konteks berisi ringkasan stok dan keuangan).
     */
    public function klasifikasi(string $pertanyaan): string
    {
        $teks = Str::lower($pertanyaan);
        $skor = [];
        foreach (self::KATA_KUNCI as $topik => $kata) {
            $skor[$topik] = collect($kata)->filter(fn ($k) => preg_match('/\b'.preg_quote($k, '/').'/u', $teks))->count();
        }

        if ($skor['stok'] === $skor['keuangan']) {
            return 'umum';
        }

        return $skor['stok'] > $skor['keuangan'] ? 'stok' : 'keuangan';
    }

    /**
     * Ringkasan data sistem untuk dikirim ke Gemini. Nilai uang dalam Rupiah (string desimal).
     * Tidak memuat data pengguna (nama, username, email, password).
     */
    public function kumpulkanKonteks(string $topik): array
    {
        $sekarang = now();
        $konteks = [
            'toko' => config('toko.nama'),
            'waktu_data' => $sekarang->format('Y-m-d H:i').' WITA',
            'mata_uang' => 'IDR (Rupiah)',
        ];

        if ($topik === 'stok' || $topik === 'umum') {
            $konteks['stok'] = $this->konteksStok($topik === 'umum' ? 10 : self::MAKS_STOK_MENIPIS);
        }

        if ($topik === 'keuangan' || $topik === 'umum') {
            $konteks['keuangan'] = $this->konteksKeuangan($sekarang);
        }

        // Barang terlaris berguna untuk keduanya (keputusan restok dan analisis penjualan).
        $konteks['barang_terlaris_30_hari'] = $this->laporan
            ->barangTerlaris(today()->subDays(29), $sekarang, self::MAKS_TERLARIS)
            ->map(fn ($b) => ['nama' => $b['nama'], 'qty' => $b['qty'], 'satuan' => $b['satuan'], 'omzet' => $b['omzet']])
            ->all();

        return $konteks;
    }

    /**
     * Ajukan pertanyaan ke Gemini dan simpan hasilnya (berhasil maupun gagal) ke riwayat_ai.
     */
    public function tanya(string $pertanyaan, User $admin): RiwayatAi
    {
        $pertanyaan = trim($pertanyaan);
        $topik = $this->klasifikasi($pertanyaan);
        $konteks = $this->kumpulkanKonteks($topik);
        $model = (string) config('services.gemini.model');

        $riwayat = new RiwayatAi([
            'id_user' => $admin->id_user,
            'pertanyaan' => $pertanyaan,
            'topik' => $topik,
            'konteks_data' => $konteks,
            'model' => Str::limit($model, 50, ''),
        ]);

        $mulai = hrtime(true);
        try {
            $riwayat->jawaban = $this->panggilGemini($model, $pertanyaan, $konteks);
            $riwayat->status = 'berhasil';
        } catch (Throwable $e) {
            $riwayat->status = 'gagal';
            $riwayat->pesan_error = Str::limit($this->pesanError($e), 250);
            if (! $e instanceof GeminiException && ! $e instanceof ConnectionException) {
                report($e); // galat tak terduga tetap tercatat di log
            }
        } finally {
            $riwayat->waktu_respons_ms = (int) round((hrtime(true) - $mulai) / 1_000_000);
        }

        $riwayat->save();

        return $riwayat;
    }

    /** Badan request generateContent (dipisah agar mudah dijelaskan dan diuji). */
    public function bodyRequest(string $pertanyaan, array $konteks): array
    {
        $json = json_encode($konteks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return [
            'systemInstruction' => ['parts' => [['text' => self::INSTRUKSI_SISTEM]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => "Data toko (JSON):\n```json\n{$json}\n```\n\nPertanyaan: {$pertanyaan}"]],
            ]],
            'generationConfig' => ['temperature' => 0.2],
        ];
    }

    private function panggilGemini(string $model, string $pertanyaan, array $konteks): string
    {
        $apiKey = config('services.gemini.api_key');
        if (blank($apiKey)) {
            throw new GeminiException('GEMINI_API_KEY belum diisi di .env.');
        }

        $respons = Http::timeout((int) config('services.gemini.timeout'))
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $apiKey]) // key lewat header, tidak di URL/log/browser
            ->post(rtrim((string) config('services.gemini.base_url'), '/')."/models/{$model}:generateContent",
                $this->bodyRequest($pertanyaan, $konteks));

        if ($respons->failed()) {
            $pesan = $respons->json('error.message') ?? Str::limit(trim($respons->body()), 150);
            throw new GeminiException("HTTP {$respons->status()}: {$pesan}");
        }

        $teks = collect($respons->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode('');
        if (trim($teks) === '') {
            $alasan = $respons->json('candidates.0.finishReason') ?? $respons->json('promptFeedback.blockReason') ?? 'tanpa teks';
            throw new GeminiException("Respons Gemini kosong ({$alasan}).");
        }

        return trim($teks);
    }

    private function pesanError(Throwable $e): string
    {
        $pesan = $e instanceof ConnectionException
            ? 'Tidak dapat terhubung ke Gemini atau melebihi batas waktu '.config('services.gemini.timeout').' detik.'
            : $e->getMessage();

        // Jaga-jaga: jangan pernah menyimpan API key di pesan error.
        $key = (string) config('services.gemini.api_key');

        return $key !== '' ? str_replace($key, '***', $pesan) : $pesan;
    }

    private function konteksStok(int $batas): array
    {
        $menipis = Barang::stokMenipis()->with('kategori')->orderBy('stok')->orderBy('nama_barang')->get();

        return [
            'jumlah_barang_aktif' => Barang::aktif()->count(),
            'jumlah_stok_menipis' => $menipis->count(),
            'jumlah_stok_habis' => $menipis->where('stok', '<=', 0)->count(),
            'barang_stok_menipis' => $menipis->take($batas)->map(fn (Barang $b) => [
                'kode' => $b->kode_barang,
                'nama' => $b->nama_barang,
                'kategori' => $b->kategori?->nama_kategori,
                'stok' => $b->stok,
                'stok_minimum' => $b->stok_minimum,
                'satuan' => $b->satuan,
            ])->values()->all(),
            'catatan' => $menipis->count() > $batas ? "Hanya {$batas} barang dengan stok terendah yang ditampilkan." : null,
        ];
    }

    private function konteksKeuangan(Carbon $sekarang): array
    {
        $bulanIni = $sekarang->copy()->startOfMonth();
        $bulanLalu = $bulanIni->copy()->subMonth();

        return [
            'saldo_kas_saat_ini' => $this->laporan->arusKas($bulanIni, $sekarang)['saldo_akhir'],
            'penjualan' => [
                'bulan_ini' => $this->ringkasPenjualan($bulanIni, $sekarang),
                'bulan_lalu' => $this->ringkasPenjualan($bulanLalu, $bulanLalu->copy()->endOfMonth()),
            ],
            'laba_rugi' => [
                'bulan_ini' => $this->ringkasLabaRugi($bulanIni),
                'bulan_lalu' => $this->ringkasLabaRugi($bulanLalu),
            ],
            'beban_operasional_bulan_ini' => DB::table('pengeluaran_operasional as p')
                ->join('kategori_pengeluaran as k', 'k.id_kategori_pengeluaran', '=', 'p.id_kategori_pengeluaran')
                ->whereBetween('p.tanggal_pengeluaran', [$bulanIni->toDateString(), $sekarang->toDateString()])
                ->groupBy('k.nama_kategori')
                ->orderByDesc(DB::raw('SUM(p.jumlah)'))
                ->limit(self::MAKS_BEBAN)
                ->pluck(DB::raw('SUM(p.jumlah)'), 'k.nama_kategori')
                ->map(fn ($v) => \App\Support\Uang::normal($v))
                ->all(),
        ];
    }

    private function ringkasPenjualan(Carbon $dari, Carbon $sampai): array
    {
        $p = $this->laporan->penjualan($dari, $sampai);

        return [
            'periode' => $dari->format('Y-m-d').' s.d. '.$sampai->format('Y-m-d'),
            'total' => $p['total'],
            'jumlah_transaksi' => $p['jumlah_transaksi'],
            'unit_terjual' => $p['total_unit'],
            'transaksi_batal' => $p['jumlah_batal'],
            'per_metode' => $p['per_metode']->mapWithKeys(fn ($m) => [$m['metode'] => $m['total']])->all(),
        ];
    }

    /** Laba rugi resmi (hpp_periode). Bila HPP belum dihitung, sertakan estimasi yang diberi label jelas. */
    private function ringkasLabaRugi(Carbon $bulan): array
    {
        $lr = $this->laporan->labaRugi($bulan->year, $bulan->month);
        $hasil = [
            'bulan' => $bulan->format('Y-m'),
            'pendapatan' => $lr['pendapatan'],
            'hpp_periodik_sudah_dihitung' => $lr['hpp_dihitung'],
            'hpp' => $lr['hpp'],
            'laba_kotor' => $lr['laba_kotor'],
            'total_beban_operasional' => $lr['total_beban'],
            'laba_bersih' => $lr['laba_bersih'],
        ];

        if (! $lr['hpp_dihitung']) {
            $est = $this->laporan->estimasiLabaBulan($bulan->year, $bulan->month);
            $hasil['estimasi_laba_bersih_hpp_rata_rata'] = $est['laba_bersih'];
            $hasil['catatan'] = 'HPP periodik belum dihitung; laba resmi belum ada. Estimasi memakai HPP rata-rata saat transaksi.';
        }

        return $hasil;
    }
}
