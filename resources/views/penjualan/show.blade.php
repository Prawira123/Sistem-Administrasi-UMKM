<x-layouts::app :title="'Penjualan '.$penjualan->no_transaksi">
    <x-page-header :title="'Penjualan '.$penjualan->no_transaksi" :subtitle="\App\Support\Format::tanggalWaktu($penjualan->tanggal_penjualan).' · '.$penjualan->user->nama">
        <x-button variant="secondary" :href="route('penjualan.index')">Kembali</x-button>
        <x-button variant="secondary" icon="printer" :href="route('penjualan.nota', [$penjualan, 'ukuran' => 58])" target="_blank">Nota 58 mm</x-button>
        <x-button variant="secondary" icon="printer" :href="route('penjualan.nota', [$penjualan, 'ukuran' => 80])" target="_blank">Nota 80 mm</x-button>
        @can('batalkan-penjualan')
            @if ($penjualan->bisaDibatalkan())
                <x-button type="button" variant="danger" icon="x-circle" x-data x-on:click="$dispatch('buka-modal', 'batal-penjualan')">Batalkan</x-button>
            @endif
        @endcan
        <x-button :href="route('penjualan.create')" icon="shopping-cart">Transaksi Baru</x-button>
    </x-page-header>

    @if ($penjualan->status === 'batal')
        <x-alert type="error">
            Transaksi ini <strong>dibatalkan</strong>. Stok sudah dikembalikan dan kas penjualan dihapus.
            Alasan: {{ $penjualan->keterangan }}
        </x-alert>
    @endif

    @error('alasan')
        <x-alert type="error">{{ $message }}</x-alert>
    @enderror

    @can('batalkan-penjualan')
        @if ($penjualan->status === 'selesai' && ! $penjualan->isBulanBerjalan())
            <x-alert type="warning">{{ \App\Services\PenjualanService::PESAN_BULAN_LALU }}</x-alert>
        @elseif ($penjualan->status === 'selesai' && $penjualan->isKasSudahDivalidasi())
            <x-alert type="warning">{{ \App\Services\PenjualanService::PESAN_KAS_DIVALIDASI }}</x-alert>
        @endif
    @endcan

    <x-card title="Ringkasan">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-gray-500">No. transaksi</dt>
                <dd class="font-mono font-medium text-gray-900">{{ $penjualan->no_transaksi }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd><x-badge :status="$penjualan->status" /></dd>
            </div>
            <div>
                <dt class="text-gray-500">Metode pembayaran</dt>
                <dd class="font-medium text-gray-900">{{ $penjualan->metode_pembayaran === 'qris' ? 'QRIS' : ucfirst($penjualan->metode_pembayaran) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Kasir</dt>
                <dd class="font-medium text-gray-900">{{ $penjualan->user->nama }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Total belanja</dt>
                <dd class="text-lg font-semibold text-gray-900">@rupiah($penjualan->total_harga)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Jumlah bayar</dt>
                <dd class="font-medium text-gray-900">@rupiah($penjualan->jumlah_bayar)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Kembalian</dt>
                <dd class="font-medium text-gray-900">@rupiah($penjualan->kembalian)</dd>
            </div>
            @can('akses-keuangan')
                <div>
                    <dt class="text-gray-500">Total HPP</dt>
                    <dd class="font-medium text-gray-900">@rupiah($penjualan->total_hpp)</dd>
                </div>
            @endcan
        </dl>
    </x-card>

    <x-card title="Rincian Barang" class="mt-6" :padding="false">
        <x-table :headers="['Kode', 'Nama Barang', 'Jumlah', 'Harga Jual', 'Subtotal']" :polos="true">
            @foreach ($penjualan->detail as $detail)
                <tr>
                    <td class="font-mono text-xs">{{ $detail->barang->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $detail->barang->nama_barang }}</td>
                    <td class="text-right whitespace-nowrap">{{ $detail->jumlah }} {{ $detail->barang->satuan }}</td>
                    <td class="text-right whitespace-nowrap">@rupiah($detail->harga_jual)</td>
                    <td class="text-right whitespace-nowrap">@rupiah($detail->subtotal)</td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold text-gray-900">
                <td colspan="2" class="text-right">Total</td>
                <td class="text-right">{{ $penjualan->total_item }}</td>
                <td></td>
                <td class="text-right whitespace-nowrap">@rupiah($penjualan->total_harga)</td>
            </tr>
        </x-table>
    </x-card>

    @can('batalkan-penjualan')
        @if ($penjualan->bisaDibatalkan())
            <x-modal name="batal-penjualan" title="Batalkan transaksi {{ $penjualan->no_transaksi }}?">
                <p>Stok semua barang dikembalikan dan kas penjualan sebesar <strong>@rupiah($penjualan->total_harga)</strong> dihapus. Tindakan ini tidak bisa diurungkan.</p>
                <form id="form-batal-penjualan" method="POST" action="{{ route('penjualan.batal', $penjualan) }}" class="mt-3">
                    @csrf
                    <x-label for="alasan" value="Alasan pembatalan" />
                    <x-textarea name="alasan" rows="3" maxlength="255" required class="mt-1">{{ old('alasan') }}</x-textarea>
                </form>

                <x-slot:footer>
                    <x-button type="button" variant="secondary" x-on:click="buka = false">Tutup</x-button>
                    <x-button variant="danger" form="form-batal-penjualan">Ya, batalkan</x-button>
                </x-slot:footer>
            </x-modal>

            @error('alasan')
                {{-- Buka lagi modal bila alasan ditolak validasi. --}}
                <div x-data x-init="$nextTick(() => $dispatch('buka-modal', 'batal-penjualan'))"></div>
            @enderror
        @endif
    @endcan
</x-layouts::app>
