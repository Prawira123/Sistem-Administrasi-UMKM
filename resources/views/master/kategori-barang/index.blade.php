<x-layouts::app title="Kategori Barang">
    <x-page-header title="Kategori Barang" subtitle="Pengelompokan barang dagangan">
        <x-button :href="route('kategori-barang.create')" icon="plus">Tambah Kategori</x-button>
    </x-page-header>

    <x-search-bar :value="$cari" placeholder="Cari nama kategori..." />

    @if ($kategori->isEmpty())
        <x-card>
            <x-empty-state title="Kategori tidak ditemukan"
                description="{{ $cari ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Belum ada kategori barang.' }}" />
        </x-card>
    @else
        <x-table :headers="['Nama Kategori', 'Keterangan', 'Jumlah Barang', 'Aksi']">
            @foreach ($kategori as $item)
                <tr>
                    <td class="font-medium text-gray-900">{{ $item->nama_kategori }}</td>
                    <td>{{ $item->keterangan ?? '-' }}</td>
                    <td>{{ $item->barang_count }}</td>
                    <td class="whitespace-nowrap">
                        <div class="flex gap-2">
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('kategori-barang.edit', $item)">Ubah</x-button>
                            <x-delete-button :action="route('kategori-barang.destroy', $item)" :id="'kategori-'.$item->id_kategori"
                                :nama="$item->nama_kategori" />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $kategori->links() }}</div>
    @endif
</x-layouts::app>
