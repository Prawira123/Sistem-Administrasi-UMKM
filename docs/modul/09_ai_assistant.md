# Modul 9: AI Assistant (Gemini API)

**DFD:** proses 8.0 AI Assistant. **Tabel:** `riwayat_ai` (+ baca tabel stok dan keuangan). **Hanya Administrator.**

## Tujuan
Administrator bertanya dalam bahasa sehari-hari ("Barang apa yang hampir habis?", "Berapa laba bulan lalu?"). Sistem mengambil data, merangkumnya menjadi JSON, mengirim ke Gemini, menampilkan jawaban, dan menyimpan riwayat.

## Yang dibuat
- `config/services.php` di repo sudah punya `gemini.api_key`, `gemini.model` (default `gemini-2.5-flash`), `gemini.base_url`, dan `gemini.timeout` (default 30). Isi `GEMINI_API_KEY` di `.env` (jangan commit) dan tambahkan `GEMINI_API_KEY=` kosong ke `.env.example`.
- `app/Services/AiAssistantService.php`:
  1. `klasifikasi(string $pertanyaan): string` → `stok` / `keuangan` / `umum` dengan kata kunci sederhana (stok, habis, barang, opname / laba, kas, untung, pengeluaran, penjualan, HPP). Jelaskan di Bab IV bahwa klasifikasi berbasis kata kunci.
  2. `kumpulkanKonteks(string $topik): array` memakai `LaporanService` dan query ringkas: stok menipis, 10 barang terlaris 30 hari, ringkasan penjualan bulan ini dan bulan lalu, laba rugi bulan berjalan dan sebelumnya, saldo kas, beban per kategori. Batasi ukuran (maks ±50 baris data).
  3. `tanya(string $pertanyaan, User $admin): RiwayatAi`: susun prompt, panggil Gemini lewat `Http::timeout(config('services.gemini.timeout'))->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])->post(config('services.gemini.base_url')."/models/{model}:generateContent", ...)`, simpan `pertanyaan`, `topik`, `konteks_data`, `jawaban`, `model`, `status`, `pesan_error`, `waktu_respons_ms`.
- Instruksi sistem (systemInstruction): "Kamu asisten toko. Jawab dalam bahasa Indonesia yang singkat dan jelas, hanya berdasarkan data JSON yang diberikan. Format uang Rupiah. Bila data tidak tersedia, katakan bahwa datanya tidak ada. Jangan mengarang angka."
- `AiAssistantController`: halaman chat dengan Tailwind (gelembung pertanyaan/jawaban, indikator "sedang menjawab" memakai Alpine.js; form pertanyaan, jawaban Markdown dirender aman di server dengan `Str::markdown()` dan opsi `html_input => 'strip'`, styling teks memakai plugin `@tailwindcss/typography` (class `prose`), contoh pertanyaan yang bisa diklik), riwayat (paginasi), detail riwayat (tampilkan konteks JSON untuk bukti pengujian).

## Aturan
- Gemini **tidak pernah** mengakses database dan API key tidak pernah dikirim ke browser.
- Timeout, HTTP error, atau respons kosong → `status = gagal`, `pesan_error` diisi, tampilkan "AI Assistant sedang tidak dapat dihubungi. Coba lagi nanti." Data tetap disimpan.
- Batasi 10 pertanyaan per menit per admin (RateLimiter).
- Pertanyaan maksimal 500 karakter.

## Feature test (`tests/Feature/AiAssistantTest.php`) memakai `Http::fake()`
1. Pertanyaan stok → topik `stok`, konteks berisi daftar stok menipis, jawaban tersimpan berstatus berhasil.
2. Gemini timeout/500 → status gagal, pesan error tersimpan, halaman menampilkan pesan ramah.
3. Request ke Gemini berisi konteks JSON dan tidak berisi data pengguna (password, email).
4. Kasir → 403.
5. Pertanyaan kosong atau > 500 karakter ditolak.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 5, dan docs/modul/09_ai_assistant.md. Kerjakan Modul 9:
1. AiAssistantService: klasifikasi kata kunci, kumpulkan konteks lewat LaporanService (ringkas, JSON),
   panggil Gemini API lewat Http client Laravel dengan timeout dan systemInstruction di file modul.
2. Simpan setiap pertanyaan ke riwayat_ai termasuk konteks, model, status, dan waktu respons;
   tangani error dengan status gagal.
3. Halaman chat (Tailwind + Alpine.js, jawaban Markdown via Str::markdown dengan html_input strip dan class prose
   dari @tailwindcss/typography), riwayat, dan detail riwayat khusus Administrator, rate limit 10/menit.
4. Feature test AiAssistantTest dengan Http::fake() untuk 5 skenario.
API key, base URL, model, dan timeout hanya dari config('services.gemini.*') yang sudah ada di repo. Jalankan php artisan test, centang Modul 9 di CLAUDE.md.
```

## Setelah semua modul
```
Semua modul sudah selesai. Jalankan php artisan migrate:fresh --seed dan php artisan test.
Lalu buat docs/pengujian_black_box.md berisi tabel skenario Black Box Testing per halaman
(no, fitur, skenario, input, hasil yang diharapkan, hasil, status) untuk Bab IV,
berdasarkan feature test dan hak akses di docs/rancangan.md.
```
