@php
    $rp = fn ($v) => \App\Support\Format::rupiah($v);
    $bulanParam = sprintf('%04d-%02d', $tahun, $bulan);
@endphp
<x-layouts::laporan title="Laporan Laba Rugi" :kop="$kop">

    <x-filter-bar tombol="Tampilkan" :reset="false">
        <label for="bulan" class="text-sm font-medium text-gray-700">Bulan</label>
        <x-input name="bulan" type="month" :max="now()->format('Y-m')" :value="$bulanParam" class="w-48" />
    </x-filter-bar>

    @unless ($hpp_dihitung)
        <div class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800">
            <p class="font-semibold">HPP periodik bulan ini belum dihitung.</p>
            <p class="mt-1">Laba kotor dan laba bersih belum bisa ditampilkan. Hitung HPP terlebih dahulu agar laporan memakai angka yang benar.</p>
            <a href="{{ route('hpp.index', ['bulan' => $bulanParam]) }}" class="mt-2 inline-block font-medium text-primary-700 hover:underline print:hidden">
                Buka halaman Perhitungan HPP &rarr;
            </a>
        </div>
    @elseif ($hpp_usang)
        <div class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 print:hidden">
            Ada mutasi stok baru setelah HPP bulan ini dihitung.
            <a href="{{ route('hpp.index', ['bulan' => $bulanParam]) }}" class="font-medium text-primary-700 hover:underline">Hitung ulang HPP</a>
            agar laba rugi mutakhir.
        </div>
    @endunless

    <x-card class="max-w-3xl" :padding="false">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr class="font-semibold text-gray-900">
                    <td class="px-4 py-3">Pendapatan penjualan</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $rp($pendapatan) }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2 pl-8">Harga pokok penjualan (HPP)</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ $hpp !== null ? '('.$rp($hpp).')' : 'belum dihitung' }}</td>
                </tr>
                <tr class="baris-total bg-gray-50 font-semibold text-gray-900">
                    <td class="px-4 py-3">Laba kotor</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $laba_kotor !== null ? $rp($laba_kotor) : '-' }}</td>
                </tr>

                <tr><td colspan="2" class="px-4 pt-4 pb-1 font-semibold text-gray-900">Beban operasional</td></tr>
                @forelse ($beban as $b)
                    <tr>
                        <td class="px-4 py-2 pl-8">{{ $b['kategori'] }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $rp($b['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-2 pl-8 text-gray-500">Tidak ada beban</td></tr>
                @endforelse
                <tr class="baris-total font-medium">
                    <td class="px-4 py-2">Total beban operasional</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">({{ $rp($total_beban) }})</td>
                </tr>

                <tr @class([
                    'baris-total text-base font-bold',
                    'bg-success-50 text-success-800' => $laba_bersih !== null && \App\Support\Uang::banding($laba_bersih, '0') >= 0,
                    'bg-danger-50 text-danger-800' => $laba_bersih !== null && \App\Support\Uang::banding($laba_bersih, '0') < 0,
                    'bg-gray-50 text-gray-900' => $laba_bersih === null,
                ])>
                    <td class="px-4 py-3">{{ $laba_bersih !== null && \App\Support\Uang::banding($laba_bersih, '0') < 0 ? 'Rugi bersih' : 'Laba bersih' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $laba_bersih !== null ? $rp($laba_bersih) : '-' }}</td>
                </tr>
            </tbody>
        </table>
    </x-card>

    <p class="mt-4 max-w-3xl text-xs text-gray-500">
        Tidak termasuk laba rugi: pembelian barang {{ $rp($pembelian_barang) }} (menjadi persediaan, masuk HPP saat terjual)
        dan prive {{ $rp($prive) }} (pengambilan pribadi pemilik). Penjualan yang dibatalkan tidak dihitung.
    </p>
</x-layouts::laporan>
