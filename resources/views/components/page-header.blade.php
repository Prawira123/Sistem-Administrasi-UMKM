{{--
    Judul halaman + tombol aksi, sejajar di atas (flex justify-between items-start).
    Contoh:
    <x-page-header title="Data Barang" subtitle="Daftar barang dagangan">
        <x-button :href="route('barang.create')" icon="plus">Tambah Barang</x-button>
    </x-page-header>
    Jarak ke blok berikutnya diatur oleh space-y-6 di <main> layout.
--}}
@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-4']) }}>
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 max-w-3xl text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
