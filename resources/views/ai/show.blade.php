<x-layouts::app title="Detail Riwayat AI">
    <x-page-header title="Detail Riwayat AI" :subtitle="\App\Support\Format::tanggalWaktu($riwayat->created_at).' · '.$riwayat->user->nama">
        <x-button variant="secondary" :href="route('ai.riwayat')">Kembali</x-button>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            <x-card>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Topik (klasifikasi kata kunci)</dt>
                        <dd class="font-medium text-gray-900">{{ ucfirst($riwayat->topik) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-badge :status="$riwayat->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Model</dt>
                        <dd class="font-mono text-gray-900">{{ $riwayat->model }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Waktu respons</dt>
                        <dd class="text-gray-900">{{ $riwayat->waktu_respons_ms !== null ? number_format($riwayat->waktu_respons_ms, 0, ',', '.').' ms' : '-' }}</dd>
                    </div>
                    @if ($riwayat->pesan_error)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Pesan error</dt>
                            <dd class="text-danger-700">{{ $riwayat->pesan_error }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Pertanyaan">
                <p class="text-sm whitespace-pre-line text-gray-900">{{ $riwayat->pertanyaan }}</p>
            </x-card>

            <x-card title="Jawaban">
                @if ($riwayat->isBerhasil())
                    <div class="prose prose-sm max-w-none">{{ $riwayat->jawabanHtml() }}</div>
                @else
                    <p class="text-sm text-danger-700">{{ \App\Services\AiAssistantService::PESAN_GAGAL }}</p>
                @endif
            </x-card>
        </div>

        <x-card title="Data yang dikirim ke Gemini (konteks JSON)">
            <pre class="max-h-[70vh] overflow-auto rounded-lg bg-gray-900 p-4 text-xs leading-relaxed text-gray-100">{{ json_encode($riwayat->konteks_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            <p class="mt-2 text-xs text-gray-500">Gemini hanya menerima data ini (tanpa akses database dan tanpa data pengguna).</p>
        </x-card>
    </div>
</x-layouts::app>
