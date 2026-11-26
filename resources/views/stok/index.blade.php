<x-layouts::app title="Monitoring Stok">
    <x-page-header title="Cek Stok" subtitle="Stok real-time barang aktif. Stok berubah lewat barang masuk, penjualan, dan stok opname.">
        <x-button variant="secondary" icon="exclamation-triangle" :href="route('stok.peringatan')">Peringatan Stok Minimum</x-button>
        <x-button icon="clipboard-check" :href="route('stok-opname.index')">Stok Opname</x-button>
    </x-page-header>

    @php
        $ringkas = [
            'aman' => ['Aman', 'bg-success-100 text-success-700', 'check-circle'],
            'menipis' => ['Menipis', 'bg-warning-100 text-warning-700', 'exclamation-triangle'],
            'habis' => ['Habis', 'bg-danger-100 text-danger-700', 'x-circle'],
        ];
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ($ringkas as $status => [$label, $warna, $ikon])
            <a href="{{ route('stok.index', ['status' => $status]) }}"
                @class(['block rounded-xl hover:opacity-90', 'ring-2 ring-primary-500' => ($filter['status'] ?? null) === $status])>
                <x-card>
                    <div class="flex items-center gap-4">
                        <span class="flex size-11 items-center justify-center rounded-xl {{ $warna }}"><x-icon :name="$ikon" class="size-6" /></span>
                        <div>
                            <p class="text-sm text-gray-500">Stok {{ strtolower($label) }}</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $jumlah[$status] }}</p>
                        </div>
                    </div>
                </x-card>
            </a>
        @endforeach
    </div>

    <x-search-bar :value="$filter['q']" placeholder="Cari kode/barcode atau nama...">
        <x-select name="kategori" :options="$daftarKategori" :selected="$filter['kategori'] ?? ''" placeholder="Semua kategori" class="w-auto" />
        <x-select name="status" :options="['aman' => 'Aman', 'menipis' => 'Menipis', 'habis' => 'Habis']"
            :selected="$filter['status'] ?? ''" placeholder="Semua status" class="w-auto" />
    </x-search-bar>

    @if ($barang->isEmpty())
        <x-card>
            <x-empty-state title="Barang tidak ditemukan" description="Tidak ada barang aktif yang cocok dengan pencarian/filter." />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Min.', 'Status', '']">
            @foreach ($barang as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $item->nama_barang }}</td>
                    <td>{{ $item->kategori->nama_kategori }}</td>
                    <td class="text-right whitespace-nowrap"><span class="font-semibold">{{ $item->stok }}</span> {{ $item->satuan }}</td>
                    <td class="text-right">{{ $item->stok_minimum }}</td>
                    <td><x-badge :status="$item->statusStok()" /></td>
                    <td><x-button variant="secondary" size="sm" :href="route('stok.kartu', $item)">Kartu Stok</x-button></td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $barang->links() }}</div>
    @endif
</x-layouts::app>
