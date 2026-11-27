{{--
    Input teks/angka/tanggal. Tinggi seragam h-10, fokus ring teal; field yang error diberi border merah.
    Contoh: <x-input name="username" :value="old('username')" autofocus />
    Baris dinamis Alpine: kosongkan name dan pakai x-bind:name, mis.
            <x-input type="number" x-model="item.jumlah" x-bind:name="`items[${index}][jumlah]`" />
    Lebar default w-full; beri class w-* (mis. w-44) untuk lebar tetap.
--}}
@props(['name' => null, 'type' => 'text', 'value' => null])

@php
    $error = $name !== null && $errors->has($name);
    $lebarSendiri = preg_match('/(^|\s)w-/', (string) $attributes->get('class'));
    $kelas = 'block h-10 rounded-lg border bg-white px-3 text-sm text-gray-900 shadow-xs placeholder:text-gray-400 '
        .'focus:outline-none focus:ring-2 disabled:bg-gray-100 disabled:text-gray-500 read-only:bg-gray-50 '
        .($lebarSendiri ? '' : 'w-full ')
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20');
    $id = $attributes->get('id', $name);
@endphp

<input type="{{ $type }}"
    @if ($name !== null) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if (! is_null($value) && $type !== 'password') value="{{ $value }}" @endif
    @if ($error) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge(['class' => $kelas]) }}>
