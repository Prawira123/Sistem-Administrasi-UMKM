<x-layouts::app title="Laporan Penjualan">
    @include('laporan._kop')
    @include('laporan._filter-periode')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm text-gray-500">Total penjualan</p>
            <p class="text-xl font-semibold text-gray-900">@rupiah($total)</p>
        </x-card>
        <x-card>
            <p class="text-sm text-gray-500">Transaksi selesai</p>
            <p class="text-xl font-semibold text-gray-900">{{ number_format($jumlah_transaksi, 0, ',', '.') }}</p>
            @if ($jumlah_batal)
                <p class="text-xs text-gray-500">{{ $jumlah_batal }} transaksi batal tidak dihitung</p>
            @endif
        </x-card>
        <x-card>
            <p class="text-sm text-gray-500">Unit terjual</p>
            <p class="text-xl font-semibold text-gray-900">{{ number_format($total_unit, 0, ',', '.') }}</p>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-card title="Per hari" :padding="false">
            @if ($per_hari->isEmpty())
                <x-empty-state icon="shopping-cart" title="Tidak ada penjualan" description="Belum ada penjualan selesai pada periode ini." />
            @else
                <x-table :headers="['Tanggal', 'Transaksi', 'Unit', 'Total']" :polos="true">
                    @foreach ($per_hari as $h)
                        <tr>
                            <td>@tanggal($h['tanggal'])</td>
                            <td class="text-right">{{ $h['transaksi'] }}</td>
                            <td class="text-right">{{ $h['unit'] }}</td>
                            <td class="text-right whitespace-nowrap">@rupiah($h['total'])</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Per metode pembayaran" :padding="false">
                <x-table :headers="['Metode', 'Transaksi', 'Total']" :polos="true">
                    @forelse ($per_metode as $m)
                        <tr>
                            <td>{{ strtoupper($m['metode']) === 'QRIS' ? 'QRIS' : ucfirst($m['metode']) }}</td>
                            <td class="text-right">{{ $m['transaksi'] }}</td>
                            <td class="text-right whitespace-nowrap">@rupiah($m['total'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-gray-500">Tidak ada</td></tr>
                    @endforelse
                </x-table>
            </x-card>

            <x-card title="Barang terlaris" :padding="false">
                <x-table :headers="['#', 'Barang', 'Qty', 'Omzet']" :polos="true">
                    @forelse ($per_barang as $i => $b)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <p class="font-medium text-gray-900">{{ $b['nama'] }}</p>
                                <p class="font-mono text-xs text-gray-500">{{ $b['kode'] }}</p>
                            </td>
                            <td class="text-right whitespace-nowrap">{{ $b['qty'] }} {{ $b['satuan'] }}</td>
                            <td class="text-right whitespace-nowrap">@rupiah($b['omzet'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-gray-500">Tidak ada</td></tr>
                    @endforelse
                </x-table>
            </x-card>
        </div>
    </div>
</x-layouts::app>
