{{--
    Halaman kasir (POS). Kiri: scan/cari barang. Kanan: keranjang dan pembayaran.
    State keranjang di Alpine.js (resources/js/components/kasir-pos.js). Server menghitung ulang semua angka.
--}}
<x-layouts::app title="Kasir">
    <x-page-header title="Kasir (POS)" subtitle="Scan barcode atau ketik kode/nama barang, lalu tekan Enter. F2 kembali ke kotak scan.">
        <x-button variant="secondary" :href="route('penjualan.index')">Riwayat Penjualan</x-button>
    </x-page-header>

    <div x-data="kasirPos(@js([
            'urlCari' => route('barang.cari'),
            'keranjang' => $keranjang,
            'metode' => old('metode_pembayaran', 'tunai'),
            'bayar' => old('jumlah_bayar', ''),
        ]))"
        x-on:keydown.f2.window.prevent="fokusScan()"
        class="grid gap-6 lg:grid-cols-5">

        {{-- Kolom kiri: pencarian / scan --}}
        <div class="lg:col-span-2">
            <x-card title="Cari Barang">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <x-icon name="search" class="size-5" />
                    </span>
                    {{-- Input polos (bukan x-input) karena butuh padding kiri untuk ikon dan teks lebih besar. --}}
                    <input type="search" x-ref="scan" x-model="cari" x-on:input="ketik()" x-on:keydown.enter.prevent="enter()"
                        placeholder="Scan barcode / kode / nama barang" autocomplete="off" aria-label="Scan atau cari barang"
                        class="block w-full rounded-lg border border-gray-300 py-3 pr-3 pl-10 text-base shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none">
                </div>

                <p class="mt-2 text-xs text-gray-500" x-show="memuat" x-cloak>Mencari...</p>
                <p class="mt-2 text-sm text-warning-700" x-show="pesanCari" x-text="pesanCari" x-cloak></p>

                <ul class="mt-3 divide-y divide-gray-100" x-show="hasil.length" x-cloak>
                    <template x-for="b in hasil" :key="b.id">
                        <li>
                            <button type="button" x-on:click="tambah(b)"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2.5 text-left hover:bg-primary-50">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-gray-900" x-text="b.nama"></span>
                                    <span class="block text-xs text-gray-500">
                                        <span class="font-mono" x-text="b.kode"></span> · stok <span x-text="b.stok"></span> <span x-text="b.satuan"></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold whitespace-nowrap text-primary-700" x-text="rupiah(sen(b.harga_jual))"></span>
                            </button>
                        </li>
                    </template>
                </ul>

                <p class="mt-4 text-xs text-gray-500" x-show="!cari && !hasil.length">
                    Hanya barang aktif dengan stok tersedia yang bisa dijual.
                </p>
            </x-card>
        </div>

        {{-- Kolom kanan: keranjang dan pembayaran --}}
        <form method="POST" action="{{ route('penjualan.store') }}" class="lg:col-span-3" x-on:submit="simpan($event)">
            @csrf

            @if ($errors->any())
                <x-alert type="error" class="mb-4">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <x-card title="Keranjang" :padding="false">
                <x-slot:actions>
                    <x-button type="button" variant="secondary" size="sm" icon="trash" x-on:click="kosongkan()" x-show="keranjang.length">Kosongkan</x-button>
                </x-slot:actions>

                <template x-if="keranjang.length === 0">
                    <x-empty-state icon="shopping-cart" title="Keranjang kosong" description="Scan atau cari barang di sebelah kiri." />
                </template>

                <ul class="divide-y divide-gray-100" x-show="keranjang.length">
                    <template x-for="(item, index) in keranjang" :key="item.id">
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <input type="hidden" x-bind:name="`items[${index}][id_barang]`" x-bind:value="item.id">

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-900" x-text="item.nama"></p>
                                <p class="text-xs text-gray-500">
                                    <span x-text="rupiah(sen(item.harga_jual))"></span> / <span x-text="item.satuan"></span>
                                    · stok <span x-text="item.stok"></span>
                                </p>
                            </div>

                            <div class="flex items-center gap-1">
                                <button type="button" x-on:click="ubah(item, -1)" class="rounded-lg p-1.5 text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50" aria-label="Kurangi">
                                    <x-icon name="minus" class="size-4" />
                                </button>
                                <div class="w-16">
                                    <x-input type="number" min="1" step="1" x-bind:max="item.stok" x-model.number="item.jumlah" x-on:change="rapikan(item)"
                                        x-bind:name="`items[${index}][jumlah]`" class="text-center" aria-label="Jumlah" />
                                </div>
                                <button type="button" x-on:click="ubah(item, 1)" class="rounded-lg p-1.5 text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50" aria-label="Tambah">
                                    <x-icon name="plus" class="size-4" />
                                </button>
                            </div>

                            <p class="w-28 text-right font-semibold text-gray-900" x-text="rupiah(subtotalSen(item))"></p>

                            <button type="button" x-on:click="hapus(index)" class="rounded-lg p-1.5 text-gray-400 hover:bg-danger-50 hover:text-danger-600" aria-label="Hapus dari keranjang">
                                <x-icon name="x-mark" class="size-4" />
                            </button>
                        </li>
                    </template>
                </ul>

                <x-slot:footer>
                    <div class="space-y-4">
                        <div class="flex items-end justify-between">
                            <p class="text-sm text-gray-500"><span x-text="totalItem()"></span> unit</p>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Total</p>
                                <p class="text-3xl font-bold text-gray-900" x-text="rupiah(totalSen())"></p>
                            </div>
                        </div>

                        <div>
                            <x-label value="Metode Pembayaran" />
                            <div class="mt-1 grid grid-cols-3 gap-2">
                                @foreach (['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'] as $nilai => $label)
                                    <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium transition"
                                        x-bind:class="metode === '{{ $nilai }}' ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'">
                                        <input type="radio" name="metode_pembayaran" value="{{ $nilai }}" x-model="metode" class="sr-only">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="metode === 'tunai'">
                            <x-label for="jumlah_bayar" value="Jumlah Bayar (Rp)" />
                            <x-input name="jumlah_bayar" type="number" min="0" step="1" x-model="bayar"
                                x-bind:disabled="metode !== 'tunai'" class="mt-1" placeholder="0" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="(nominal, i) in tombolCepat()" :key="nominal">
                                    <button type="button" x-on:click="pilihNominal(nominal)"
                                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-primary-100 hover:text-primary-800"
                                        x-text="i === 0 ? 'Uang pas' : angka(nominal)"></button>
                                </template>
                            </div>
                        </div>

                        <p x-show="metode !== 'tunai'" x-cloak class="text-sm text-gray-500">
                            Pembayaran non-tunai dicatat sebesar total belanja (tanpa kembalian).
                        </p>

                        <div class="flex items-center justify-between rounded-lg px-4 py-3"
                            x-bind:class="kembalianSen() < 0 ? 'bg-danger-50 text-danger-700' : 'bg-success-50 text-success-700'">
                            <span class="text-sm font-medium" x-text="kembalianSen() < 0 ? 'Kurang bayar' : 'Kembalian'"></span>
                            <span class="text-xl font-bold" x-text="rupiah(Math.abs(kembalianSen()))"></span>
                        </div>

                        <x-button class="w-full" x-bind:disabled="!bisaSimpan()">
                            <span x-text="menyimpan ? 'Menyimpan...' : 'Simpan Transaksi'">Simpan Transaksi</span>
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-layouts::app>
