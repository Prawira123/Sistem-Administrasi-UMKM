@extends('laporan.pdf._layout')

@section('isi')
    <table class="ringkas">
        <tr class="abu tebal">
            <td>Saldo awal ({{ \App\Support\Format::tanggal($dari) }})</td>
            <td class="kanan">@rupiah($saldo_awal)</td>
        </tr>
        <tr><td colspan="2" class="tebal hijau">Kas masuk</td></tr>
        @forelse ($masuk as $m)
            <tr>
                <td class="indent">{{ $m['label'] }} ({{ $m['jumlah_transaksi'] }} transaksi)</td>
                <td class="kanan">@rupiah($m['total'])</td>
            </tr>
        @empty
            <tr><td colspan="2" class="indent">Tidak ada</td></tr>
        @endforelse
        <tr class="tebal">
            <td>Total kas masuk</td>
            <td class="kanan">@rupiah($total_masuk)</td>
        </tr>
        <tr><td colspan="2" class="tebal merah">Kas keluar</td></tr>
        @forelse ($keluar as $k)
            <tr>
                <td class="indent">{{ $k['label'] }} ({{ $k['jumlah_transaksi'] }} transaksi)</td>
                <td class="kanan">@rupiah($k['total'])</td>
            </tr>
        @empty
            <tr><td colspan="2" class="indent">Tidak ada</td></tr>
        @endforelse
        <tr class="tebal">
            <td>Total kas keluar</td>
            <td class="kanan">(@rupiah($total_keluar))</td>
        </tr>
        <tr>
            <td>Kenaikan (penurunan) kas</td>
            <td class="kanan">@rupiah($arus_bersih)</td>
        </tr>
        <tr class="total">
            <td>Saldo akhir ({{ \App\Support\Format::tanggal($sampai) }})</td>
            <td class="kanan">@rupiah($saldo_akhir)</td>
        </tr>
    </table>

    @if ($rincian->isNotEmpty())
        <h3>Rincian per transaksi</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 13%">Waktu</th>
                    <th style="width: 16%">Kategori</th>
                    <th>Keterangan</th>
                    <th class="kanan" style="width: 12%">Masuk</th>
                    <th class="kanan" style="width: 12%">Keluar</th>
                    <th class="kanan" style="width: 13%">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rincian as $r)
                    <tr>
                        <td>{{ \App\Support\Format::tanggalWaktu($r['tanggal']) }}</td>
                        <td>{{ $r['kategori'] }}</td>
                        <td>{{ $r['keterangan'] }}</td>
                        <td class="kanan">{{ $r['jenis'] === 'masuk' ? \App\Support\Format::rupiah($r['jumlah']) : '' }}</td>
                        <td class="kanan">{{ $r['jenis'] === 'keluar' ? \App\Support\Format::rupiah($r['jumlah']) : '' }}</td>
                        <td class="kanan">@rupiah($r['saldo'])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
