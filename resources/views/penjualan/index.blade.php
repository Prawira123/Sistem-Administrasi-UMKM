<x-layouts::app title="Penjualan">
    <x-page-header title="Riwayat Penjualan" :subtitle="auth()->user()->isAdministrator()
        ? 'Transaksi penjualan semua kasir; transaksi batal tidak dihitung di laporan.'
        : 'Transaksi penjualan yang Anda layani.'">
        <x-button :href="route('penjualan.create')" icon="shopping-cart">Transaksi Baru</x-button>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <x-form-field for="dari" label="Dari tanggal">
            <x-input name="dari" type="date" :value="$filter['dari'] ?? ''" />
        </x-form-field>
        <x-form-field for="sampai" label="Sampai tanggal">
            <x-input name="sampai" type="date" :value="$filter['sampai'] ?? ''" />
        </x-form-field>
        @if (auth()->user()->isAdministrator())
            <x-form-field for="kasir" label="Kasir">
                <x-select name="kasir" :options="$daftarKasir" :selected="$filter['kasir'] ?? ''" placeholder="Semua kasir" />
            </x-form-field>
        @endif
        <x-form-field for="status" label="Status">
            <x-select name="status" :options="['selesai' => 'Selesai', 'batal' => 'Batal']" :selected="$filter['status'] ?? ''" placeholder="Semua status" />
        </x-form-field>
        <div class="flex gap-2">
            <x-button variant="secondary">Terapkan</x-button>
            @if (request()->query())
                <x-button variant="secondary" :href="route('penjualan.index')">Reset</x-button>
            @endif
        </div>
    </form>

    @if ($penjualan->isEmpty())
        <x-card>
            <x-empty-state icon="shopping-cart" title="Belum ada penjualan"
                description="{{ request()->query() ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Mulai transaksi dari halaman kasir.' }}" />
        </x-card>
    @else
        <x-table :headers="['No. Transaksi', 'Waktu', 'Kasir', 'Unit', 'Total', 'Bayar', 'Status', '']">
            @foreach ($penjualan as $item)
                <tr @class(['text-gray-400' => $item->status === 'batal'])>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->no_transaksi }}</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($item->tanggal_penjualan) }}</td>
                    <td>{{ $item->user->nama }}</td>
                    <td class="text-right">{{ $item->total_item }}</td>
                    <td @class(['text-right font-medium whitespace-nowrap', 'line-through' => $item->status === 'batal'])>@rupiah($item->total_harga)</td>
                    <td><x-badge status="info">{{ $item->metode_pembayaran === 'qris' ? 'QRIS' : ucfirst($item->metode_pembayaran) }}</x-badge></td>
                    <td><x-badge :status="$item->status" /></td>
                    <td class="whitespace-nowrap">
                        <x-button variant="secondary" size="sm" :href="route('penjualan.show', $item)">Detail</x-button>
                        <x-button variant="secondary" size="sm" icon="printer" :href="route('penjualan.nota', $item)" target="_blank">Nota</x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $penjualan->links() }}</div>
    @endif
</x-layouts::app>
