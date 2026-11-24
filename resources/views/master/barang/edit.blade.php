<x-layouts::app title="Ubah Barang">
    <x-page-header title="Ubah Barang" :subtitle="$barang->kode_barang.' · '.$barang->nama_barang" />

    <form method="POST" action="{{ route('barang.update', $barang) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card>
            @include('master.barang._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('barang.index')">Batal</x-button>
                    <x-button>Simpan Perubahan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
