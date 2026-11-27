<x-layouts::app title="Peringatan Stok Minimum">
    <x-page-header title="Peringatan Stok Minimum" subtitle="Barang aktif dengan stok sama dengan atau di bawah stok minimum.">
        <x-button variant="secondary" :href="route('stok.index')">Cek Stok</x-button>
        <x-button icon="arrow-down-tray" :href="route('barang-masuk.create')">Catat Barang Masuk</x-button>
    </x-page-header>

    @if ($barang->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="Semua stok aman"
                description="Tidak ada barang aktif dengan stok di bawah atau sama dengan stok minimum." />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Min.', 'Perlu Dibeli', 'Status', 'Notifikasi', '']" :kanan="['Stok', 'Stok Min.', 'Perlu Dibeli']">
            @foreach ($barang as $item)
                <tr @class(['bg-danger-50/50' => $item->statusStok() === 'habis'])>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $item->nama_barang }}</td>
                    <td>{{ $item->kategori->nama_kategori }}</td>
                    <td class="text-right whitespace-nowrap"><span class="font-semibold">{{ $item->stok }}</span> {{ $item->satuan }}</td>
                    <td class="text-right">{{ $item->stok_minimum }}</td>
                    <td class="text-right">min. {{ $item->stok_minimum - $item->stok + 1 }}</td>
                    <td><x-badge :status="$item->statusStok()" /></td>
                    <td>
                        @if ($notif = $item->notifikasiStok->first())
                            <x-badge :status="$notif->status" />
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td><x-button variant="secondary" size="sm" :href="route('stok.kartu', $item)">Kartu Stok</x-button></td>
                </tr>
            @endforeach
        </x-table>
        <p class="-mt-3 text-xs text-gray-500">"Perlu Dibeli" = jumlah paling sedikit agar stok kembali di atas stok minimum.</p>
    @endif
</x-layouts::app>
