{{-- Layout PDF (dompdf). CSS biasa di <style>, karena dompdf tidak mendukung Tailwind v4. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $kop['judul'] }}</title>
    <style>
        @page { margin: 24px 28px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
        .kop { text-align: center; border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 10px; }
        .kop .toko { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 9px; color: #4b5563; }
        .judul { font-size: 13px; font-weight: bold; margin: 0; }
        .periode { font-size: 10px; color: #374151; margin: 2px 0 0; }
        .cetak { font-size: 8.5px; color: #6b7280; margin: 2px 0 10px; }
        h3 { font-size: 10.5px; margin: 14px 0 4px; }
        h3 small { font-weight: normal; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; }
        .kanan { text-align: right; white-space: nowrap; }
        .tengah { text-align: center; }
        .tebal { font-weight: bold; }
        .abu { background: #f9fafb; }
        .miring { font-style: italic; }
        .indent { padding-left: 18px; }
        .hijau { color: #047857; }
        .merah { color: #b91c1c; }
        .ringkas { width: 60%; }
        .ringkas td { border: none; border-bottom: 1px solid #e5e7eb; }
        .total td { border-top: 1.5px solid #111827; font-weight: bold; font-size: 10.5px; }
        .peringatan { border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; padding: 6px 8px; margin-bottom: 10px; }
        .catatan { font-size: 8.5px; color: #6b7280; }
        .halaman { position: fixed; bottom: -22px; right: 0; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="toko">{{ $kop['toko'] }}</div>
        <div class="alamat">{{ $kop['alamat'] }}</div>
    </div>
    <p class="judul">{{ $kop['judul'] }}</p>
    <p class="periode">{{ $kop['periode'] }}</p>
    <p class="cetak">Dicetak {{ $kop['dicetak'] }} oleh {{ $kop['pencetak'] }}</p>

    @yield('isi')

    <div class="halaman">{{ $kop['judul'] }} &middot; {{ $kop['dicetak'] }}</div>
</body>
</html>
