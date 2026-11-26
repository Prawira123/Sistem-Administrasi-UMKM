@php($baru = ! $kategori->exists)
<x-layouts::app :title="$baru ? 'Tambah Kategori Pengeluaran' : 'Ubah Kategori Pengeluaran'">
    <x-page-header :title="$baru ? 'Tambah Kategori Pengeluaran' : 'Ubah Kategori Pengeluaran'" />

    <form method="POST" action="{{ $baru ? route('kategori-pengeluaran.store') : route('kategori-pengeluaran.update', $kategori) }}" class="max-w-2xl">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <x-card>
            <div class="grid gap-4">
                <x-form-field for="nama_kategori" label="Nama Kategori" wajib>
                    <x-input name="nama_kategori" :value="old('nama_kategori', $kategori->nama_kategori)" maxlength="100" required autofocus />
                </x-form-field>

                <x-form-field for="keterangan" label="Keterangan">
                    <x-textarea name="keterangan" maxlength="255">{{ old('keterangan', $kategori->keterangan) }}</x-textarea>
                </x-form-field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('kategori-pengeluaran.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
