{{--
    Baris filter (form GET) di atas tabel: pencarian, filter tambahan (slot), tombol Terapkan, dan Reset.
    Semua elemen h-10 dalam satu baris (flex-wrap: turun ke baris baru di layar sempit).

    Contoh:
    <x-filter-bar :cari="$cari" placeholder="Cari kode atau nama...">
        <x-select name="kategori" :options="$daftarKategori" :selected="$idKategori" placeholder="Semua kategori" class="w-48" />
    </x-filter-bar>

    Prop:
    - cari        : nilai pencarian; null = kotak pencarian tidak ditampilkan.
    - name        : nama parameter pencarian (default q).
    - dari/sampai : nilai rentang tanggal (Y-m-d); diisi salah satu = input tanggal "dari – sampai" ditampilkan.
    - tombol/ikon : label dan ikon tombol kirim (default Terapkan, funnel; ikon null = tanpa ikon).
    - reset       : URL tombol Reset; false = tanpa Reset. Default: halaman ini tanpa query, tampil bila ada query.
    - action      : URL tujuan form (default halaman ini).
    Pilih/isian di slot: beri class="w-48" (select) atau w-44 (tanggal) agar lebarnya seragam.
--}}
@props([
    'cari' => null,
    'name' => 'q',
    'placeholder' => 'Cari...',
    'dari' => null,
    'sampai' => null,
    'tombol' => 'Terapkan',
    'ikon' => 'funnel',
    'reset' => null,
    'action' => null,
])

@php
    $urlReset = $reset === false ? null : ($reset ?? url()->current());
    $tampilReset = $urlReset && request()->query();
    $periode = $dari !== null || $sampai !== null;
@endphp

<form method="GET" @if ($action) action="{{ $action }}" @endif
    {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 print:hidden']) }}>
    @if ($cari !== null)
        <div class="relative min-w-56 flex-1 sm:max-w-md">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
            <x-input :name="$name" type="search" :value="$cari" :placeholder="$placeholder" class="pl-9" aria-label="Pencarian" />
        </div>
    @endif

    @if ($periode)
        <div class="flex items-center gap-2">
            <x-input name="dari" type="date" :value="$dari" class="w-44" aria-label="Dari tanggal" title="Dari tanggal" />
            <span class="text-sm text-gray-500">s.d.</span>
            <x-input name="sampai" type="date" :value="$sampai" class="w-44" aria-label="Sampai tanggal" title="Sampai tanggal" />
        </div>
    @endif

    {{ $slot }}

    <div class="flex items-center gap-2">
        <x-button variant="secondary" :icon="$ikon">{{ $tombol }}</x-button>
        @if ($tampilReset)
            <x-button variant="secondary" :href="$urlReset" class="text-gray-500">Reset</x-button>
        @endif
    </div>
</form>
