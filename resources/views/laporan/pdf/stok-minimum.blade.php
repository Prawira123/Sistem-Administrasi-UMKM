@extends('laporan.pdf._layout')

@section('isi')
    @if ($barang->isEmpty())
        <p>Semua stok aman: tidak ada barang aktif dengan stok di bawah atau sama dengan stok minimum.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="tengah" style="width: 5%">No</th>
                    <th style="width: 12%">Kode</th>
                    <th>Nama Barang</th>
                    <th style="width: 16%">Kategori</th>
                    <th class="kanan" style="width: 10%">Stok</th>
                    <th class="kanan" style="width: 10%">Stok Min.</th>
                    <th class="kanan" style="width: 10%">Kekurangan</th>
                    <th style="width: 9%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($barang as $i => $b)
                    <tr>
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>{{ $b->kode_barang }}</td>
                        <td>{{ $b->nama_barang }}</td>
                        <td>{{ $b->kategori->nama_kategori }}</td>
                        <td class="kanan">{{ $b->stok }} {{ $b->satuan }}</td>
                        <td class="kanan">{{ $b->stok_minimum }}</td>
                        <td class="kanan">{{ max(0, $b->stok_minimum - $b->stok) }}</td>
                        <td>{{ ucfirst($b->statusStok()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
