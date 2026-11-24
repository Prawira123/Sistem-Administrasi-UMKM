{{--
    Form pencarian/filter (GET) di atas tabel. Slot untuk filter tambahan (mis. x-select).
    Contoh: <x-search-bar :value="$cari" placeholder="Cari kode atau nama..." />
--}}
@props(['value' => '', 'placeholder' => 'Cari...', 'name' => 'q'])

<form method="GET" {{ $attributes->merge(['class' => 'mb-4 flex flex-wrap items-center gap-2']) }}>
    <div class="relative min-w-56 flex-1 sm:max-w-sm">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <x-icon name="search" class="size-4" />
        </span>
        <x-input :name="$name" type="search" :value="$value" :placeholder="$placeholder" class="pl-9" />
    </div>
    {{ $slot }}
    <x-button variant="secondary">Terapkan</x-button>
    @if (request()->query())
        <x-button variant="secondary" :href="url()->current()">Reset</x-button>
    @endif
</form>
