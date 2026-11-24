{{-- Textarea. Contoh: <x-textarea name="keterangan" rows="3">{{ old('keterangan') }}</x-textarea> --}}
@props(['name'])

@php
    $error = $errors->has($name);
    $kelas = 'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 '
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-100'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-100');
@endphp

<textarea name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
    @if ($error) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge(['class' => $kelas, 'rows' => 3]) }}>{{ $slot }}</textarea>
