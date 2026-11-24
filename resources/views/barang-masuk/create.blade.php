<x-layouts::app title="Catat Barang Masuk">
    <x-page-header title="Catat Barang Masuk"
        subtitle="Stok bertambah, HPP rata-rata dihitung ulang, kartu stok dan kas keluar tercatat otomatis." />

    @php
        $errorItem = collect($errors->getMessages())->filter(fn ($pesan, $kunci) => str_starts_with($kunci, 'items.'));
    @endphp

    <form method="POST" action="{{ route('barang-masuk.store') }}"
        x-data="formBarangMasuk(@js(['barang' => $daftarBarang, 'items' => old('items', []), 'errors' => $errorItem]))">
        @csrf

        <x-card title="Data Faktur">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-form-field for="id_supplier" label="Supplier" wajib bantuan="Hanya supplier aktif yang tampil.">
                    <x-select name="id_supplier" :options="$daftarSupplier" :selected="old('id_supplier')" placeholder="Pilih supplier" required />
                </x-form-field>

                <x-form-field for="tanggal_masuk" label="Tanggal Masuk" wajib bantuan="Hanya tanggal di bulan berjalan, maksimal hari ini.">
                    <x-input name="tanggal_masuk" type="date" :value="old('tanggal_masuk', now()->toDateString())"
                        :min="now()->startOfMonth()->toDateString()" :max="now()->toDateString()" required />
                </x-form-field>

                <x-form-field for="no_faktur" label="No. Faktur / Nota Supplier">
                    <x-input name="no_faktur" :value="old('no_faktur')" maxlength="50" />
                </x-form-field>

                <x-form-field for="metode_pembayaran" label="Metode Pembayaran" wajib>
                    <x-select name="metode_pembayaran" :options="['tunai' => 'Tunai', 'transfer' => 'Transfer']"
                        :selected="old('metode_pembayaran', 'tunai')" />
                </x-form-field>

                <x-form-field for="keterangan" label="Keterangan" class="sm:col-span-2">
                    <x-input name="keterangan" :value="old('keterangan')" maxlength="255" />
                </x-form-field>
            </div>
        </x-card>

        <x-card title="Daftar Barang" class="mt-6">
            <x-slot:actions>
                <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="tambah()">Tambah Baris</x-button>
            </x-slot:actions>

            <x-input-error for="items" class="mb-3" />

            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="item.key">
                    <div class="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-12 md:items-start">
                        {{-- Barang: kotak cari atau barang terpilih --}}
                        <div class="md:col-span-4">
                            <x-label value="Barang" />
                            <input type="hidden" x-bind:name="`items[${index}][id_barang]`" x-bind:value="item.id_barang">

                            <template x-if="item.id_barang">
                                <div class="flex items-start justify-between gap-2 rounded-lg bg-primary-50 px-3 py-2">
                                    <div class="min-w-0 text-sm">
                                        <p class="truncate font-medium text-gray-900" x-text="dataBarang(item.id_barang)?.nama"></p>
                                        <p class="text-xs text-gray-500">
                                            <span class="font-mono" x-text="dataBarang(item.id_barang)?.kode"></span>
                                            · stok <span x-text="dataBarang(item.id_barang)?.stok"></span>
                                            <span x-text="dataBarang(item.id_barang)?.satuan"></span>
                                        </p>
                                    </div>
                                    <button type="button" class="text-xs font-medium text-primary-700 hover:underline" x-on:click="ganti(item)">Ganti</button>
                                </div>
                            </template>

                            <template x-if="!item.id_barang">
                                <div class="relative" x-on:click.outside="item.buka = false">
                                    <x-input type="search" placeholder="Scan barcode / cari kode atau nama" autocomplete="off"
                                        x-model="item.cari" x-on:focus="item.buka = true" x-on:input="item.buka = true"
                                        x-on:keydown.enter.prevent="pilihDenganEnter(item)" />
                                    <ul x-show="item.buka" x-cloak
                                        class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                        <template x-for="b in hasilCari(item)" :key="b.id">
                                            <li>
                                                <button type="button" class="block w-full px-3 py-2 text-left hover:bg-primary-50" x-on:click="pilih(item, b)">
                                                    <span class="font-medium text-gray-900" x-text="b.nama"></span>
                                                    <span class="block text-xs text-gray-500">
                                                        <span class="font-mono" x-text="b.kode"></span> · stok <span x-text="b.stok"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="hasilCari(item).length === 0" class="px-3 py-2 text-gray-500">Barang tidak ditemukan.</li>
                                    </ul>
                                </div>
                            </template>
                            <p class="mt-1 text-sm text-danger-600" x-show="galat(index, 'id_barang')" x-text="galat(index, 'id_barang')"></p>
                        </div>

                        <div class="md:col-span-2">
                            <x-label value="Jumlah" />
                            <x-input type="number" min="1" step="1" required x-model="item.jumlah" x-bind:name="`items[${index}][jumlah]`" />
                            <p class="mt-1 text-sm text-danger-600" x-show="galat(index, 'jumlah')" x-text="galat(index, 'jumlah')"></p>
                        </div>

                        <div class="md:col-span-2">
                            <x-label value="Harga Beli / Unit" />
                            <x-input type="number" min="0" step="0.01" required x-model="item.harga_beli" x-bind:name="`items[${index}][harga_beli]`" />
                            <p class="mt-1 text-sm text-danger-600" x-show="galat(index, 'harga_beli')" x-text="galat(index, 'harga_beli')"></p>
                        </div>

                        <div class="md:col-span-2">
                            <x-label value="Kedaluwarsa" />
                            <x-input type="date" x-model="item.tanggal_kadaluarsa" x-bind:name="`items[${index}][tanggal_kadaluarsa]`" />
                            <p class="mt-1 text-sm text-danger-600" x-show="galat(index, 'tanggal_kadaluarsa')" x-text="galat(index, 'tanggal_kadaluarsa')"></p>
                        </div>

                        <div class="flex items-end justify-between gap-2 md:col-span-2 md:flex-col md:items-end">
                            <div class="md:text-right">
                                <p class="text-xs text-gray-500">Subtotal</p>
                                <p class="font-semibold text-gray-900" x-text="rupiah(subtotalSen(item))"></p>
                            </div>
                            <x-button type="button" variant="secondary" size="sm" icon="trash" x-on:click="hapus(index)"
                                x-bind:disabled="items.length === 1">Hapus</x-button>
                        </div>
                    </div>
                </template>
            </div>

            <x-slot:footer>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Total pembelian (<span x-text="items.length"></span> barang)</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="rupiah(totalSen())"></p>
                        <p class="text-xs text-gray-500">Total final dihitung ulang oleh sistem saat disimpan.</p>
                    </div>
                    <div class="flex gap-2">
                        <x-button variant="secondary" :href="route('barang-masuk.index')">Batal</x-button>
                        <x-button>Simpan Barang Masuk</x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
