@extends('laporan.pdf._layout')

@section('isi')
    <table class="ringkas">
        <tr><td>Total penjualan</td><td class="kanan tebal">@rupiah($total)</td></tr>
        <tr><td>Transaksi selesai</td><td class="kanan">{{ $jumlah_transaksi }}</td></tr>
        <tr><td>Unit terjual</td><td class="kanan">{{ $total_unit }}</td></tr>
        @if ($jumlah_batal)
            <tr><td>Transaksi batal (tidak dihitung)</td><td class="kanan">{{ $jumlah_batal }}</td></tr>
        @endif
    </table>

    <h3>Per metode pembayaran</h3>
    <table>
        <thead><tr><th>Metode</th><th class="kanan">Transaksi</th><th class="kanan">Total</th></tr></thead>
        <tbody>
            @forelse ($per_metode as $m)
                <tr>
                    <td>{{ $m['metode'] === 'qris' ? 'QRIS' : ucfirst($m['metode']) }}</td>
                    <td class="kanan">{{ $m['transaksi'] }}</td>
                    <td class="kanan">@rupiah($m['total'])</td>
                </tr>
            @empty
                <tr><td colspan="3">Tidak ada</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Per hari</h3>
    <table>
        <thead><tr><th>Tanggal</th><th class="kanan">Transaksi</th><th class="kanan">Unit</th><th class="kanan">Total</th></tr></thead>
        <tbody>
            @forelse ($per_hari as $h)
                <tr>
                    <td>@tanggal($h['tanggal'])</td>
                    <td class="kanan">{{ $h['transaksi'] }}</td>
                    <td class="kanan">{{ $h['unit'] }}</td>
                    <td class="kanan">@rupiah($h['total'])</td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada penjualan</td></tr>
            @endforelse
            <tr class="abu tebal">
                <td>Total</td>
                <td class="kanan">{{ $jumlah_transaksi }}</td>
                <td class="kanan">{{ $total_unit }}</td>
                <td class="kanan">@rupiah($total)</td>
            </tr>
        </tbody>
    </table>

    <h3>Barang terlaris</h3>
    <table>
        <thead><tr><th class="tengah" style="width: 6%">No</th><th style="width: 16%">Kode</th><th>Barang</th><th class="kanan">Qty</th><th class="kanan">Omzet</th></tr></thead>
        <tbody>
            @forelse ($per_barang as $i => $b)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $b['kode'] }}</td>
                    <td>{{ $b['nama'] }}</td>
                    <td class="kanan">{{ $b['qty'] }} {{ $b['satuan'] }}</td>
                    <td class="kanan">@rupiah($b['omzet'])</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
