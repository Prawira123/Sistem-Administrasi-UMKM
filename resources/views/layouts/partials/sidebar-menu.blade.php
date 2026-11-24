{{--
    Menu sidebar sesuai hak akses (docs/rancangan.md bagian 1).
    - 'gate' null  : tampil untuk Administrator dan Kasir.
    - 'gate' diisi : tampil hanya bila @can(gate) lolos (khusus Administrator).
    - Route yang belum dibuat (modul berikutnya) tampil abu-abu dengan label "Segera".
--}}
@php
    $grupMenu = [
        null => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'gate' => null],
        ],
        'Data Master' => [
            ['label' => 'Data Barang', 'route' => 'barang.index', 'icon' => 'cube', 'gate' => null],
            ['label' => 'Kategori Barang', 'route' => 'kategori-barang.index', 'icon' => 'tag', 'gate' => 'kelola-master'],
            ['label' => 'Supplier', 'route' => 'supplier.index', 'icon' => 'truck', 'gate' => 'kelola-master'],
            ['label' => 'Pengguna', 'route' => 'pengguna.index', 'icon' => 'users', 'gate' => 'kelola-master'],
        ],
        'Transaksi' => [
            ['label' => 'Barang Masuk', 'route' => 'barang-masuk.index', 'icon' => 'arrow-down-tray', 'gate' => null],
            ['label' => 'Penjualan', 'route' => 'penjualan.index', 'icon' => 'shopping-cart', 'gate' => null],
        ],
        'Stok' => [
            ['label' => 'Monitoring Stok', 'route' => 'stok.index', 'icon' => 'chart-bar', 'gate' => null],
            ['label' => 'Stok Opname', 'route' => 'stok-opname.index', 'icon' => 'clipboard-check', 'gate' => null],
        ],
        'Keuangan' => [
            ['label' => 'Pengeluaran', 'route' => 'pengeluaran.index', 'icon' => 'banknotes', 'gate' => 'akses-keuangan'],
            ['label' => 'Kas (Modal/Prive)', 'route' => 'kas.index', 'icon' => 'banknotes', 'gate' => 'akses-keuangan'],
            ['label' => 'Validasi Kas', 'route' => 'validasi-kas.index', 'icon' => 'check-circle', 'gate' => 'akses-keuangan'],
            ['label' => 'Perhitungan HPP', 'route' => 'hpp.index', 'icon' => 'calculator', 'gate' => 'akses-keuangan'],
        ],
        'Laporan' => [
            ['label' => 'Laporan', 'route' => 'laporan.index', 'icon' => 'document-text', 'gate' => 'akses-laporan'],
        ],
        'Asisten' => [
            ['label' => 'AI Assistant', 'route' => 'ai.index', 'icon' => 'sparkles', 'gate' => 'akses-ai'],
        ],
    ];
@endphp

@foreach ($grupMenu as $judulGrup => $items)
    @php
        $itemTampil = array_filter($items, fn ($item) => $item['gate'] === null || Gate::allows($item['gate']));
    @endphp

    @if (count($itemTampil))
        @if ($judulGrup)
            <p class="mt-5 mb-1 px-3 text-xs font-semibold tracking-wider text-primary-300 uppercase">{{ $judulGrup }}</p>
        @endif

        <ul class="space-y-0.5">
            @foreach ($itemTampil as $item)
                <li>
                    @if (Route::has($item['route']))
                        @php
                            $prefix = \Illuminate\Support\Str::beforeLast($item['route'], '.');
                            $aktif = request()->routeIs($item['route'], $prefix.'.*');
                        @endphp
                        <a href="{{ route($item['route']) }}"
                            @class([
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                'bg-white/15 font-medium text-white' => $aktif,
                                'text-primary-100 hover:bg-white/10 hover:text-white' => ! $aktif,
                            ])
                            @if ($aktif) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm text-primary-100/40"
                            title="Fitur ini dibuat pada modul berikutnya">
                            <x-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                            <span class="ml-auto rounded bg-white/10 px-1.5 text-[10px]">Segera</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endforeach
