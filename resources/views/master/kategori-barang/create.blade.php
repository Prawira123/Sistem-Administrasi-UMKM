<x-layouts::app title="Tambah Kategori Barang">
    <x-page-header title="Tambah Kategori Barang" />

    <form method="POST" action="{{ route('kategori-barang.store') }}" class="max-w-2xl">
        @csrf
        <x-card>
            @include('master.kategori-barang._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('kategori-barang.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
