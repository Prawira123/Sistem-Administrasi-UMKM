<?php

namespace Tests\Unit;

use App\Services\BarangMasukService;
use App\Support\Uang;
use Tests\TestCase;

class HitungUangTest extends TestCase
{
    public function test_hpp_rata_rata_contoh_modul(): void
    {
        $service = app(BarangMasukService::class);

        // Stok 10 @ 3.000, masuk 20 @ 3.600 → (30.000 + 72.000) ÷ 30 = 3.400
        $this->assertSame('3400.00', $service->hitungHppRataRata(10, '3000.00', 20, '3600.00'));
        // Stok kosong → HPP = harga beli
        $this->assertSame('2500.00', $service->hitungHppRataRata(0, '0.00', 12, '2500.00'));
        // Pembulatan 2 desimal: (1 × 1.000 + 2 × 1.000,01) ÷ 3 = 1.000,00666… → 1.000,01
        $this->assertSame('1000.01', $service->hitungHppRataRata(1, '1000.00', 2, '1000.01'));
    }

    public function test_aritmetika_uang_tanpa_float(): void
    {
        $this->assertSame('0.30', Uang::tambah('0.10', '0.20'));
        $this->assertSame('3751.50', Uang::kali(3, '1250.50'));
        $this->assertSame('3333.33', Uang::bagi('10000', 3));
        $this->assertSame('6666.67', Uang::bagi('20000', 3));
        $this->assertSame('3600.00', Uang::normal('3600'));
        $this->assertSame(1, Uang::banding('0.01', '0'));
    }
}
