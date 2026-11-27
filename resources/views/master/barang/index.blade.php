<x-layouts::app title="Data Barang">
    <x-page-header title="Data Barang" subtitle="Stok berubah otomatis lewat barang masuk, penjualan, dan stok opname.">
        <x-button :href="route('barang.create')" icon="plus">Tambah Barang</x-button>
    </x-page-header>

    <x-filter-bar :cari="$cari" placeholder="Cari kode/barcode atau nama...">
        <x-select name="kategori" :options="$daftarKategori" :selected="$idKategori" placeholder="Semua kategori" class="w-48" />
        <x-select name="stok" :options="['menipis' => 'Stok menipis saja']" :selected="$filterStok" placeholder="Semua stok" class="w-48" />
    </x-filter-bar>

    @if ($barang->isEmpty())
        <x-card>
            <x-empty-state title="Barang tidak ditemukan"
                description="{{ request()->query() ? 'Tidak ada barang yang cocok dengan pencarian/filter.' : 'Belum ada data barang.' }}" />
        </x-card>
    @else
        {{-- Kolom HPP hanya untuk pemegang akses keuangan (Administrator). --}}
        @php($lihatHpp = auth()->user()->can('akses-keuangan'))
        <x-table :headers="array_values(array_filter(['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Min.', $lihatHpp ? 'HPP Rata-rata' : null, 'Harga Jual', 'Status', 'Aksi']))"
            :kanan="['Stok', 'Stok Min.', 'HPP Rata-rata', 'Harga Jual']">
            @foreach ($barang as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_barang }}</td>
                    <td>
                        <p class="font-medium text-gray-900">{{ $item->nama_barang }}</p>
                        <p class="text-xs text-gray-500">per {{ $item->satuan }}</p>
                    </td>
                    <td>{{ $item->kategori->nama_kategori }}</td>
                    {{-- Badge di bawah angka agar lebar kolom tidak bergeser. --}}
                    <td class="text-right whitespace-nowrap">
                        <span class="font-semibold text-gray-900 tabular-nums">{{ $item->stok }}</span>
                        @if ($item->statusStok() === 'habis')
                            <div class="mt-1"><x-badge status="habis">Stok habis</x-badge></div>
                        @elseif ($item->statusStok() === 'menipis')
                            <div class="mt-1"><x-badge status="menipis">Stok menipis</x-badge></div>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $item->stok_minimum }}</td>
                    @can('akses-keuangan')
                        <td class="text-right whitespace-nowrap tabular-nums">@rupiah($item->hpp_rata_rata)</td>
                    @endcan
                    <td class="text-right whitespace-nowrap tabular-nums">
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
                    <td>
                        <x-table.aksi>
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('barang.edit', $item)">Ubah</x-button>
                            @can('hapus-barang')
                                <x-delete-button :action="route('barang.destroy', $item)" :id="'barang-'.$item->id_barang"
                                    :nama="$item->nama_barang" />
                            @endcan
                        </x-table.aksi>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $barang->links() }}</div>
    @endif
</x-layouts::app>
