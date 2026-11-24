{{-- Pesan error validasi untuk satu field. Contoh: <x-input-error for="username" /> --}}
@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'mt-1 text-sm text-danger-600']) }}>{{ $message }}</p>
@enderror
