{{-- Textarea, gaya sama dengan x-input. Contoh: <x-textarea name="keterangan" rows="3">{{ old('keterangan') }}</x-textarea> --}}
@props(['name'])

@php
    $error = $errors->has($name);
    $kelas = 'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 shadow-xs placeholder:text-gray-400 '
        .'focus:outline-none focus:ring-2 read-only:bg-gray-50 '
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20');
@endphp

<textarea name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
    @if ($error) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge(['class' => $kelas, 'rows' => 3]) }}>{{ $slot }}</textarea>
