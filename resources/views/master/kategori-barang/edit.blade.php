<x-layouts::app title="Ubah Kategori Barang">
    <x-page-header title="Ubah Kategori Barang" :subtitle="$kategori->nama_kategori" />

    <form method="POST" action="{{ route('kategori-barang.update', $kategori) }}" class="max-w-2xl">
        @csrf
        @method('PUT')
        <x-card>
            @include('master.kategori-barang._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('kategori-barang.index')">Batal</x-button>
                    <x-button>Simpan Perubahan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
