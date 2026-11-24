{{--
    Nota penjualan ukuran struk thermal 58 mm / 80 mm.
    - @page mengatur ukuran kertas saat dicetak (lebar $ukuran mm, tinggi mengikuti isi).
    - Elemen dengan class print:hidden (tombol) tidak ikut tercetak.
--}}
@php
    $metode = $penjualan->metode_pembayaran === 'qris' ? 'QRIS' : ucfirst($penjualan->metode_pembayaran);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ $penjualan->no_transaksi }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: {{ $ukuran }}mm auto; margin: 0; }
        .nota { width: {{ $ukuran }}mm; }
    </style>
</head>
<body class="bg-gray-100 font-mono text-gray-900 print:bg-white">
    <div class="mx-auto my-4 flex max-w-md flex-wrap justify-center gap-2 px-4 font-sans print:hidden">
        <button type="button" onclick="window.print()"
            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">Cetak</button>
        <a href="{{ route('penjualan.nota', [$penjualan, 'ukuran' => $ukuran === 58 ? 80 : 58]) }}"
            class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Ganti ke {{ $ukuran === 58 ? 80 : 58 }} mm</a>
        <a href="{{ route('penjualan.create') }}"
            class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Transaksi Baru</a>
    </div>

    <div @class([
        'nota mx-auto bg-white px-3 py-4 leading-snug shadow print:my-0 print:shadow-none',
        'text-[10px]' => $ukuran === 58,
        'text-xs' => $ukuran === 80,
    ])>
        <div class="text-center">
            <p class="text-sm font-bold">{{ config('app.name') }}</p>
            <p>Sekretariat DPRD Kab. Badung</p>
        </div>

        <div class="my-2 border-t border-dashed border-gray-500"></div>

        <table class="w-full">
            <tr><td>No</td><td class="text-right">{{ $penjualan->no_transaksi }}</td></tr>
            <tr><td>Tanggal</td><td class="text-right">{{ \App\Support\Format::tanggalWaktu($penjualan->tanggal_penjualan) }}</td></tr>
            <tr><td>Kasir</td><td class="text-right">{{ $penjualan->user->nama }}</td></tr>
        </table>

        <div class="my-2 border-t border-dashed border-gray-500"></div>

        @foreach ($penjualan->detail as $detail)
            <div class="mb-1">
                <p>{{ $detail->barang->nama_barang }}</p>
                <div class="flex justify-between">
                    <span>{{ $detail->jumlah }} x {{ number_format((float) $detail->harga_jual, 0, ',', '.') }}</span>
                    <span>{{ number_format((float) $detail->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach

        <div class="my-2 border-t border-dashed border-gray-500"></div>

        <table class="w-full">
            <tr class="font-bold"><td>TOTAL</td><td class="text-right">@rupiah($penjualan->total_harga)</td></tr>
            <tr><td>{{ $metode }}</td><td class="text-right">@rupiah($penjualan->jumlah_bayar)</td></tr>
            <tr><td>Kembali</td><td class="text-right">@rupiah($penjualan->kembalian)</td></tr>
            <tr><td>Jumlah unit</td><td class="text-right">{{ $penjualan->total_item }}</td></tr>
        </table>

        @if ($penjualan->status === 'batal')
            <p class="mt-2 border border-gray-900 py-1 text-center font-bold">*** DIBATALKAN ***</p>
        @endif

        <div class="my-2 border-t border-dashed border-gray-500"></div>
        <p class="text-center">Terima kasih atas kunjungan Anda</p>
    </div>
</body>
</html>
