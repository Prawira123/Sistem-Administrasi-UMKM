@php
    $labelKategori = [
        'penjualan' => 'Penjualan', 'pembelian_barang' => 'Pembelian barang', 'operasional' => 'Operasional',
        'modal' => 'Modal', 'prive' => 'Prive', 'penyesuaian' => 'Penyesuaian',
    ];
@endphp
<x-layouts::app title="Buku Kas">
    <x-page-header title="Buku Kas"
        subtitle="Semua uang masuk dan keluar. Kas transaksi tercatat otomatis; modal dan prive diinput manual.">
        <x-button variant="secondary" icon="plus" :href="route('kas.create', ['kategori' => 'prive'])">Catat Prive</x-button>
        <x-button icon="plus" :href="route('kas.create', ['kategori' => 'modal'])">Catat Modal</x-button>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <x-form-field for="dari" label="Dari tanggal">
            <x-input name="dari" type="date" :value="$dari->toDateString()" />
        </x-form-field>
        <x-form-field for="sampai" label="Sampai tanggal">
            <x-input name="sampai" type="date" :value="$sampai->toDateString()" />
        </x-form-field>
        <x-form-field for="kategori" label="Kategori">
            <x-select name="kategori" :options="$labelKategori" :selected="$filter['kategori'] ?? ''" placeholder="Semua kategori" />
        </x-form-field>
        <x-form-field for="jenis" label="Jenis">
            <x-select name="jenis" :options="['masuk' => 'Masuk', 'keluar' => 'Keluar']" :selected="$filter['jenis'] ?? ''" placeholder="Masuk & keluar" />
        </x-form-field>
        <div class="flex gap-2">
            <x-button variant="secondary">Terapkan</x-button>
            @if (request()->query())
                <x-button variant="secondary" :href="route('kas.index')">Reset</x-button>
            @endif
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-card>
            <p class="text-sm text-gray-500">Saldo awal ({{ \App\Support\Format::tanggal($dari) }})</p>
            <p class="text-xl font-semibold text-gray-900">@rupiah($saldoAwal)</p>
        </x-card>
        <x-card>
            <p class="text-sm text-gray-500">Kas masuk</p>
            <p class="text-xl font-semibold text-success-700">@rupiah($totalMasuk)</p>
        </x-card>
        <x-card>
            <p class="text-sm text-gray-500">Kas keluar</p>
            <p class="text-xl font-semibold text-danger-700">@rupiah($totalKeluar)</p>
        </x-card>
        <x-card>
            <p class="text-sm text-gray-500">Saldo akhir ({{ \App\Support\Format::tanggal($sampai) }})</p>
            <p class="text-xl font-semibold text-gray-900">{{ $saldoAkhir !== null ? \App\Support\Format::rupiah($saldoAkhir) : '-' }}</p>
            @if ($tersaring)
                <p class="text-xs text-gray-500">Hapus filter kategori/jenis untuk melihat saldo.</p>
            @endif
        </x-card>
    </div>

    @if ($baris->isEmpty())
        <x-card>
            <x-empty-state icon="banknotes" title="Tidak ada arus kas" description="Belum ada kas masuk atau keluar pada periode/filter ini." />
        </x-card>
    @else
        <x-table :headers="array_merge(['Waktu', 'Kategori', 'Referensi', 'Keterangan', 'Masuk', 'Keluar'], $tersaring ? [] : ['Saldo'], ['Oleh', ''])">
            @foreach ($baris as $k)
                <tr>
                    <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($k->tanggal) }}</td>
                    <td><x-badge :status="$k->jenis">{{ $labelKategori[$k->kategori] }}</x-badge></td>
                    <td class="font-mono text-xs whitespace-nowrap">
                        @if ($k->penjualan)
                            <a href="{{ route('penjualan.show', $k->penjualan) }}" class="text-primary-700 hover:underline">{{ $k->penjualan->no_transaksi }}</a>
                        @elseif ($k->barangMasuk)
                            <a href="{{ route('barang-masuk.show', $k->barangMasuk) }}" class="text-primary-700 hover:underline">{{ $k->barangMasuk->kode_barang_masuk }}</a>
                        @elseif ($k->pengeluaran)
                            @if ($k->pengeluaran->isBulanBerjalan())
                                <a href="{{ route('pengeluaran.edit', $k->pengeluaran) }}" class="text-primary-700 hover:underline">{{ $k->pengeluaran->kode_pengeluaran }}</a>
                            @else
                                {{ $k->pengeluaran->kode_pengeluaran }}
                            @endif
                        @elseif ($k->validasiKas)
                            <a href="{{ route('validasi-kas.show', $k->validasiKas) }}" class="text-primary-700 hover:underline">Validasi @tanggal($k->validasiKas->tanggal)</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="min-w-48 text-xs">{{ $k->keterangan }}</td>
                    <td class="text-right whitespace-nowrap text-success-700">{{ $k->jenis === 'masuk' ? \App\Support\Format::rupiah($k->jumlah) : '' }}</td>
                    <td class="text-right whitespace-nowrap text-danger-700">{{ $k->jenis === 'keluar' ? \App\Support\Format::rupiah($k->jumlah) : '' }}</td>
                    @unless ($tersaring)
                        <td class="text-right font-medium whitespace-nowrap">@rupiah($k->saldo_berjalan)</td>
                    @endunless
                    <td class="whitespace-nowrap">{{ $k->user->nama }}</td>
                    <td>
                        @if ($k->bisaDihapus())
                            <x-delete-button :action="route('kas.destroy', $k)" :id="'kas-'.$k->id_kas"
                                :nama="$labelKategori[$k->kategori].' '.\App\Support\Format::rupiah($k->jumlah)" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    @if ($perKategori->isNotEmpty())
        <x-card title="Ringkasan per kategori" class="mt-6" :padding="false">
            <x-table :headers="['Jenis', 'Kategori', 'Total']" :polos="true">
                @foreach ($perKategori as $r)
                    <tr>
                        <td><x-badge :status="$r->jenis" /></td>
                        <td>{{ $labelKategori[$r->kategori] }}</td>
                        <td @class(['text-right font-medium whitespace-nowrap', 'text-success-700' => $r->jenis === 'masuk', 'text-danger-700' => $r->jenis === 'keluar'])>@rupiah($r->total)</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-layouts::app>
