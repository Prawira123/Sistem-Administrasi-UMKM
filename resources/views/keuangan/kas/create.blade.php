@php
    $modal = $kategori === 'modal';
    $judul = $modal ? 'Catat Modal' : 'Catat Prive';
@endphp
<x-layouts::app :title="$judul">
    <x-page-header :title="$judul" :subtitle="$modal
        ? 'Modal: uang pemilik yang dimasukkan ke usaha (kas masuk). Bukan pendapatan.'
        : 'Prive: uang usaha yang diambil untuk keperluan pribadi (kas keluar). Bukan beban, tidak masuk laba rugi.'" />

    <form method="POST" action="{{ route('kas.store') }}" class="max-w-2xl">
        @csrf
        <input type="hidden" name="kategori" value="{{ $kategori }}">

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field for="tanggal" label="Tanggal" wajib>
                    <x-input name="tanggal" type="date" required :min="now()->startOfMonth()->toDateString()" :max="now()->toDateString()" :value="old('tanggal', now()->toDateString())" />
                </x-form-field>

                <x-form-field for="jumlah" label="Jumlah (Rp)" wajib>
                    <x-input name="jumlah" type="number" min="1" step="0.01" required class="text-right" :value="old('jumlah')" />
                </x-form-field>

                <x-form-field for="keterangan" label="Keterangan" wajib class="sm:col-span-2">
                    <x-input name="keterangan" :value="old('keterangan')" maxlength="255" required
                        :placeholder="$modal ? 'Mis. Tambahan modal dari pemilik' : 'Mis. Ambil uang untuk keperluan rumah'" />
                </x-form-field>
            </div>
            <x-input-error for="kategori" class="mt-2" />

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('kas.index')">Batal</x-button>
                    <x-button :variant="$modal ? 'primary' : 'danger'">{{ $modal ? 'Simpan Kas Masuk' : 'Simpan Kas Keluar' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
