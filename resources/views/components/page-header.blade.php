{{--
    Judul halaman + tombol aksi.
    Contoh:
    <x-page-header title="Data Barang" subtitle="Daftar barang dagangan">
        <x-button :href="route('barang.create')" icon="plus">Tambah Barang</x-button>
    </x-page-header>
--}}
@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
