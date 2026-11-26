<x-layouts::app title="Perhitungan HPP">
    <x-page-header title="Perhitungan HPP Periodik"
        subtitle="HPP = Persediaan Awal + Pembelian − Persediaan Akhir, per barang per bulan, dari kartu stok dan barang masuk." />

    <x-card class="mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-form-field for="bulan" label="Periode">
                    <x-input name="bulan" type="month" :max="now()->format('Y-m')" :value="$bulan->format('Y-m')" />
                </x-form-field>
                <x-button variant="secondary">Tampilkan</x-button>
            </form>

            @if ($hasil->isEmpty())
                <form method="POST" action="{{ route('hpp.hitung') }}">
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $bulan->format('Y-m') }}">
                    <x-button icon="calculator">Hitung HPP {{ $bulan->translatedFormat('F Y') }}</x-button>
                </form>
            @else
                <x-button type="button" icon="calculator" x-data x-on:click="$dispatch('buka-modal', 'hitung-ulang-hpp')">
                    Hitung Ulang
                </x-button>
                <x-modal name="hitung-ulang-hpp" title="Hitung ulang HPP {{ $bulan->translatedFormat('F Y') }}?" variant="primary"
                    :action="route('hpp.hitung')" confirm-label="Ya, hitung ulang">
                    Hasil sebelumnya akan diganti dengan perhitungan baru dari kartu stok, barang masuk, dan penjualan saat ini.
                    Laporan laba rugi bulan ini ikut berubah.
                    <x-slot:fields>
                        <input type="hidden" name="bulan" value="{{ $bulan->format('Y-m') }}">
                        <input type="hidden" name="hitung_ulang" value="1">
                    </x-slot:fields>
                </x-modal>
            @endif
        </div>
        <x-input-error for="periode" class="mt-3" />
        <x-input-error for="bulan" class="mt-3" />
    </x-card>

    @if ($hasil->isEmpty())
        <x-card>
            <x-empty-state icon="calculator" title="HPP {{ $bulan->translatedFormat('F Y') }} belum dihitung"
                description="Tekan tombol hitung. Bulan berjalan boleh dihitung sementara lalu dihitung ulang di akhir bulan." />
        </x-card>
    @else
        <p class="mb-2 text-sm text-gray-500">
            Dihitung {{ \App\Support\Format::tanggalWaktu($dihitungPada) }} oleh {{ $hasil->first()->user->nama }}.
        </p>
        <x-table :headers="['Barang', 'Persediaan Awal', 'Pembelian', 'Persediaan Akhir', 'Total HPP', 'Terjual', 'HPP/Unit']">
            @foreach ($hasil as $h)
                <tr>
                    <td>
                        <p class="font-medium text-gray-900">{{ $h->barang->nama_barang }}</p>
                        <p class="font-mono text-xs text-gray-500">{{ $h->barang->kode_barang }}</p>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @rupiah($h->nilai_persediaan_awal)<span class="block text-xs text-gray-500">{{ $h->qty_persediaan_awal }} unit</span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @rupiah($h->nilai_pembelian)<span class="block text-xs text-gray-500">{{ $h->qty_pembelian }} unit</span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @rupiah($h->nilai_persediaan_akhir)<span class="block text-xs text-gray-500">{{ $h->qty_persediaan_akhir }} unit</span>
                    </td>
                    <td class="text-right font-semibold whitespace-nowrap">@rupiah($h->total_hpp)</td>
                    <td class="text-right">{{ $h->qty_terjual }}</td>
                    <td class="text-right whitespace-nowrap">@rupiah($h->hpp_per_unit)</td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold text-gray-900">
                <td>Total</td>
                <td class="text-right whitespace-nowrap">@rupiah($total['nilai_persediaan_awal'])</td>
                <td class="text-right whitespace-nowrap">@rupiah($total['nilai_pembelian'])</td>
                <td class="text-right whitespace-nowrap">@rupiah($total['nilai_persediaan_akhir'])</td>
                <td class="text-right whitespace-nowrap">@rupiah($total['total_hpp'])</td>
                <td class="text-right">{{ $total['qty_terjual'] }}</td>
                <td></td>
            </tr>
        </x-table>
        <p class="mt-3 text-xs text-gray-500">
            Selisih stok opname (rusak, hilang, kedaluwarsa) ikut masuk HPP karena mengurangi persediaan akhir tanpa penjualan.
        </p>
    @endif
</x-layouts::app>
