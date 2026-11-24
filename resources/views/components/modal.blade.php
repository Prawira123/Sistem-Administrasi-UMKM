{{--
    Modal konfirmasi (hapus/batal) dengan Alpine.js.
    Buka dengan event: <x-button type="button" variant="danger" x-data x-on:click="$dispatch('buka-modal', 'hapus-barang-1')">Hapus</x-button>

    <x-modal name="hapus-barang-1" title="Hapus barang?" :action="route('barang.destroy', $barang)" method="DELETE"
             confirm-label="Ya, hapus">
        Barang "{{ $barang->nama_barang }}" akan dihapus.
    </x-modal>

    Tanpa :action, isi footer sendiri lewat slot "footer".
--}}
@props([
    'name',
    'title' => 'Konfirmasi',
    'action' => null,
    'method' => 'POST',
    'confirmLabel' => 'Ya, lanjutkan',
    'cancelLabel' => 'Batal',
    'variant' => 'danger',
])

<div x-data="{ buka: false }"
    x-on:buka-modal.window="if ($event.detail === @js($name)) buka = true"
    x-on:tutup-modal.window="if ($event.detail === @js($name)) buka = false"
    x-on:keydown.escape.window="buka = false"
    x-show="buka" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">

    <div x-show="buka" x-transition.opacity class="absolute inset-0 bg-gray-900/50" @click="buka = false"></div>

    <div x-show="buka" x-transition class="relative w-full max-w-md rounded-xl bg-white shadow-xl">
        <div class="flex items-start gap-3 px-5 pt-5">
            @if ($variant === 'danger')
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-danger-100 text-danger-600">
                    <x-icon name="exclamation-triangle" />
                </div>
            @endif
            <div>
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <div class="mt-1 text-sm text-gray-600">{{ $slot }}</div>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2 rounded-b-xl bg-gray-50 px-5 py-3">
            @isset($footer)
                {{ $footer }}
            @else
                <x-button type="button" variant="secondary" x-on:click="buka = false">{{ $cancelLabel }}</x-button>
                @if ($action)
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @if (strtoupper($method) !== 'POST')
                            @method($method)
                        @endif
                        {{ $fields ?? '' }}
                        <x-button :variant="$variant">{{ $confirmLabel }}</x-button>
                    </form>
                @endif
            @endisset
        </div>
    </div>
</div>
