<?php

namespace App\Http\Controllers;

use App\Http\Requests\TanyaAiRequest;
use App\Models\RiwayatAi;
use App\Services\AiAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/** Proses 8.0 AI Assistant (Gemini). Khusus Administrator. */
class AiAssistantController extends Controller implements HasMiddleware
{
    public const CONTOH_PERTANYAAN = [
        'Barang apa yang hampir habis?',
        'Berapa laba bulan lalu?',
        'Bandingkan penjualan bulan ini dengan bulan lalu.',
        'Barang apa yang paling laris 30 hari terakhir?',
        'Berapa saldo kas sekarang?',
        'Beban operasional terbesar bulan ini apa?',
    ];

    public function __construct(private readonly AiAssistantService $ai) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-ai')];
    }

    /** Halaman chat: 10 tanya-jawab terakhir milik admin yang login (urut lama → baru). */
    public function index(Request $request): View
    {
        $percakapan = RiwayatAi::where('id_user', $request->user()->id_user)
            ->latest('id_riwayat_ai')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        return view('ai.index', [
            'percakapan' => $percakapan,
            'contoh' => self::CONTOH_PERTANYAAN,
            'siap' => filled(config('services.gemini.api_key')),
        ]);
    }

    public function store(TanyaAiRequest $request): RedirectResponse
    {
        // Batas waktu PHP harus lebih panjang dari timeout HTTP ke Gemini.
        @set_time_limit((int) config('services.gemini.timeout') + 30);

        $riwayat = $this->ai->tanya($request->validated('pertanyaan'), $request->user());

        $redirect = redirect()->to(route('ai.index').'#riwayat-'.$riwayat->id_riwayat_ai);

        return $riwayat->isBerhasil()
            ? $redirect
            : $redirect->withInput()->with('error', AiAssistantService::PESAN_GAGAL);
    }

    public function riwayat(Request $request): View
    {
        $cari = trim((string) $request->query('q'));

        $riwayat = RiwayatAi::with('user')
            ->when($cari !== '', fn ($q) => $q->where('pertanyaan', 'like', '%'.addcslashes($cari, '%_\\').'%'))
            ->when(in_array($request->query('status'), ['berhasil', 'gagal'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id_riwayat_ai')
            ->paginate(15)
            ->withQueryString();

        return view('ai.riwayat', compact('riwayat', 'cari'));
    }

    /** Detail riwayat, termasuk konteks JSON yang dikirim ke Gemini (bukti pengujian). */
    public function show(RiwayatAi $riwayat): View
    {
        return view('ai.show', ['riwayat' => $riwayat->load('user')]);
    }
}
