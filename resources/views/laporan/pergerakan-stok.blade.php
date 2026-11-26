<x-layouts::app title="Laporan Pergerakan Stok">
    @include('laporan._kop')

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
        <x-form-field for="dari" label="Dari tanggal">
            <x-input name="dari" type="date" :value="$dari->toDateString()" />
        </x-form-field>
        <x-form-field for="sampai" label="Sampai tanggal">
            <x-input name="sampai" type="date" :value="$sampai->toDateString()" />
        </x-form-field>
        <x-form-field for="barang" label="Barang">
            <x-select name="barang" :options="$daftarBarang" :selected="$idBarang" placeholder="Semua barang" />
        </x-form-field>
        <x-button variant="secondary">Tampilkan</x-button>
    </form>

    @forelse ($bagian as $b)
        <x-card class="mb-6 break-inside-avoid" :padding="false">
            <x-slot:title>
                {{ $b['barang']->nama_barang }}
                <span class="font-mono text-xs font-normal text-gray-500">{{ $b['barang']->kode_barang }} · {{ $b['barang']->satuan }}</span>
            </x-slot:title>
            <x-table :headers="['Tanggal', 'Jenis', 'Sumber', 'Keterangan', 'Masuk', 'Keluar', 'Saldo', 'HPP Satuan']" :polos="true">
                <tr class="bg-gray-50 italic">
                    <td colspan="6">Stok awal periode</td>
                    <td class="text-right font-medium">{{ $b['stok_awal'] }}</td>
                    <td></td>
                </tr>
                @foreach ($b['mutasi'] as $m)
                    <tr>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($m['tanggal']) }}</td>
                        <td><x-badge :status="$m['jenis']" /></td>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $m['sumber'] }}</td>
                        <td class="min-w-48 text-xs">{{ $m['keterangan'] }}</td>
                        <td class="text-right text-success-700">{{ $m['masuk'] ?: '' }}</td>
                        <td class="text-right text-danger-700">{{ $m['keluar'] ?: '' }}</td>
                        <td class="text-right font-medium">{{ $m['saldo'] }}</td>
                        <td class="text-right whitespace-nowrap">@rupiah($m['hpp_satuan'])</td>
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold text-gray-900">
                    <td colspan="4">Total / stok akhir periode</td>
                    <td class="text-right">{{ $b['total_masuk'] }}</td>
                    <td class="text-right">{{ $b['total_keluar'] }}</td>
                    <td class="text-right">{{ $b['stok_akhir'] }}</td>
                    <td></td>
                </tr>
            </x-table>
        </x-card>
    @empty
        <x-card>
            <x-empty-state icon="clipboard-check" title="Tidak ada pergerakan stok" description="Tidak ada mutasi stok pada periode ini." />
        </x-card>
    @endforelse
</x-layouts::app>
