@php
    $daftar = [
        ['route' => 'laporan.pergerakan-stok', 'icon' => 'clipboard-check', 'judul' => 'Pergerakan Stok',
            'isi' => 'Mutasi kartu stok per barang: stok awal, masuk, keluar, saldo, dan HPP satuan.'],
        ['route' => 'laporan.arus-kas', 'icon' => 'banknotes', 'judul' => 'Arus Kas',
            'isi' => 'Saldo awal, kas masuk dan keluar per kategori, saldo akhir; opsional rincian per transaksi.'],
        ['route' => 'laporan.laba-rugi', 'icon' => 'calculator', 'judul' => 'Laba Rugi Bulanan',
            'isi' => 'Pendapatan − HPP = laba kotor; − beban operasional per kategori = laba bersih.'],
        ['route' => 'laporan.penjualan', 'icon' => 'shopping-cart', 'judul' => 'Penjualan',
            'isi' => 'Rekap per hari, barang terlaris, dan per metode pembayaran.'],
        ['route' => 'laporan.stok-minimum', 'icon' => 'exclamation-triangle', 'judul' => 'Stok Minimum',
            'isi' => 'Barang aktif dengan stok di bawah atau sama dengan stok minimum.'],
    ];
@endphp
<x-layouts::app title="Laporan">
    <x-page-header title="Laporan" subtitle="Dihitung langsung dari data transaksi. Setiap laporan bisa dicetak dan diunduh sebagai PDF." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($daftar as $l)
            <a href="{{ route($l['route']) }}" class="group">
                <x-card class="h-full transition group-hover:border-primary-300 group-hover:shadow-md">
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700">
                            <x-icon :name="$l['icon']" class="size-6" />
                        </span>
                        <div>
                            <p class="font-semibold text-gray-900 group-hover:text-primary-700">{{ $l['judul'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $l['isi'] }}</p>
                        </div>
                    </div>
                </x-card>
            </a>
        @endforeach
    </div>
</x-layouts::app>
