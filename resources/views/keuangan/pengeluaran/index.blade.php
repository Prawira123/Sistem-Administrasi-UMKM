<x-layouts::app title="Pengeluaran Operasional">
    <x-page-header title="Pengeluaran Operasional" subtitle="Beban usaha di luar pembelian barang. Setiap pengeluaran otomatis tercatat sebagai kas keluar.">
        <x-button variant="secondary" icon="tag" :href="route('kategori-pengeluaran.index')">Kategori</x-button>
        <x-button :href="route('pengeluaran.create')" icon="plus">Catat Pengeluaran</x-button>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <x-form-field for="dari" label="Dari tanggal">
            <x-input name="dari" type="date" :value="$filter['dari'] ?? ''" />
        </x-form-field>
        <x-form-field for="sampai" label="Sampai tanggal">
            <x-input name="sampai" type="date" :value="$filter['sampai'] ?? ''" />
        </x-form-field>
        <x-form-field for="kategori" label="Kategori">
            <x-select name="kategori" :options="$daftarKategori" :selected="$filter['kategori'] ?? ''" placeholder="Semua kategori" />
        </x-form-field>
        <div class="flex gap-2">
            <x-button variant="secondary">Terapkan</x-button>
            @if (request()->query())
                <x-button variant="secondary" :href="route('pengeluaran.index')">Reset</x-button>
            @endif
        </div>
    </form>

    @if ($pengeluaran->isEmpty())
        <x-card>
            <x-empty-state icon="banknotes" title="Belum ada pengeluaran"
                description="{{ request()->query() ? 'Tidak ada pengeluaran yang cocok dengan filter.' : 'Catat beban operasional seperti listrik, air, atau sewa.' }}" />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Tanggal', 'Kategori', 'Deskripsi', 'Metode', 'Jumlah', 'Bukti', 'Aksi']">
            @foreach ($pengeluaran as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_pengeluaran }}</td>
                    <td class="whitespace-nowrap">@tanggal($item->tanggal_pengeluaran)</td>
                    <td>{{ $item->kategori->nama_kategori }}</td>
                    <td>{{ $item->deskripsi }}</td>
                    <td>{{ ucfirst($item->metode_pembayaran) }}</td>
                    <td class="text-right font-medium whitespace-nowrap text-danger-700">@rupiah($item->jumlah)</td>
                    <td>
                        @if ($item->bukti)
                            <a href="{{ route('pengeluaran.bukti', $item) }}" target="_blank" class="text-sm font-medium text-primary-700 hover:underline">Lihat</a>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($item->isBulanBerjalan())
                            <div class="flex gap-2">
                                <x-button variant="secondary" size="sm" icon="pencil" :href="route('pengeluaran.edit', $item)">Ubah</x-button>
                                <x-delete-button :action="route('pengeluaran.destroy', $item)" :id="'pengeluaran-'.$item->id_pengeluaran"
                                    :nama="$item->kode_pengeluaran.' ('.\App\Support\Format::rupiah($item->jumlah).')'" />
                            </div>
                        @else
                            {{-- Bulan sebelumnya terkunci karena laporan periode itu sudah berjalan. --}}
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400" title="{{ \App\Services\PengeluaranService::PESAN_BULAN_LALU }}">
                                <x-icon name="lock-closed" class="size-4" /> Terkunci
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold text-gray-900">
                <td colspan="5" class="text-right">Total {{ request()->query() ? 'sesuai filter' : 'semua' }}</td>
                <td class="text-right whitespace-nowrap">@rupiah($total)</td>
                <td colspan="2"></td>
            </tr>
        </x-table>

        <div class="mt-4">{{ $pengeluaran->links() }}</div>
    @endif
</x-layouts::app>
