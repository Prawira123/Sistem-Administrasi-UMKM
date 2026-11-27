<x-layouts::app title="Data Supplier">
    <x-page-header title="Data Supplier" subtitle="Pemasok barang. Supplier nonaktif tidak muncul di form barang masuk.">
        <x-button :href="route('supplier.create')" icon="plus">Tambah Supplier</x-button>
    </x-page-header>

    <x-filter-bar :cari="$cari" placeholder="Cari kode, nama, atau kontak..." />

    @if ($supplier->isEmpty())
        <x-card>
            <x-empty-state title="Supplier tidak ditemukan"
                description="{{ $cari ? 'Tidak ada supplier yang cocok dengan pencarian.' : 'Belum ada data supplier.' }}" />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Nama Supplier', 'Kontak', 'Telepon', 'Status', 'Aksi']">
            @foreach ($supplier as $item)
                <tr>
                    <td class="font-mono text-xs">{{ $item->kode_supplier }}</td>
                    <td>
                        <p class="font-medium text-gray-900">{{ $item->nama_supplier }}</p>
                        @if ($item->alamat)
                            <p class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($item->alamat, 60) }}</p>
                        @endif
                    </td>
                    <td>{{ $item->nama_kontak ?? '-' }}</td>
                    <td class="whitespace-nowrap">{{ $item->no_telepon ?? '-' }}</td>
                    <td><x-badge :status="$item->status" /></td>
                    <td>
                        <x-table.aksi>
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('supplier.edit', $item)">Ubah</x-button>
                            <x-delete-button :action="route('supplier.destroy', $item)" :id="'supplier-'.$item->id_supplier"
                                :nama="$item->nama_supplier" />
                        </x-table.aksi>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $supplier->links() }}</div>
    @endif
</x-layouts::app>
