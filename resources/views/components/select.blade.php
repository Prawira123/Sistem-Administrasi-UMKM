{{--
    Dropdown pilihan.
    Contoh: <x-select name="id_kategori" :options="$kategori->pluck('nama_kategori', 'id_kategori')"
                      :selected="old('id_kategori', $barang->id_kategori)" placeholder="Pilih kategori" />
    Atau isi <option> sendiri lewat slot.
--}}
@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])

@php
    $error = $errors->has($name);
    $kelas = 'block w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 '
        .'disabled:bg-gray-100 '
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-100'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-100');
@endphp

<select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
    @if ($error) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge(['class' => $kelas]) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $nilai => $teks)
        <option value="{{ $nilai }}" @selected((string) $selected === (string) $nilai)>{{ $teks }}</option>
    @endforeach
    {{ $slot }}
</select>
