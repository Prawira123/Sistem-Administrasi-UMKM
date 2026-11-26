@extends('laporan.pdf._layout')

@section('isi')
    @forelse ($bagian as $b)
        <h3>{{ $b['barang']->nama_barang }} <small>{{ $b['barang']->kode_barang }} &middot; {{ $b['barang']->satuan }}</small></h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 13%">Tanggal</th>
                    <th style="width: 9%">Jenis</th>
                    <th style="width: 15%">Sumber</th>
                    <th>Keterangan</th>
                    <th class="kanan" style="width: 7%">Masuk</th>
                    <th class="kanan" style="width: 7%">Keluar</th>
                    <th class="kanan" style="width: 7%">Saldo</th>
                    <th class="kanan" style="width: 10%">HPP Satuan</th>
                </tr>
            </thead>
            <tbody>
                <tr class="abu miring">
                    <td colspan="6">Stok awal periode</td>
                    <td class="kanan tebal">{{ $b['stok_awal'] }}</td>
                    <td></td>
                </tr>
                @foreach ($b['mutasi'] as $m)
                    <tr>
                        <td>{{ \App\Support\Format::tanggalWaktu($m['tanggal']) }}</td>
                        <td>{{ ucfirst($m['jenis']) }}</td>
                        <td>{{ $m['sumber'] }}</td>
                        <td>{{ $m['keterangan'] }}</td>
                        <td class="kanan">{{ $m['masuk'] ?: '' }}</td>
                        <td class="kanan">{{ $m['keluar'] ?: '' }}</td>
                        <td class="kanan">{{ $m['saldo'] }}</td>
                        <td class="kanan">@rupiah($m['hpp_satuan'])</td>
                    </tr>
                @endforeach
                <tr class="abu tebal">
                    <td colspan="4">Total / stok akhir periode</td>
                    <td class="kanan">{{ $b['total_masuk'] }}</td>
                    <td class="kanan">{{ $b['total_keluar'] }}</td>
                    <td class="kanan">{{ $b['stok_akhir'] }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @empty
        <p>Tidak ada mutasi stok pada periode ini.</p>
    @endforelse
@endsection
