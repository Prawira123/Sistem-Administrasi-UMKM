{{--
    Tombol. Varian: primary (default), secondary, danger. Ukuran: md (default), sm (aksi di tabel).
    Bila diberi atribut href, dirender sebagai <a>.
    Contoh: <x-button>Simpan</x-button>, <x-button variant="secondary" href="{{ route('dashboard') }}">Kembali</x-button>
--}}
@props(['variant' => 'primary', 'type' => 'submit', 'href' => null, 'icon' => null, 'size' => 'md'])

@php
    $varian = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus-visible:outline-primary-600',
        'secondary' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-400',
        'danger' => 'bg-danger-600 text-white hover:bg-danger-700 focus-visible:outline-danger-600',
    ][$variant] ?? '';

    $ukuran = $size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-4 py-2 text-sm';

    $kelas = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium shadow-sm transition '.$ukuran.' '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 '.$varian;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
