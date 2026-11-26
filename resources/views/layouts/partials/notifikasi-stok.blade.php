{{--
    Lonceng notifikasi stok minimum (dropdown Alpine.js).
    Data $jumlahBaru dan $daftar diisi view composer di AppServiceProvider (NotifikasiStokService::ringkasan()).
--}}
<div id="notifikasi-stok" class="relative" x-data="{ buka: false }" x-on:click.outside="buka = false" x-on:keydown.escape.window="buka = false">
    <button type="button" x-on:click="buka = !buka"
        class="relative inline-flex rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
        title="Notifikasi stok" aria-label="Notifikasi stok minimum ({{ $jumlahBaru }} baru)">
        <x-icon name="bell" class="size-6" />
        @if ($jumlahBaru > 0)
            <span data-jumlah-notifikasi
                class="absolute top-0.5 right-0.5 flex min-w-5 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] leading-5 font-semibold text-white">
                {{ $jumlahBaru > 99 ? '99+' : $jumlahBaru }}
            </span>
        @endif
    </button>

    <div x-show="buka" x-cloak x-transition
        class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <p class="text-sm font-semibold text-gray-800">Stok Minimum</p>
            @if ($jumlahBaru > 0)
                <form method="POST" action="{{ route('notifikasi-stok.baca-semua') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-primary-700 hover:underline">Tandai semua dibaca</button>
                </form>
            @endif
        </div>

        @if ($daftar->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada peringatan stok.</p>
        @else
            <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                @foreach ($daftar as $notif)
                    <li @class(['flex items-start gap-3 px-4 py-3', 'bg-warning-50/60' => $notif->status === 'baru'])>
                        <span @class([
                            'mt-1.5 size-2 shrink-0 rounded-full',
                            'bg-danger-600' => $notif->status === 'baru',
                            'bg-gray-300' => $notif->status !== 'baru',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-800">{{ $notif->pesan }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ \App\Support\Format::tanggalWaktu($notif->created_at) }}
                                · stok sekarang {{ $notif->barang->stok }} {{ $notif->barang->satuan }}
                            </p>
                        </div>
                        @if ($notif->status === 'baru')
                            <form method="POST" action="{{ route('notifikasi-stok.baca', $notif) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium whitespace-nowrap text-primary-700 hover:underline">Dibaca</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
            <span>Selesai otomatis setelah stok diisi ulang.</span>
            <a href="{{ route('stok.peringatan') }}" class="font-medium whitespace-nowrap text-primary-700 hover:underline">Lihat semua</a>
        </div>
    </div>
</div>
