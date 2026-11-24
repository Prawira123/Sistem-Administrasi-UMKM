<x-layouts::app :title="'Barang Masuk '.$barangMasuk->kode_barang_masuk">
    <x-page-header :title="'Barang Masuk '.$barangMasuk->kode_barang_masuk" :subtitle="$barangMasuk->supplier->nama_supplier">
        <x-button variant="secondary" :href="route('barang-masuk.index')">Kembali</x-button>
        <x-button :href="route('barang-masuk.create')" icon="plus">Catat Lagi</x-button>
    </x-page-header>

    <x-alert type="warning">
        Barang masuk yang sudah disimpan tidak bisa diedit atau dihapus, agar HPP rata-rata dan kartu stok tetap konsisten.
        Bila ada kesalahan jumlah, perbaiki lewat Stok Opname.
    </x-alert>

    <x-card title="Data Faktur">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-gray-500">Kode</dt>
                <dd class="font-mono font-medium text-gray-900">{{ $barangMasuk->kode_barang_masuk }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tanggal masuk</dt>
                <dd class="font-medium text-gray-900">@tanggal($barangMasuk->tanggal_masuk)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Supplier</dt>
                <dd class="font-medium text-gray-900">{{ $barangMasuk->supplier->kode_supplier }} · {{ $barangMasuk->supplier->nama_supplier }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">No. faktur supplier</dt>
                <dd class="font-medium text-gray-900">{{ $barangMasuk->no_faktur ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Metode pembayaran</dt>
                <dd class="font-medium text-gray-900">{{ ucfirst($barangMasuk->metode_pembayaran) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Dicatat oleh</dt>
                <dd class="font-medium text-gray-900">{{ $barangMasuk->user->nama }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Waktu pencatatan</dt>
                <dd class="font-medium text-gray-900">{{ \App\Support\Format::tanggalWaktu($barangMasuk->created_at) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Kas keluar</dt>
                <dd class="font-medium text-gray-900">{{ $barangMasuk->kas ? \App\Support\Format::rupiah($barangMasuk->kas->jumlah) : '-' }}</dd>
            </div>
            @if ($barangMasuk->keterangan)
                <div class="sm:col-span-2 lg:col-span-4">
                    <dt class="text-gray-500">Keterangan</dt>
                    <dd class="text-gray-900">{{ $barangMasuk->keterangan }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    <x-card title="Rincian Barang" class="mt-6" :padding="false">
        <x-table :headers="['Kode', 'Nama Barang', 'Jumlah', 'Harga Beli', 'Subtotal', 'Kedaluwarsa']" :polos="true">
            @foreach ($barangMasuk->detail as $detail)
                <tr>
                    <td class="font-mono text-xs">{{ $detail->barang->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $detail->barang->nama_barang }}</td>
                    <td class="text-right whitespace-nowrap">{{ $detail->jumlah }} {{ $detail->barang->satuan }}</td>
                    <td class="text-right whitespace-nowrap">@rupiah($detail->harga_beli)</td>
                    <td class="text-right whitespace-nowrap">@rupiah($detail->subtotal)</td>
                    <td class="whitespace-nowrap">@tanggal($detail->tanggal_kadaluarsa)</td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold text-gray-900">
                <td colspan="4" class="text-right">Total Pembelian</td>
                <td class="text-right whitespace-nowrap">@rupiah($barangMasuk->total_pembelian)</td>
                <td></td>
            </tr>
        </x-table>
    </x-card>
</x-layouts::app>
