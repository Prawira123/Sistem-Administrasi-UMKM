{{--
    Tampilan saat data kosong.
    Contoh: <x-empty-state title="Belum ada barang" description="Tambahkan barang pertama." />
--}}
@props(['title' => 'Belum ada data', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <h3 class="text-sm font-semibold text-gray-800">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
