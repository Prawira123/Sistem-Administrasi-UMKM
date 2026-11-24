@php
    $kembali = auth()->check() ? route('dashboard') : route('login');
@endphp

<x-layouts::guest title="Akses Ditolak">
    <x-card class="text-center">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-danger-100 text-danger-600">
            <x-icon name="lock-closed" class="size-7" />
        </div>
        <p class="text-sm font-semibold text-danger-600">403</p>
        <h1 class="mt-1 text-xl font-semibold text-gray-900">Akses Ditolak</h1>
        <p class="mt-2 text-sm text-gray-600">Anda tidak memiliki akses ke halaman ini.</p>

        <div class="mt-6">
            <x-button variant="secondary" :href="$kembali">
                {{ auth()->check() ? 'Kembali ke Dashboard' : 'Kembali ke Login' }}
            </x-button>
        </div>
    </x-card>
</x-layouts::guest>
