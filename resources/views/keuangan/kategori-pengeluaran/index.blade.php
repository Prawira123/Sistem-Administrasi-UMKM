<x-layouts::app title="Kategori Pengeluaran">
    <x-page-header title="Kategori Pengeluaran" subtitle="Jenis beban operasional; menjadi rincian beban di laporan laba rugi.">
        <x-button variant="secondary" :href="route('pengeluaran.index')">Pengeluaran</x-button>
        <x-button :href="route('kategori-pengeluaran.create')" icon="plus">Tambah Kategori</x-button>
    </x-page-header>

    <x-filter-bar :cari="$cari" placeholder="Cari nama kategori..." />

    @if ($kategori->isEmpty())
        <x-card>
            <x-empty-state title="Kategori tidak ditemukan"
                description="{{ $cari ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Belum ada kategori pengeluaran.' }}" />
        </x-card>
    @else
        <x-table :headers="['Nama Kategori', 'Keterangan', 'Jumlah Pengeluaran', 'Aksi']" :kanan="['Jumlah Pengeluaran']">
            @foreach ($kategori as $item)
                <tr>
                    <td class="font-medium text-gray-900">{{ $item->nama_kategori }}</td>
                    <td>{{ $item->keterangan ?? '-' }}</td>
                    <td class="text-right">{{ $item->pengeluaran_count }}</td>
                    <td>
                        <x-table.aksi>
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('kategori-pengeluaran.edit', $item)">Ubah</x-button>
                            <x-delete-button :action="route('kategori-pengeluaran.destroy', $item)" :id="'kategori-pengeluaran-'.$item->id_kategori_pengeluaran"
                                :nama="$item->nama_kategori" />
                        </x-table.aksi>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $kategori->links() }}</div>
    @endif
</x-layouts::app>
