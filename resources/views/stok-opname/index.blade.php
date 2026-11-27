<x-layouts::app title="Stok Opname">
    <x-page-header title="Stok Opname" subtitle="Pencocokan stok sistem dengan stok fisik. Stok baru berubah setelah disetujui Administrator.">
        <x-button :href="route('stok-opname.create')" icon="plus">Buat Stok Opname</x-button>
    </x-page-header>

    <x-filter-bar>
        <x-select name="status" :options="['draft' => 'Draft', 'diajukan' => 'Diajukan', 'final' => 'Final']"
            :selected="$filter['status'] ?? ''" placeholder="Semua status" class="w-48" />
    </x-filter-bar>

    @if ($opname->isEmpty())
        <x-card>
            <x-empty-state icon="clipboard-check" title="Belum ada stok opname"
                description="Buat draft, hitung stok fisik di rak, lalu ajukan untuk disetujui Administrator." />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Tanggal', 'Dibuat oleh', 'Barang', 'Berselisih', 'Status', 'Disetujui', '']" :kanan="['Barang', 'Berselisih']">
            @foreach ($opname as $item)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $item->kode_opname }}</td>
                    <td class="whitespace-nowrap">@tanggal($item->tanggal_opname)</td>
                    <td>{{ $item->user->nama }}</td>
                    <td class="text-right">{{ $item->detail_count }}</td>
                    <td class="text-right">
                        <span @class(['font-semibold text-warning-700' => $item->berselisih_count > 0])>{{ $item->berselisih_count }}</span>
                    </td>
                    <td><x-badge :status="$item->status" /></td>
                    <td class="text-xs whitespace-nowrap">
                        @if ($item->approver)
                            {{ $item->approver->nama }}<br>{{ \App\Support\Format::tanggalWaktu($item->disetujui_pada) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <x-table.aksi>
                            <x-button variant="secondary" size="sm" :href="route('stok-opname.show', $item)">Detail</x-button>
                            @can('ubah-opname', $item)
                                <x-button size="sm" icon="pencil" :href="route('stok-opname.edit', $item)">Input Hitungan</x-button>
                            @endcan
                        </x-table.aksi>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $opname->links() }}</div>
    @endif
</x-layouts::app>
