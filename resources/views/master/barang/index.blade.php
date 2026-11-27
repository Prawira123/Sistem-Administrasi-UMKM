<x-layouts::app title="Data Barang">
    <x-page-header title="Data Barang" subtitle="Stok berubah otomatis lewat barang masuk, penjualan, dan stok opname.">
        <x-button :href="route('barang.create')" icon="plus">Tambah Barang</x-button>
    </x-page-header>

    <x-search-bar :value="$cari" placeholder="Cari kode/barcode atau nama...">
        <x-select name="kategori" :options="$daftarKategori" :selected="$idKategori" placeholder="Semua kategori" class="w-auto" />
        <x-select name="stok" :options="['menipis' => 'Stok menipis saja']" :selected="$filterStok" placeholder="Semua stok" class="w-auto" />
    </x-search-bar>

    @if ($barang->isEmpty())
        <x-card>
            <x-empty-state title="Barang tidak ditemukan"
                description="{{ request()->query() ? 'Tidak ada barang yang cocok dengan pencarian/filter.' : 'Belum ada data barang.' }}" />
        </x-card>
    @else
        {{-- Kolom HPP hanya untuk pemegang akses keuangan (Administrator). --}}
        @php($lihatHpp = auth()->user()->can('akses-keuangan'))
        <x-table :headers="array_values(array_filter(['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Min.', $lihatHpp ? 'HPP Rata-rata' : null, 'Harga Jual', 'Status', 'Aksi']))">
            @foreach ($barang as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_barang }}</td>
                    <td>
                        <p class="font-medium text-gray-900">{{ $item->nama_barang }}</p>
                        <p class="text-xs text-gray-500">per {{ $item->satuan }}</p>
                    </td>
                    <td>{{ $item->kategori->nama_kategori }}</td>
                    <td class="text-right whitespace-nowrap">
                        <span class="font-semibold">{{ $item->stok }}</span>
                        @if ($item->statusStok() === 'habis')
                            <x-badge status="habis" class="ml-1">Stok habis</x-badge>
                        @elseif ($item->statusStok() === 'menipis')
                            <x-badge status="menipis" class="ml-1">Stok menipis</x-badge>
                        @endif
                    </td>
                    <td class="text-right">{{ $item->stok_minimum }}</td>
                    @can('akses-keuangan')
                        <td class="text-right whitespace-nowrap">@rupiah($item->hpp_rata_rata)</td>
                    @endcan
                    <td class="text-right whitespace-nowrap">
                        @if ((float) $item->harga_jual > 0)
                            @rupiah($item->harga_jual)
                        @else
                            @can('ubah-harga-jual')
                                <x-badge status="menipis">Harga belum diatur</x-badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endcan
                        @endif
                    </td>
                    <td><x-badge :status="$item->status" /></td>
                    <td class="whitespace-nowrap">
                        <div class="flex gap-2">
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('barang.edit', $item)">Ubah</x-button>
                            @can('hapus-barang')
                                <x-delete-button :action="route('barang.destroy', $item)" :id="'barang-'.$item->id_barang"
                                    :nama="$item->nama_barang" />
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $barang->links() }}</div>
    @endif
</x-layouts::app>
