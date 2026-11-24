<?php

namespace Tests\Unit;

use App\Support\Format;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp15.000', Format::rupiah(15000));
        $this->assertSame('Rp1.234.567', Format::rupiah('1234567.00'));
        $this->assertSame('Rp0', Format::rupiah(null));
        $this->assertSame('-Rp2.500', Format::rupiah(-2500));
    }

    public function test_format_tanggal_zona_waktu_makassar(): void
    {
        $this->assertSame('24/11/2026', Format::tanggal('2026-11-24'));
        // 20:00 UTC = 04:00 WITA hari berikutnya
        $this->assertSame('25/11/2026', Format::tanggal(Carbon::parse('2026-11-24 20:00:00', 'UTC')));
        $this->assertSame('-', Format::tanggal(null));
    }
}
