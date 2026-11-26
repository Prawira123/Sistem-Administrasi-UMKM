<x-layouts::app title="Laporan Stok Minimum">
    @include('laporan._kop')

    @if ($barang->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="Semua stok aman" description="Tidak ada barang aktif dengan stok di bawah atau sama dengan stok minimum." />
        </x-card>
    @else
        <x-table :headers="['No', 'Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Minimum', 'Kekurangan', 'Status']">
            @foreach ($barang as $i => $b)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-mono text-xs">{{ $b->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $b->nama_barang }}</td>
                    <td>{{ $b->kategori->nama_kategori }}</td>
                    <td class="text-right">{{ $b->stok }} {{ $b->satuan }}</td>
                    <td class="text-right">{{ $b->stok_minimum }}</td>
                    <td class="text-right">{{ max(0, $b->stok_minimum - $b->stok) }}</td>
                    <td><x-badge :status="$b->statusStok()" /></td>
                </tr>
            @endforeach
        </x-table>
        <p class="mt-3 text-xs text-gray-500">Kekurangan = stok minimum − stok saat ini. Barang tampil bila stok ≤ stok minimum dan berstatus aktif.</p>
    @endif
</x-layouts::app>
