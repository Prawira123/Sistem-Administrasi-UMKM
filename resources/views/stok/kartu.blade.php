<x-layouts::app :title="'Kartu Stok '.$barang->nama_barang">
    <x-page-header :title="'Kartu Stok: '.$barang->nama_barang"
        :subtitle="$barang->kode_barang.' · '.$barang->kategori->nama_kategori.' · stok sekarang '.$barang->stok.' '.$barang->satuan">
        <x-button variant="secondary" :href="route('stok.index')">Kembali</x-button>
    </x-page-header>

    <x-filter-bar :dari="$filter['dari'] ?? ''" :sampai="$filter['sampai'] ?? ''">
        <x-select name="jenis" :options="['masuk' => 'Masuk', 'keluar' => 'Keluar', 'penyesuaian' => 'Penyesuaian']"
            :selected="$filter['jenis'] ?? ''" placeholder="Semua jenis mutasi" class="w-48" />
    </x-filter-bar>

    @if ($mutasi->isEmpty())
        <x-card>
            <x-empty-state title="Belum ada mutasi" description="Tidak ada mutasi stok barang ini yang cocok dengan filter." />
        </x-card>
    @else
        {{-- Kolom HPP hanya untuk pemegang akses keuangan (Administrator). --}}
        @php($lihatHpp = auth()->user()->can('akses-keuangan'))
        <x-table :headers="array_values(array_filter(['Waktu', 'Jenis', 'Sumber', 'Masuk', 'Keluar', 'Stok Sebelum', 'Stok Sesudah', $lihatHpp ? 'HPP/Unit' : null, 'Keterangan', 'Oleh']))"
            :kanan="['Masuk', 'Keluar', 'Stok Sebelum', 'Stok Sesudah', 'HPP/Unit']">
            @foreach ($mutasi as $m)
                <tr>
                    <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($m->tanggal) }}</td>
                    <td><x-badge :status="$m->jenis_mutasi" /></td>
                    <td class="font-mono text-xs whitespace-nowrap">
                        @if ($m->barangMasuk)
                            <a href="{{ route('barang-masuk.show', $m->barangMasuk) }}" class="text-primary-700 hover:underline">{{ $m->barangMasuk->kode_barang_masuk }}</a>
                        @elseif ($m->penjualan)
                            @can('lihat-penjualan', $m->penjualan)
                                <a href="{{ route('penjualan.show', $m->penjualan) }}" class="text-primary-700 hover:underline">{{ $m->penjualan->no_transaksi }}</a>
                            @else
                                {{ $m->penjualan->no_transaksi }}
                            @endcan
                        @elseif ($m->stokOpname)
                            <a href="{{ route('stok-opname.show', $m->stokOpname) }}" class="text-primary-700 hover:underline">{{ $m->stokOpname->kode_opname }}</a>
                        @endif
                    </td>
                    <td class="text-right">{{ $m->jumlah_masuk ?: '' }}</td>
                    <td class="text-right">{{ $m->jumlah_keluar ?: '' }}</td>
                    <td class="text-right">{{ $m->stok_sebelum }}</td>
                    <td class="text-right font-semibold">{{ $m->stok_sesudah }}</td>
                    @can('akses-keuangan')
                        <td class="text-right whitespace-nowrap">@rupiah($m->hpp_satuan)</td>
                    @endcan
                    <td class="min-w-48 text-xs">{{ $m->keterangan }}</td>
                    <td class="whitespace-nowrap">{{ $m->user->nama }}</td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $mutasi->links() }}</div>
    @endif
</x-layouts::app>
