<x-layouts::app title="Laporan Arus Kas">
    @include('laporan._kop')

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
        <x-form-field for="dari" label="Dari tanggal">
            <x-input name="dari" type="date" :value="$dari->toDateString()" />
        </x-form-field>
        <x-form-field for="sampai" label="Sampai tanggal">
            <x-input name="sampai" type="date" :value="$sampai->toDateString()" />
        </x-form-field>
        <label class="mb-2 inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="rincian" value="1" @checked($rincian->isNotEmpty() || request()->boolean('rincian')) class="rounded border-gray-300">
            Tampilkan rincian per transaksi
        </label>
        <x-button variant="secondary">Tampilkan</x-button>
    </form>

    <x-card class="max-w-3xl" :padding="false">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr class="bg-gray-50 font-semibold text-gray-900">
                    <td class="px-4 py-3">Saldo awal ({{ \App\Support\Format::tanggal($dari) }})</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">@rupiah($saldo_awal)</td>
                </tr>

                <tr><td colspan="2" class="px-4 pt-4 pb-1 font-semibold text-success-700">Kas masuk</td></tr>
                @forelse ($masuk as $m)
                    <tr>
                        <td class="px-4 py-2 pl-8">{{ $m['label'] }} <span class="text-xs text-gray-500">({{ $m['jumlah_transaksi'] }} transaksi)</span></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">@rupiah($m['total'])</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-2 pl-8 text-gray-500">Tidak ada</td></tr>
                @endforelse
                <tr class="font-medium">
                    <td class="px-4 py-2">Total kas masuk</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap text-success-700">@rupiah($total_masuk)</td>
                </tr>

                <tr><td colspan="2" class="px-4 pt-4 pb-1 font-semibold text-danger-700">Kas keluar</td></tr>
                @forelse ($keluar as $k)
                    <tr>
                        <td class="px-4 py-2 pl-8">{{ $k['label'] }} <span class="text-xs text-gray-500">({{ $k['jumlah_transaksi'] }} transaksi)</span></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">@rupiah($k['total'])</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-2 pl-8 text-gray-500">Tidak ada</td></tr>
                @endforelse
                <tr class="font-medium">
                    <td class="px-4 py-2">Total kas keluar</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap text-danger-700">(@rupiah($total_keluar))</td>
                </tr>

                <tr class="font-medium">
                    <td class="px-4 py-2">Kenaikan (penurunan) kas</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">@rupiah($arus_bersih)</td>
                </tr>
                <tr class="bg-gray-50 text-base font-bold text-gray-900">
                    <td class="px-4 py-3">Saldo akhir ({{ \App\Support\Format::tanggal($sampai) }})</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">@rupiah($saldo_akhir)</td>
                </tr>
            </tbody>
        </table>
    </x-card>

    @if ($rincian->isNotEmpty())
        <x-card title="Rincian per transaksi" class="mt-6" :padding="false">
            <x-table :headers="['Waktu', 'Kategori', 'Keterangan', 'Masuk', 'Keluar', 'Saldo']" :polos="true">
                @foreach ($rincian as $r)
                    <tr>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($r['tanggal']) }}</td>
                        <td>{{ $r['kategori'] }}</td>
                        <td class="text-xs">{{ $r['keterangan'] }}</td>
                        <td class="text-right whitespace-nowrap text-success-700">{{ $r['jenis'] === 'masuk' ? \App\Support\Format::rupiah($r['jumlah']) : '' }}</td>
                        <td class="text-right whitespace-nowrap text-danger-700">{{ $r['jenis'] === 'keluar' ? \App\Support\Format::rupiah($r['jumlah']) : '' }}</td>
                        <td class="text-right font-medium whitespace-nowrap">@rupiah($r['saldo'])</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-layouts::app>
