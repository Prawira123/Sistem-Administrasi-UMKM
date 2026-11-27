<x-layouts::app title="Validasi Kas">
    <x-page-header title="Validasi Kas Harian" subtitle="Mencocokkan uang tunai di laci dengan penjualan tunai di sistem, satu kali per tanggal.">
        @if ($sudahHariIni)
            <x-badge status="sesuai">Hari ini sudah divalidasi</x-badge>
        @else
            <x-button :href="route('validasi-kas.create')" icon="plus">Validasi Hari Ini</x-button>
        @endif
    </x-page-header>

    <x-filter-bar :action="route('validasi-kas.create')" tombol="Buka" :ikon="null" :reset="false">
        <label for="tanggal" class="text-sm font-medium text-gray-700">Validasi tanggal lain</label>
        <x-input name="tanggal" type="date" :min="now()->startOfMonth()->toDateString()" :max="now()->toDateString()" :value="now()->toDateString()" class="w-44" />
    </x-filter-bar>

    @if ($validasi->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="Belum ada validasi kas" description="Lakukan validasi setiap akhir hari setelah kasir tutup." />
        </x-card>
    @else
        <x-table :headers="['Tanggal', 'Tunai Sistem', 'Uang Fisik', 'Selisih', 'Status', 'Catatan', 'Oleh', '']" :kanan="['Tunai Sistem', 'Uang Fisik', 'Selisih']">
            @foreach ($validasi as $v)
                <tr>
                    <td class="whitespace-nowrap">@tanggal($v->tanggal)</td>
                    <td class="text-right whitespace-nowrap">@rupiah($v->total_tunai_sistem)</td>
                    <td class="text-right whitespace-nowrap">@rupiah($v->uang_fisik)</td>
                    <td @class([
                        'text-right font-medium whitespace-nowrap',
                        'text-success-700' => (float) $v->selisih > 0,
                        'text-danger-700' => (float) $v->selisih < 0,
                    ])>{{ (float) $v->selisih > 0 ? '+' : '' }}@rupiah($v->selisih)</td>
                    <td><x-badge :status="$v->status" /></td>
                    <td class="text-xs">{{ $v->catatan ?? '-' }}</td>
                    <td class="whitespace-nowrap">{{ $v->user->nama }}</td>
                    <td><x-button variant="secondary" size="sm" :href="route('validasi-kas.show', $v)">Detail</x-button></td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $validasi->links() }}</div>
    @endif
</x-layouts::app>
