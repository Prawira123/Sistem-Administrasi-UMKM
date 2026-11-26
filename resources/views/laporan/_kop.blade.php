{{-- Kop laporan web: judul halaman + tombol (disembunyikan saat dicetak) + kop toko (ikut tercetak). --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <a href="{{ route('laporan.index') }}" class="text-sm font-medium text-primary-700 hover:underline">&larr; Semua laporan</a>
    <div class="flex gap-2">
        <x-button type="button" variant="secondary" icon="printer" x-data x-on:click="window.print()">Cetak</x-button>
        <x-button icon="arrow-down-tray" :href="request()->fullUrlWithQuery(['unduh' => 'pdf'])">Unduh PDF</x-button>
    </div>
</div>

<div class="mb-4 border-b-2 border-gray-800 pb-3 text-center">
    <p class="text-lg font-bold text-gray-900 uppercase">{{ $kop['toko'] }}</p>
    <p class="text-xs text-gray-600">{{ $kop['alamat'] }}</p>
</div>
<div class="mb-5 flex flex-wrap items-end justify-between gap-2">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ $kop['judul'] }}</h1>
        <p class="text-sm text-gray-600">{{ $kop['periode'] }}</p>
    </div>
    <p class="text-xs text-gray-500">Dicetak {{ $kop['dicetak'] }} oleh {{ $kop['pencetak'] }}</p>
</div>
