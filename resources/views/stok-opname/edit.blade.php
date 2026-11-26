{{--
    Input hasil hitung fisik. Setiap baris punya state Alpine kecil { sistem, fisik }:
    selisih = fisik − sistem dihitung langsung, baris berselisih diberi warna dan keterangan menjadi wajib.
--}}
<x-layouts::app :title="'Stok Opname '.$opname->kode_opname">
    <x-page-header :title="'Input Hitungan '.$opname->kode_opname"
        :subtitle="'Draft dibuat '.\App\Support\Format::tanggal($opname->tanggal_opname).' oleh '.$opname->user->nama.'. Stok sistem diambil saat draft dibuat.'">
        <x-button variant="secondary" :href="route('stok-opname.show', $opname)">Lihat Detail</x-button>
    </x-page-header>

    @if ($opname->keterangan)
        <x-alert type="warning">{{ $opname->keterangan }}</x-alert>
    @endif

    @error('opname')
        <x-alert type="error">{{ $message }}</x-alert>
    @enderror

    <datalist id="saran-keterangan">
        @foreach ($saranKeterangan as $saran)
            <option value="{{ $saran }}">
        @endforeach
    </datalist>

    <form method="POST" action="{{ route('stok-opname.update', $opname) }}">
        @csrf
        @method('PUT')

        <x-card :padding="false">
            <x-table :headers="['Kode', 'Nama Barang', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Keterangan Selisih']" :polos="true">
                @foreach ($opname->detail as $d)
                    @php
                        $fisik = old("detail.{$d->id_detail_opname}.stok_fisik", $d->stok_fisik);
                    @endphp
                    <tr x-data="{
                            sistem: {{ $d->stok_sistem }},
                            fisik: @js((string) $fisik),
                            selisih() { const n = parseInt(this.fisik, 10); return isNaN(n) ? 0 : n - this.sistem; },
                        }"
                        x-bind:class="selisih() !== 0 ? 'bg-warning-50' : ''">
                        <td class="font-mono text-xs whitespace-nowrap">{{ $d->barang->kode_barang }}</td>
                        <td class="font-medium text-gray-900">{{ $d->barang->nama_barang }}</td>
                        <td class="text-right whitespace-nowrap">{{ $d->stok_sistem }} {{ $d->barang->satuan }}</td>
                        <td class="w-32">
                            <x-input type="number" min="0" step="1" required :name="'detail['.$d->id_detail_opname.'][stok_fisik]'"
                                :id="'fisik-'.$d->id_detail_opname" :value="$fisik" x-model="fisik" class="text-right" />
                            @error('detail.'.$d->id_detail_opname.'.stok_fisik')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </td>
                        <td class="text-right font-semibold whitespace-nowrap"
                            x-bind:class="selisih() < 0 ? 'text-danger-700' : (selisih() > 0 ? 'text-success-700' : 'text-gray-400')"
                            x-text="(selisih() > 0 ? '+' : '') + selisih()">{{ $d->selisih > 0 ? '+' : '' }}{{ $d->selisih }}</td>
                        <td class="min-w-56">
                            <x-input :name="'detail['.$d->id_detail_opname.'][keterangan]'" :id="'keterangan-'.$d->id_detail_opname"
                                :value="old('detail.'.$d->id_detail_opname.'.keterangan', $d->keterangan)" maxlength="255" list="saran-keterangan"
                                placeholder="Rusak / hilang / kedaluwarsa / salah catat" x-bind:required="selisih() !== 0" />
                            @error('detail.'.$d->id_detail_opname.'.keterangan')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <x-slot:footer>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Selisih = stok fisik − stok sistem. Keterangan wajib untuk baris yang berselisih.</p>
                    <div class="flex gap-2">
                        <x-button variant="secondary">Simpan Draft</x-button>
                        <x-button name="ajukan" value="1" icon="check-circle">Simpan &amp; Ajukan</x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
