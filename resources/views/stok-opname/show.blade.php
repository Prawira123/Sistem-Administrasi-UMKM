<x-layouts::app :title="'Stok Opname '.$opname->kode_opname">
    <x-page-header :title="'Stok Opname '.$opname->kode_opname" :subtitle="'Tanggal '.\App\Support\Format::tanggal($opname->tanggal_opname).' · dibuat oleh '.$opname->user->nama">
        <x-button variant="secondary" :href="route('stok-opname.index')">Kembali</x-button>
        @can('ubah-opname', $opname)
            <x-button variant="secondary" icon="pencil" :href="route('stok-opname.edit', $opname)">Input Hitungan</x-button>
            <form method="POST" action="{{ route('stok-opname.ajukan', $opname) }}">
                @csrf
                <x-button icon="check-circle">Ajukan</x-button>
            </form>
        @endcan
        @can('setujui-opname')
            @if ($opname->isDiajukan())
                <x-button type="button" variant="secondary" icon="x-circle" x-data x-on:click="$dispatch('buka-modal', 'tolak-opname')">Tolak</x-button>
                <x-button type="button" icon="check-circle" x-data x-on:click="$dispatch('buka-modal', 'setujui-opname')">Setujui</x-button>
            @endif
        @endcan
    </x-page-header>

    @error('opname')
        <x-alert type="error">{{ $message }}</x-alert>
    @enderror
    @error('catatan')
        <x-alert type="error">{{ $message }}</x-alert>
    @enderror

    @if ($opname->isFinal())
        <x-alert type="success">
            Disetujui {{ $opname->approver?->nama ?? '(pengguna dihapus)' }} pada {{ \App\Support\Format::tanggalWaktu($opname->disetujui_pada) }}.
            Data final dan tidak bisa diubah; stok sudah disesuaikan lewat kartu stok.
        </x-alert>
    @elseif ($opname->isDiajukan())
        <x-alert type="warning">Menunggu persetujuan Administrator. Data tidak bisa diedit kecuali ditolak.</x-alert>
    @endif

    {{-- Peringatan untuk Admin: stok berubah sejak draft (mis. ada penjualan setelah barang dihitung). --}}
    @if ($perubahan->isNotEmpty())
        <x-card title="Stok berubah sejak draft dibuat" class="mb-6 border-warning-600/40">
            <p class="mb-3 text-sm text-gray-600">
                Saat disetujui, yang diterapkan adalah <strong>selisih hasil hitung</strong>:
                stok baru = stok saat ini + selisih, sehingga transaksi setelah penghitungan tetap terhitung.
            </p>
            <x-table :headers="['Barang', 'Stok Sistem (draft)', 'Stok Fisik', 'Selisih', 'Stok Saat Ini', 'Stok Setelah Disetujui']" :polos="true">
                @foreach ($perubahan as $p)
                    <tr @class(['bg-danger-50' => $p['minus']])>
                        <td class="font-medium text-gray-900">{{ $p['detail']->barang->nama_barang }}</td>
                        <td class="text-right">{{ $p['detail']->stok_sistem }}</td>
                        <td class="text-right">{{ $p['detail']->stok_fisik }}</td>
                        <td class="text-right">{{ $p['detail']->selisih > 0 ? '+' : '' }}{{ $p['detail']->selisih }}</td>
                        <td class="text-right">{{ $p['stok_saat_ini'] }}</td>
                        <td @class(['text-right font-semibold', 'text-danger-700' => $p['minus']])>
                            {{ $p['stok_hasil'] }}
                            @if ($p['minus'])
                                <span class="block text-xs font-normal">Minus: tolak dan hitung ulang</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    <x-card title="Ringkasan">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd><x-badge :status="$opname->status" /></dd>
            </div>
            <div>
                <dt class="text-gray-500">Jumlah barang</dt>
                <dd class="font-medium text-gray-900">{{ $opname->detail->count() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Barang berselisih</dt>
                <dd class="font-medium text-gray-900">{{ $opname->detail->where('selisih', '!=', 0)->count() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Keterangan</dt>
                <dd class="text-gray-900">{{ $opname->keterangan ?? '-' }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Hasil Hitung" class="mt-6" :padding="false">
        <x-table :headers="['Kode', 'Nama Barang', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Keterangan']" :polos="true">
            @foreach ($opname->detail as $d)
                <tr @class(['bg-warning-50' => $d->selisih !== 0])>
                    <td class="font-mono text-xs">{{ $d->barang->kode_barang }}</td>
                    <td class="font-medium text-gray-900">{{ $d->barang->nama_barang }}</td>
                    <td class="text-right">{{ $d->stok_sistem }} {{ $d->barang->satuan }}</td>
                    <td class="text-right">{{ $d->stok_fisik }}</td>
                    <td @class([
                        'text-right font-semibold',
                        'text-danger-700' => $d->selisih < 0,
                        'text-success-700' => $d->selisih > 0,
                        'text-gray-400' => $d->selisih === 0,
                    ])>{{ $d->selisih > 0 ? '+' : '' }}{{ $d->selisih }}</td>
                    <td>{{ $d->keterangan ?? '-' }}</td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @can('setujui-opname')
        @if ($opname->isDiajukan())
            <x-modal name="setujui-opname" title="Setujui stok opname {{ $opname->kode_opname }}?" variant="primary"
                :action="route('stok-opname.setujui', $opname)" confirm-label="Ya, setujui">
                Stok {{ $opname->detail->where('selisih', '!=', 0)->count() }} barang berselisih akan disesuaikan
                (stok saat ini + selisih) dan dicatat di kartu stok. Opname menjadi final dan tidak bisa diubah.
            </x-modal>

            <x-modal name="tolak-opname" title="Kembalikan ke draft?" variant="primary">
                Opname dikembalikan ke draft agar pembuatnya bisa menghitung ulang.
                <form id="form-tolak-opname" method="POST" action="{{ route('stok-opname.tolak', $opname) }}" class="mt-3">
                    @csrf
                    <x-label for="catatan" value="Catatan untuk penghitung" />
                    <x-textarea name="catatan" rows="2" maxlength="200" required class="mt-1">{{ old('catatan') }}</x-textarea>
                </form>
                <x-slot:footer>
                    <x-button type="button" variant="secondary" x-on:click="buka = false">Tutup</x-button>
                    <x-button form="form-tolak-opname">Kembalikan ke Draft</x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    @endcan
</x-layouts::app>
