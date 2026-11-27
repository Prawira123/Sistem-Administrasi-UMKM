<x-layouts::app title="Validasi Kas">
    <x-page-header title="Validasi Kas Harian" subtitle="Mencocokkan uang tunai di laci dengan penjualan tunai di sistem, satu kali per tanggal.">
        @if ($sudahHariIni)
            <x-badge status="sesuai">Hari ini sudah divalidasi</x-badge>
        @else
            <x-button :href="route('validasi-kas.create')" icon="plus">Validasi Hari Ini</x-button>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('validasi-kas.create') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <x-form-field for="tanggal" label="Validasi tanggal lain">
            <x-input name="tanggal" type="date" :min="now()->startOfMonth()->toDateString()" :max="now()->toDateString()" :value="now()->toDateString()" />
        </x-form-field>
        <x-button variant="secondary">Buka</x-button>
    </form>

    @if ($validasi->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="Belum ada validasi kas" description="Lakukan validasi setiap akhir hari setelah kasir tutup." />
        </x-card>
    @else
        <x-table :headers="['Tanggal', 'Tunai Sistem', 'Uang Fisik', 'Selisih', 'Status', 'Catatan', 'Oleh', '']">
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

        <div class="mt-4">{{ $validasi->links() }}</div>
    @endif
</x-layouts::app>
