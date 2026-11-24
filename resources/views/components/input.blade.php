{{--
    Input teks/angka/tanggal. Field yang error otomatis diberi border merah.
    Contoh: <x-input name="username" :value="old('username')" autofocus />
    Baris dinamis Alpine: kosongkan name dan pakai x-bind:name, mis.
            <x-input type="number" x-model="item.jumlah" x-bind:name="`items[${index}][jumlah]`" />
--}}
@props(['name' => null, 'type' => 'text', 'value' => null])

@php
    $error = $name !== null && $errors->has($name);
    $kelas = 'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 '
        .'disabled:bg-gray-100 disabled:text-gray-500 read-only:bg-gray-50 '
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-100'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-100');
    $id = $attributes->get('id', $name);
@endphp

<input type="{{ $type }}"
    @if ($name !== null) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if (! is_null($value) && $type !== 'password') value="{{ $value }}" @endif
    @if ($error) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge(['class' => $kelas]) }}>
