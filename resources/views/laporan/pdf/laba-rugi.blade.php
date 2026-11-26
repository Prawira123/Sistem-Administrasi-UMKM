@php($rp = fn ($v) => \App\Support\Format::rupiah($v))
@extends('laporan.pdf._layout')

@section('isi')
    @unless ($hpp_dihitung)
        <div class="peringatan">
            <strong>HPP periodik bulan ini belum dihitung.</strong>
            Laba kotor dan laba bersih belum bisa ditampilkan. Hitung HPP di menu Keuangan &rarr; HPP terlebih dahulu.
        </div>
    @endunless

    <table class="ringkas">
        <tr class="tebal">
            <td>Pendapatan penjualan</td>
            <td class="kanan">{{ $rp($pendapatan) }}</td>
        </tr>
        <tr>
            <td class="indent">Harga pokok penjualan (HPP)</td>
            <td class="kanan">{{ $hpp !== null ? '('.$rp($hpp).')' : 'belum dihitung' }}</td>
        </tr>
        <tr class="abu tebal">
            <td>Laba kotor</td>
            <td class="kanan">{{ $laba_kotor !== null ? $rp($laba_kotor) : '-' }}</td>
        </tr>
        <tr><td colspan="2" class="tebal">Beban operasional</td></tr>
        @forelse ($beban as $b)
            <tr>
                <td class="indent">{{ $b['kategori'] }}</td>
                <td class="kanan">{{ $rp($b['total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="indent">Tidak ada beban</td></tr>
        @endforelse
        <tr class="tebal">
            <td>Total beban operasional</td>
            <td class="kanan">({{ $rp($total_beban) }})</td>
        </tr>
        <tr class="total">
            <td>{{ $laba_bersih !== null && \App\Support\Uang::banding($laba_bersih, '0') < 0 ? 'Rugi bersih' : 'Laba bersih' }}</td>
            <td class="kanan">{{ $laba_bersih !== null ? $rp($laba_bersih) : '-' }}</td>
        </tr>
    </table>

    <p class="catatan">
        Tidak termasuk laba rugi: pembelian barang {{ $rp($pembelian_barang) }} (menjadi persediaan, masuk HPP saat terjual)
        dan prive {{ $rp($prive) }} (pengambilan pribadi pemilik). Penjualan yang dibatalkan tidak dihitung.
    </p>
@endsection
