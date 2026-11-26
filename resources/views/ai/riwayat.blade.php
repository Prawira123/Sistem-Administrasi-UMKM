<x-layouts::app title="Riwayat AI Assistant">
    <x-page-header title="Riwayat AI Assistant" subtitle="Semua pertanyaan, topik, status, dan waktu respons. Buka detail untuk melihat data JSON yang dikirim.">
        <x-button :href="route('ai.index')" icon="sparkles">Tanya AI</x-button>
    </x-page-header>

    <x-search-bar :value="$cari" placeholder="Cari pertanyaan...">
        <x-select name="status" :options="['berhasil' => 'Berhasil', 'gagal' => 'Gagal']" :selected="request('status')" placeholder="Semua status" />
    </x-search-bar>

    @if ($riwayat->isEmpty())
        <x-card>
            <x-empty-state icon="sparkles" title="Belum ada riwayat" description="Pertanyaan ke AI Assistant akan tercatat di sini." />
        </x-card>
    @else
        <x-table :headers="['Waktu', 'Pertanyaan', 'Topik', 'Status', 'Respons', 'Model', 'Oleh', '']">
            @foreach ($riwayat as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($r->created_at) }}</td>
                    <td class="max-w-md">{{ \Illuminate\Support\Str::limit($r->pertanyaan, 90) }}</td>
                    <td><x-badge :status="$r->topik">{{ ucfirst($r->topik) }}</x-badge></td>
                    <td><x-badge :status="$r->status" /></td>
                    <td class="text-right whitespace-nowrap">{{ $r->waktu_respons_ms !== null ? number_format($r->waktu_respons_ms, 0, ',', '.').' ms' : '-' }}</td>
                    <td class="font-mono text-xs">{{ $r->model }}</td>
                    <td class="whitespace-nowrap">{{ $r->user->nama }}</td>
                    <td><x-button variant="secondary" size="sm" :href="route('ai.show', $r)">Detail</x-button></td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $riwayat->links() }}</div>
    @endif
</x-layouts::app>
