{{-- Form kategori barang (dipakai create dan edit). Variabel: $kategori --}}
<div class="grid gap-4">
    <x-form-field for="nama_kategori" label="Nama Kategori" wajib>
        <x-input name="nama_kategori" :value="old('nama_kategori', $kategori->nama_kategori)" maxlength="100" required autofocus />
    </x-form-field>

    <x-form-field for="keterangan" label="Keterangan">
        <x-textarea name="keterangan" maxlength="255">{{ old('keterangan', $kategori->keterangan) }}</x-textarea>
    </x-form-field>
</div>
