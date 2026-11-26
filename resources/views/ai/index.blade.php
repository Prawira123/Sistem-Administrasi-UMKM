{{--
    Halaman chat AI Assistant. Form dikirim biasa (POST → redirect); Alpine hanya menampilkan gelembung pertanyaan
    dan indikator "sedang menjawab" selama menunggu respons server. API key tidak pernah sampai ke browser.
--}}
<x-layouts::app title="AI Assistant">
    <x-page-header title="AI Assistant" subtitle="Tanya tentang stok dan keuangan toko dalam bahasa sehari-hari. Jawaban hanya berdasarkan data sistem.">
        <x-button variant="secondary" :href="route('ai.riwayat')">Riwayat</x-button>
    </x-page-header>

    @unless ($siap)
        <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800">
            <code>GEMINI_API_KEY</code> belum diisi di file <code>.env</code>. Pertanyaan tetap tersimpan, tetapi berstatus gagal.
        </div>
    @endunless

    <div class="mx-auto max-w-4xl"
        x-data="{
            pertanyaan: @js(old('pertanyaan', '')),
            menunggu: false,
            kirim() {
                if (this.pertanyaan.trim() === '' || this.menunggu) return;
                this.menunggu = true;
                this.$nextTick(() => { this.$refs.form.submit(); this.$refs.akhir.scrollIntoView({ behavior: 'smooth' }); });
            },
            pakaiContoh(teks) { this.pertanyaan = teks; this.kirim(); },
        }"
        x-init="$nextTick(() => $refs.akhir.scrollIntoView())">

        <x-card :padding="false">
            <div class="max-h-[60vh] min-h-64 space-y-5 overflow-y-auto p-5">
                @if ($percakapan->isEmpty())
                    <div class="py-6 text-center">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                            <x-icon name="sparkles" class="size-6" />
                        </span>
                        <p class="mt-3 font-medium text-gray-900">Mulai bertanya</p>
                        <p class="text-sm text-gray-500">Pilih contoh pertanyaan di bawah atau tulis sendiri.</p>
                    </div>
                @endif

                @foreach ($percakapan as $r)
                    <div id="riwayat-{{ $r->id_riwayat_ai }}" class="space-y-3">
                        {{-- Pertanyaan --}}
                        <div class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm whitespace-pre-line text-white">{{ $r->pertanyaan }}</div>
                        </div>
                        {{-- Jawaban --}}
                        <div class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <x-icon name="sparkles" class="size-4" />
                            </span>
                            <div class="max-w-[85%] min-w-0">
                                @if ($r->isBerhasil())
                                    <div class="prose prose-sm max-w-none rounded-2xl rounded-tl-sm bg-gray-100 px-4 py-3 text-gray-800">{{ $r->jawabanHtml() }}</div>
                                @else
                                    <div class="rounded-2xl rounded-tl-sm border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700">
                                        {{ \App\Services\AiAssistantService::PESAN_GAGAL }}
                                    </div>
                                @endif
                                <p class="mt-1 flex flex-wrap gap-x-2 text-xs text-gray-400">
                                    <span>{{ \App\Support\Format::tanggalWaktu($r->created_at) }}</span>
                                    <span>&middot; topik {{ $r->topik }}</span>
                                    @if ($r->waktu_respons_ms !== null)
                                        <span>&middot; {{ number_format($r->waktu_respons_ms / 1000, 1, ',', '.') }} dtk</span>
                                    @endif
                                    <a href="{{ route('ai.show', $r) }}" class="text-primary-600 hover:underline">&middot; detail &amp; data</a>
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Gelembung sementara saat menunggu jawaban --}}
                <template x-if="menunggu">
                    <div class="space-y-3">
                        <div class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm whitespace-pre-line text-white" x-text="pertanyaan"></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                <x-icon name="sparkles" class="size-4" />
                            </span>
                            <div class="flex items-center gap-2 rounded-2xl rounded-tl-sm bg-gray-100 px-4 py-3 text-sm text-gray-500">
                                <span class="flex gap-1">
                                    <span class="size-2 animate-bounce rounded-full bg-gray-400"></span>
                                    <span class="size-2 animate-bounce rounded-full bg-gray-400 [animation-delay:150ms]"></span>
                                    <span class="size-2 animate-bounce rounded-full bg-gray-400 [animation-delay:300ms]"></span>
                                </span>
                                Sedang menjawab...
                            </div>
                        </div>
                    </div>
                </template>
                <div x-ref="akhir"></div>
            </div>

            <x-slot:footer>
                <div class="mb-3 flex flex-wrap gap-2" x-show="!menunggu">
                    @foreach ($contoh as $c)
                        <button type="button" x-on:click="pakaiContoh(@js($c))"
                            class="rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 hover:bg-primary-100">
                            {{ $c }}
                        </button>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('ai.tanya') }}" x-ref="form" x-on:submit.prevent="kirim()">
                    @csrf
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <textarea name="pertanyaan" id="pertanyaan" rows="2" maxlength="{{ \App\Services\AiAssistantService::MAKS_PERTANYAAN }}"
                                x-model="pertanyaan" x-bind:readonly="menunggu" x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); kirim(); }"
                                placeholder="Tulis pertanyaan... (Enter untuk kirim, Shift+Enter baris baru)"
                                class="block w-full resize-none rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 read-only:bg-gray-100"></textarea>
                            <div class="mt-1 flex justify-between text-xs">
                                <x-input-error for="pertanyaan" />
                                <span class="ml-auto text-gray-400" x-text="pertanyaan.length + '/{{ \App\Services\AiAssistantService::MAKS_PERTANYAAN }}'"></span>
                            </div>
                        </div>
                        <x-button icon="sparkles" class="mb-5" x-bind:disabled="menunggu || pertanyaan.trim() === ''">Kirim</x-button>
                    </div>
                </form>
            </x-slot:footer>
        </x-card>

        <p class="mt-3 text-center text-xs text-gray-500">
            AI hanya menerima ringkasan data stok dan keuangan (tanpa data pengguna) dan bisa keliru. Periksa angka penting di menu Laporan.
        </p>
    </div>
</x-layouts::app>
