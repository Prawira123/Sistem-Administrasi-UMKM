{{--
    Dropdown pilihan: appearance-none + ikon chevron yang sama di semua halaman, tinggi h-10.
    Contoh: <x-select name="id_kategori" :options="$kategori->pluck('nama_kategori', 'id_kategori')"
                      :selected="old('id_kategori', $barang->id_kategori)" placeholder="Pilih kategori" />
    Atau isi <option> sendiri lewat slot.
    Class w-* (mis. w-48) dipasang di pembungkus agar chevron tetap di dalam kotak; default w-full.
--}}
@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])

@php
    $error = $errors->has($name);
    $kelasMasuk = (string) $attributes->get('class');
    preg_match_all('/(?:^|\s)((?:[a-z]+:)?w-\S+)/', $kelasMasuk, $cocok);
    $lebar = $cocok[1] ? implode(' ', $cocok[1]) : 'w-full';
    $kelasSisa = trim(preg_replace('/(?:^|\s)(?:[a-z]+:)?w-\S+/', ' ', $kelasMasuk));

    $kelas = 'block h-10 w-full cursor-pointer appearance-none rounded-lg border bg-white py-0 pr-9 pl-3 text-sm text-gray-900 shadow-xs '
        .'focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 '
        .($error
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500/20')
        .' '.$kelasSisa;
@endphp

<div class="relative {{ $lebar }}">
    <select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except(['id', 'class'])->merge(['class' => $kelas]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $nilai => $teks)
            <option value="{{ $nilai }}" @selected((string) $selected === (string) $nilai)>{{ $teks }}</option>
        @endforeach
        {{ $slot }}
    </select>
    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-gray-400" />
</div>
