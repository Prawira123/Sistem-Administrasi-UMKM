{{--
    Satu baris form: label + input (slot) + pesan error + teks bantuan.
    Contoh:
    <x-form-field for="nama_barang" label="Nama Barang" wajib>
        <x-input name="nama_barang" :value="old('nama_barang', $barang->nama_barang)" />
    </x-form-field>
--}}
@props(['for', 'label', 'wajib' => false, 'bantuan' => null])

<div {{ $attributes }}>
    <x-label :for="$for" :value="$label" :wajib="$wajib" />
    {{ $slot }}
    @if ($bantuan)
        <p class="mt-1 text-xs text-gray-500">{{ $bantuan }}</p>
    @endif
    <x-input-error :for="$for" />
</div>
