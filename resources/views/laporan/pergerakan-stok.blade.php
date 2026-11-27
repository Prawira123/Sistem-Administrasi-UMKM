<x-layouts::laporan title="Laporan Pergerakan Stok" :kop="$kop">

    <x-filter-bar :dari="$dari->toDateString()" :sampai="$sampai->toDateString()" tombol="Tampilkan" :reset="false">
        <x-select name="barang" :options="$daftarBarang" :selected="$idBarang" placeholder="Semua barang" class="w-48" />
    </x-filter-bar>

    @forelse ($bagian as $b)
        <x-card class="break-inside-avoid" :padding="false">
            <x-slot:title>
                {{ $b['barang']->nama_barang }}
                <span class="font-mono text-xs font-normal text-gray-500">{{ $b['barang']->kode_barang }} · {{ $b['barang']->satuan }}</span>
            </x-slot:title>
            <x-table :headers="['Tanggal', 'Jenis', 'Sumber', 'Keterangan', 'Masuk', 'Keluar', 'Saldo', 'HPP Satuan']" :kanan="['Masuk', 'Keluar', 'Saldo', 'HPP Satuan']" :polos="true">
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
                <tr class="baris-total bg-gray-50 font-semibold text-gray-900">
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
</x-layouts::laporan>
