<x-layouts::app :title="'Validasi Kas '.\App\Support\Format::tanggal($validasi->tanggal)">
    <x-page-header :title="'Validasi Kas '.\App\Support\Format::tanggal($validasi->tanggal)" :subtitle="'Divalidasi oleh '.$validasi->user->nama">
        <x-button variant="secondary" :href="route('validasi-kas.index')">Kembali</x-button>
    </x-page-header>

    <x-card>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-gray-500">Tunai di sistem</dt>
                <dd class="text-lg font-semibold text-gray-900">@rupiah($validasi->total_tunai_sistem)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Uang fisik</dt>
                <dd class="text-lg font-semibold text-gray-900">@rupiah($validasi->uang_fisik)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Selisih</dt>
                <dd @class([
                    'text-lg font-semibold',
                    'text-success-700' => (float) $validasi->selisih > 0,
                    'text-danger-700' => (float) $validasi->selisih < 0,
                ])>{{ (float) $validasi->selisih > 0 ? '+' : '' }}@rupiah($validasi->selisih)</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd><x-badge :status="$validasi->status" /></dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Catatan</dt>
                <dd class="text-gray-900">{{ $validasi->catatan ?? '-' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Kas penyesuaian</dt>
                <dd class="text-gray-900">
                    @if ($validasi->kas)
                        <x-badge :status="$validasi->kas->jenis">{{ ucfirst($validasi->kas->jenis) }}</x-badge>
                        @rupiah($validasi->kas->jumlah) · {{ \App\Support\Format::tanggalWaktu($validasi->kas->tanggal) }}
                    @else
                        Tidak ada (uang fisik sesuai).
                    @endif
                </dd>
            </div>
        </dl>
    </x-card>
</x-layouts::app>
