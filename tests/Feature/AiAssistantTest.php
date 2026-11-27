<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\RiwayatAi;
use App\Models\User;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'kunci-uji-rahasia-123';

    private const URL = 'https://gemini.test/v1beta/models/gemini-2.5-flash:generateContent';

    private User $admin;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => self::KEY,
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.timeout' => 5,
        ]);
        Http::preventStrayRequests();

        $this->admin = User::factory()->administrator()->create(['nama' => 'Ibu Admin Rahasia', 'username' => 'admin_rahasia', 'email' => 'admin.rahasia@contoh.test']);
        $this->kasir = User::factory()->kasir()->create(['nama' => 'Pak Kasir Rahasia', 'username' => 'kasir_rahasia', 'email' => 'kasir.rahasia@contoh.test']);
    }

    protected function tearDown(): void
    {
        // AiAssistantController::store() memanggil set_time_limit(timeout + 30). Batas itu berlaku untuk seluruh
        // proses PHPUnit, sehingga test berikutnya bisa terhenti "Maximum execution time". Kembalikan tanpa batas.
        set_time_limit(0);

        parent::tearDown();
    }

    private function jawabanGemini(string $teks): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $teks]]], 'finishReason' => 'STOP']]];
    }

    // 1. Pertanyaan stok → topik stok, konteks berisi stok menipis, jawaban tersimpan berstatus berhasil
    public function test_pertanyaan_stok_memakai_konteks_stok_menipis_dan_tersimpan_berhasil(): void
    {
        Barang::factory()->stok(2, 1000)->create(['nama_barang' => 'Gula Pasir 1 kg', 'stok_minimum' => 5]);
        Barang::factory()->stok(50, 1000)->create(['nama_barang' => 'Teh Celup', 'stok_minimum' => 5]);
        Http::fake([self::URL => Http::response($this->jawabanGemini(
            "Barang yang hampir habis:\n\n- **Gula Pasir 1 kg**: sisa 2 kg (minimum 5)\n\n<script>alert('x')</script>"
        ))]);

        $this->actingAs($this->admin)
            ->post(route('ai.tanya'), ['pertanyaan' => 'Barang apa yang stoknya hampir habis?'])
            ->assertRedirect()->assertSessionMissing('error');

        $riwayat = RiwayatAi::sole();
        $this->assertSame('stok', $riwayat->topik);
        $this->assertSame('berhasil', $riwayat->status);
        $this->assertSame($this->admin->id_user, $riwayat->id_user);
        $this->assertSame('gemini-2.5-flash', $riwayat->model);
        $this->assertNull($riwayat->pesan_error);
        $this->assertNotNull($riwayat->waktu_respons_ms);
        $this->assertStringContainsString('Gula Pasir 1 kg', $riwayat->jawaban);

        $menipis = collect($riwayat->konteks_data['stok']['barang_stok_menipis']);
        $this->assertSame(['Gula Pasir 1 kg'], $menipis->pluck('nama')->all());
        $this->assertSame(2, $menipis->first()['stok']);
        $this->assertSame(1, $riwayat->konteks_data['stok']['jumlah_stok_menipis']);
        $this->assertArrayNotHasKey('keuangan', $riwayat->konteks_data);

        // Markdown dirender aman: tebal jadi <strong>, tag <script> dari jawaban dibuang.
        $this->get(route('ai.index'))->assertOk()
            ->assertSee('<strong>Gula Pasir 1 kg</strong>', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertDontSee(self::KEY);
        $this->get(route('ai.riwayat'))->assertOk()->assertSee('Barang apa yang stoknya hampir habis?');
        $this->get(route('ai.show', $riwayat))->assertOk()->assertSee('barang_stok_menipis')->assertDontSee(self::KEY);

        // Klasifikasi kata kunci.
        $ai = app(AiAssistantService::class);
        $this->assertSame('keuangan', $ai->klasifikasi('Berapa laba bulan lalu?'));
        $this->assertSame('keuangan', $ai->klasifikasi('Berapa saldo KAS sekarang?'));
        $this->assertSame('stok', $ai->klasifikasi('Kapan terakhir stok opname?'));
        $this->assertSame('umum', $ai->klasifikasi('Halo, apa kabar?'));
    }

    // 2. Gemini timeout / HTTP 500 / respons kosong → status gagal, pesan error tersimpan, pesan ramah tampil
    public function test_gemini_gagal_disimpan_berstatus_gagal_dan_menampilkan_pesan_ramah(): void
    {
        // Panggilan ke-1: HTTP 500, ke-2: respons tanpa teks (diblokir), ke-3: timeout.
        $ke = 0;
        Http::fake([self::URL => function () use (&$ke) {
            return match (++$ke) {
                1 => Http::response(['error' => ['code' => 500, 'message' => 'Internal error encountered.']], 500),
                2 => Http::response(['candidates' => [['finishReason' => 'SAFETY']]], 200),
                default => throw new ConnectionException('cURL error 28: Operation timed out'),
            };
        }]);

        $this->actingAs($this->admin)->post(route('ai.tanya'), ['pertanyaan' => 'Berapa laba bulan ini?'])
            ->assertRedirect()->assertSessionHas('error', AiAssistantService::PESAN_GAGAL);
        $this->post(route('ai.tanya'), ['pertanyaan' => 'Berapa omzet bulan ini?'])->assertSessionHas('error');
        $this->post(route('ai.tanya'), ['pertanyaan' => 'Berapa saldo kas?'])->assertSessionHas('error');

        // API key kosong.
        config(['services.gemini.api_key' => null]);
        $this->post(route('ai.tanya'), ['pertanyaan' => 'Berapa pengeluaran listrik?'])->assertSessionHas('error');

        $gagal = RiwayatAi::orderBy('id_riwayat_ai')->get();
        $this->assertCount(4, $gagal);
        $this->assertTrue($gagal->every(fn ($r) => $r->status === 'gagal' && $r->jawaban === null && $r->konteks_data !== null));
        $this->assertSame('HTTP 500: Internal error encountered.', $gagal[0]->pesan_error);
        $this->assertSame('Respons Gemini kosong (SAFETY).', $gagal[1]->pesan_error);
        $this->assertStringContainsString('batas waktu 5 detik', $gagal[2]->pesan_error);
        $this->assertSame('GEMINI_API_KEY belum diisi di .env.', $gagal[3]->pesan_error);
        $this->assertSame('keuangan', $gagal[0]->topik);

        $this->get(route('ai.index'))->assertOk()->assertSee(AiAssistantService::PESAN_GAGAL);
        $this->get(route('ai.show', $gagal[0]))->assertOk()->assertSee('HTTP 500: Internal error encountered.');
    }

    // 3. Request ke Gemini berisi konteks JSON dan tidak berisi data pengguna (password, email)
    public function test_request_gemini_berisi_konteks_json_tanpa_data_pengguna(): void
    {
        Barang::factory()->stok(1, 1000)->create(['nama_barang' => 'Minyak Goreng 1 L', 'stok_minimum' => 3]);
        Http::fake([self::URL => Http::response($this->jawabanGemini('Saldo kas Rp0.'))]);

        $this->actingAs($this->admin)->post(route('ai.tanya'), ['pertanyaan' => 'Bagaimana kondisi toko bulan ini?']);

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) {
            $body = $request->body();
            $data = $request->data();
            $teks = $data['contents'][0]['parts'][0]['text'];

            // Alamat, metode, dan header API key (key tidak ada di URL maupun body).
            $this->assertSame(self::URL, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame([self::KEY], $request->header('x-goog-api-key'));
            $this->assertStringNotContainsString(self::KEY, $body);

            // Instruksi sistem dan konteks JSON.
            $this->assertSame(AiAssistantService::INSTRUKSI_SISTEM, $data['systemInstruction']['parts'][0]['text']);
            $this->assertStringContainsString("Data toko (JSON):\n```json", $teks);
            $this->assertStringContainsString('Pertanyaan: Bagaimana kondisi toko bulan ini?', $teks);
            preg_match('/```json\n(.*)\n```/s', $teks, $cocok);
            $konteks = json_decode($cocok[1], true);
            $this->assertIsArray($konteks);
            $this->assertArrayHasKey('stok', $konteks);              // topik umum: stok + keuangan
            $this->assertArrayHasKey('keuangan', $konteks);
            $this->assertSame('Minyak Goreng 1 L', $konteks['stok']['barang_stok_menipis'][0]['nama']);

            // Tidak ada data pengguna.
            foreach ([$this->admin, $this->kasir] as $user) {
                foreach ([$user->nama, $user->username, $user->email, $user->getAuthPassword()] as $rahasia) {
                    $this->assertStringNotContainsString($rahasia, $body);
                }
            }
            $this->assertStringNotContainsStringIgnoringCase('password', $body);
            $this->assertStringNotContainsStringIgnoringCase('email', $body);

            return true;
        });

        // assertEquals: kolom JSON MySQL menyimpan key dengan urutan berbeda, isinya sama.
        $this->assertEquals(RiwayatAi::sole()->konteks_data, json_decode(
            Str::between(Http::recorded()[0][0]->data()['contents'][0]['parts'][0]['text'], "```json\n", "\n```"), true));
    }

    // 4. Kasir → 403
    public function test_kasir_tidak_bisa_membuka_ai_assistant(): void
    {
        Http::fake();
        $riwayat = RiwayatAi::create(['id_user' => $this->admin->id_user, 'pertanyaan' => 'X', 'topik' => 'umum',
            'model' => 'gemini-2.5-flash', 'status' => 'berhasil', 'jawaban' => 'Y']);

        $this->actingAs($this->kasir);
        $this->get(route('ai.index'))->assertForbidden();
        $this->get(route('ai.riwayat'))->assertForbidden();
        $this->get(route('ai.show', $riwayat))->assertForbidden();
        $this->post(route('ai.tanya'), ['pertanyaan' => 'Berapa laba bulan ini?'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(1, RiwayatAi::count());
    }

    // 5. Pertanyaan kosong atau > 500 karakter ditolak
    public function test_pertanyaan_kosong_atau_lebih_dari_500_karakter_ditolak(): void
    {
        Http::fake([self::URL => Http::response($this->jawabanGemini('Oke.'))]);
        $this->actingAs($this->admin);

        $this->post(route('ai.tanya'), ['pertanyaan' => ''])->assertSessionHasErrors(['pertanyaan' => 'Pertanyaan wajib diisi.']);
        $this->post(route('ai.tanya'), ['pertanyaan' => '   '])->assertSessionHasErrors('pertanyaan');
        $this->post(route('ai.tanya'), ['pertanyaan' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['pertanyaan' => 'Pertanyaan maksimal 500 karakter.']);

        Http::assertNothingSent();
        $this->assertSame(0, RiwayatAi::count());

        // Tepat 500 karakter diterima.
        $this->post(route('ai.tanya'), ['pertanyaan' => str_repeat('a', 500)])->assertSessionHasNoErrors();
        $this->assertSame(1, RiwayatAi::count());
    }

    // Tambahan: batas 10 pertanyaan per menit per Administrator
    public function test_batas_10_pertanyaan_per_menit(): void
    {
        Http::fake([self::URL => Http::response($this->jawabanGemini('Oke.'))]);
        $this->actingAs($this->admin);

        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('ai.tanya'), ['pertanyaan' => "Pertanyaan stok ke-$i"])->assertSessionMissing('error');
        }
        $this->post(route('ai.tanya'), ['pertanyaan' => 'Pertanyaan ke-11'])
            ->assertRedirect()->assertSessionHas('error', fn ($pesan) => str_contains($pesan, '10 pertanyaan per menit'));
        $this->assertSame(10, RiwayatAi::count());

        // Admin lain punya kuota sendiri; setelah satu menit kuota pulih.
        $adminLain = User::factory()->administrator()->create();
        $this->actingAs($adminLain)->post(route('ai.tanya'), ['pertanyaan' => 'Stok aman?'])->assertSessionMissing('error');
        $this->travel(61)->seconds();
        $this->actingAs($this->admin)->post(route('ai.tanya'), ['pertanyaan' => 'Stok aman?'])->assertSessionMissing('error');
        $this->assertSame(12, RiwayatAi::count());
    }
}
