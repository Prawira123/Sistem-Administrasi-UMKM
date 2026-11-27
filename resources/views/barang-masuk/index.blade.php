<x-layouts::app title="Barang Masuk">
    <x-page-header title="Barang Masuk" subtitle="Faktur pembelian barang dari supplier.">
        <x-button :href="route('barang-masuk.create')" icon="plus">Catat Barang Masuk</x-button>
    </x-page-header>

    <x-filter-bar :dari="$filter['dari'] ?? ''" :sampai="$filter['sampai'] ?? ''">
        <x-select name="supplier" :options="$daftarSupplier" :selected="$filter['supplier'] ?? ''" placeholder="Semua supplier" class="w-48" />
    </x-filter-bar>

    @if ($barangMasuk->isEmpty())
        <x-card>
            <x-empty-state icon="arrow-down-tray" title="Belum ada barang masuk"
                description="{{ request()->query() ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Catat faktur pembelian pertama dari supplier.' }}" />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Tanggal', 'Supplier', 'No. Faktur', 'Item', 'Total', 'Bayar', 'Dicatat oleh', '']" :kanan="['Item', 'Total']">
            @foreach ($barangMasuk as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_barang_masuk }}</td>
                    <td class="whitespace-nowrap">@tanggal($item->tanggal_masuk)</td>
                    <td>{{ $item->supplier->nama_supplier }}</td>
                    <td>{{ $item->no_faktur ?? '-' }}</td>
                    <td class="text-right">{{ $item->detail_count }}</td>
                    <td class="text-right font-medium whitespace-nowrap">@rupiah($item->total_pembelian)</td>
                    <td><x-badge status="info">{{ ucfirst($item->metode_pembayaran) }}</x-badge></td>
                    <td>{{ $item->user->nama }}</td>
                    <td>
                        <x-button variant="secondary" size="sm" :href="route('barang-masuk.show', $item)">Detail</x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $barangMasuk->links() }}</div>
    @endif
</x-layouts::app>
