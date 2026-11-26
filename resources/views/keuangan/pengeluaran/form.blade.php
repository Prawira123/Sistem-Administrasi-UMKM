@php($baru = ! $pengeluaran->exists)
<x-layouts::app :title="$baru ? 'Catat Pengeluaran' : 'Ubah Pengeluaran '.$pengeluaran->kode_pengeluaran">
    <x-page-header :title="$baru ? 'Catat Pengeluaran' : 'Ubah Pengeluaran '.$pengeluaran->kode_pengeluaran"
        subtitle="Kas keluar kategori operasional dibuat/diperbarui otomatis sesuai jumlah ini." />

    <form method="POST" enctype="multipart/form-data" class="max-w-3xl"
        action="{{ $baru ? route('pengeluaran.store') : route('pengeluaran.update', $pengeluaran) }}">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field for="id_kategori_pengeluaran" label="Kategori" wajib>
                    <x-select name="id_kategori_pengeluaran" :options="$daftarKategori" placeholder="Pilih kategori" required
                        :selected="old('id_kategori_pengeluaran', $pengeluaran->id_kategori_pengeluaran)" />
                </x-form-field>

                <x-form-field for="tanggal_pengeluaran" label="Tanggal" wajib>
                    <x-input name="tanggal_pengeluaran" type="date" required :max="now()->toDateString()"
                        :value="old('tanggal_pengeluaran', $pengeluaran->tanggal_pengeluaran?->toDateString())" />
                </x-form-field>

                <x-form-field for="deskripsi" label="Deskripsi" wajib class="sm:col-span-2">
                    <x-input name="deskripsi" :value="old('deskripsi', $pengeluaran->deskripsi)" maxlength="255" required
                        placeholder="Mis. Token listrik November" />
                </x-form-field>

                <x-form-field for="jumlah" label="Jumlah (Rp)" wajib>
                    <x-input name="jumlah" type="number" min="1" step="0.01" required class="text-right"
                        :value="old('jumlah', $pengeluaran->jumlah !== null ? (float) $pengeluaran->jumlah : null)" />
                </x-form-field>

                <x-form-field for="metode_pembayaran" label="Metode Pembayaran" wajib>
                    <x-select name="metode_pembayaran" :options="['tunai' => 'Tunai', 'transfer' => 'Transfer']"
                        :selected="old('metode_pembayaran', $pengeluaran->metode_pembayaran)" />
                </x-form-field>

                <x-form-field for="bukti" label="Bukti / Foto Nota" bantuan="Opsional. JPG, PNG, atau PDF, maksimal 2 MB." class="sm:col-span-2">
                    <input type="file" name="bukti" id="bukti" accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100">
                    @if ($pengeluaran->bukti)
                        <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                            <a href="{{ route('pengeluaran.bukti', $pengeluaran) }}" target="_blank" class="font-medium text-primary-700 hover:underline">Lihat bukti saat ini</a>
                            <label class="inline-flex items-center gap-2 text-gray-600">
                                <input type="checkbox" name="hapus_bukti" value="1" class="rounded border-gray-300"> Hapus bukti
                            </label>
                        </div>
                    @endif
                </x-form-field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('pengeluaran.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
