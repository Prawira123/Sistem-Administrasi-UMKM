{{--
    Pesan flash. Tanpa atribut: otomatis menampilkan session('success') dan session('error').
    Manual: <x-alert type="error">Stok tidak cukup.</x-alert>
--}}
@props(['type' => null])

@php
    $gaya = [
        'success' => ['bg-success-50 text-success-700 border-success-600/30', 'check-circle'],
        'error' => ['bg-danger-50 text-danger-700 border-danger-600/30', 'exclamation-triangle'],
        'warning' => ['bg-warning-50 text-warning-700 border-warning-600/30', 'exclamation-triangle'],
    ];

    $pesan = [];
    if ($type) {
        $pesan[] = [$type, $slot];
    } else {
        foreach (['success', 'error', 'warning'] as $jenis) {
            if (session()->has($jenis)) {
                $pesan[] = [$jenis, session($jenis)];
            }
        }
    }
@endphp

@foreach ($pesan as [$jenis, $isi])
    <div x-data="{ tampil: true }" x-show="tampil" role="alert"
        {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$gaya[$jenis][0]]) }}>
        <x-icon :name="$gaya[$jenis][1]" class="mt-0.5 size-5" />
        <div class="flex-1">{{ $isi }}</div>
        <button type="button" @click="tampil = false" class="opacity-60 hover:opacity-100" aria-label="Tutup">
            <x-icon name="x-mark" class="size-4" />
        </button>
    </div>
@endforeach
