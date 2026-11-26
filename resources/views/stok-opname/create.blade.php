<x-layouts::app title="Buat Stok Opname">
    <x-page-header title="Buat Stok Opname"
        subtitle="Stok sistem diambil saat draft dibuat. Setelah itu hitung stok fisik di rak dan isi pada langkah berikutnya." />

    <form method="POST" action="{{ route('stok-opname.store') }}" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="space-y-4">
                <x-form-field for="id_kategori" label="Barang yang dihitung" bantuan="Kosongkan untuk menghitung semua barang aktif.">
                    <x-select name="id_kategori" :options="$daftarKategori" :selected="old('id_kategori')" placeholder="Semua barang aktif" />
                </x-form-field>

                <x-form-field for="keterangan" label="Keterangan">
                    <x-input name="keterangan" :value="old('keterangan')" maxlength="255" placeholder="Mis. opname akhir bulan rak minuman" />
                </x-form-field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('stok-opname.index')">Batal</x-button>
                    <x-button icon="clipboard-check">Buat Draft</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
