<x-layouts::app title="Tambah Barang">
    <x-page-header title="Tambah Barang" subtitle="Barang baru dimulai dengan stok 0. Stok bertambah lewat transaksi Barang Masuk." />

    <form method="POST" action="{{ route('barang.store') }}" class="max-w-3xl">
        @csrf
        <x-card>
            @include('master.barang._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('barang.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
