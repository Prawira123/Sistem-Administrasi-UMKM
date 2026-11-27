{{--
    Form barang (create dan edit). Variabel: $barang, $daftarKategori
    - Tidak ada input stok, HPP, atau harga beli: nilainya hanya berubah lewat transaksi.
    - Input harga_jual hanya untuk Administrator (gate ubah-harga-jual); server juga mengabaikannya untuk Kasir.
--}}
<div class="grid gap-4 sm:grid-cols-2">
    <x-form-field for="kode_barang" label="Kode / Barcode" :wajib="$barang->exists"
        bantuan="{{ $barang->exists ? 'Bisa diisi barcode kemasan.' : 'Kosongkan untuk kode otomatis (BRG-0001). Bisa diisi barcode kemasan.' }}">
        <x-input name="kode_barang" :value="old('kode_barang', $barang->kode_barang)" maxlength="30" :required="$barang->exists" />
    </x-form-field>

    <x-form-field for="id_kategori" label="Kategori" wajib>
        <x-select name="id_kategori" :options="$daftarKategori" :selected="old('id_kategori', $barang->id_kategori)"
            placeholder="Pilih kategori" required />
    </x-form-field>

    <x-form-field for="nama_barang" label="Nama Barang" wajib class="sm:col-span-2">
        <x-input name="nama_barang" :value="old('nama_barang', $barang->nama_barang)" maxlength="150" required />
    </x-form-field>

    <x-form-field for="satuan" label="Satuan" wajib bantuan="Contoh: pcs, bungkus, botol, dus.">
        <x-input name="satuan" :value="old('satuan', $barang->satuan)" maxlength="20" list="daftar-satuan" required />
        <datalist id="daftar-satuan">
            <option value="pcs"></option>
            <option value="bungkus"></option>
            <option value="botol"></option>
            <option value="dus"></option>
            <option value="kg"></option>
        </datalist>
    </x-form-field>

    <x-form-field for="stok_minimum" label="Stok Minimum" wajib bantuan="Batas peringatan stok menipis.">
        <x-input name="stok_minimum" type="number" min="0" step="1" :value="old('stok_minimum', $barang->stok_minimum)" required />
    </x-form-field>

    @can('ubah-harga-jual')
        <x-form-field for="harga_jual" label="Harga Jual (Rp)" wajib bantuan="Harga jual per satuan.">
            <x-input name="harga_jual" type="number" min="0" step="0.01" :value="old('harga_jual', $barang->harga_jual ?? 0)" required />
        </x-form-field>
    @else
        <x-form-field for="harga_jual" label="Harga Jual" bantuan="Harga jual hanya dapat diatur oleh Administrator.">
            <x-input name="harga_jual_info" disabled
                :value="(float) $barang->harga_jual > 0 ? \App\Support\Format::rupiah($barang->harga_jual) : 'Belum diatur'" />
        </x-form-field>
    @endcan

    @can('hapus-barang')
        <x-form-field for="status" label="Status" wajib bantuan="Barang nonaktif tidak bisa dijual.">
            <x-select name="status" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" :selected="old('status', $barang->status)" />
        </x-form-field>
    @else
        <x-form-field for="status" label="Status" bantuan="Status barang hanya dapat diubah oleh Administrator.">
            <x-input name="status_info" disabled :value="ucfirst($barang->status ?? 'aktif')" />
        </x-form-field>
    @endcan
</div>

@if ($barang->exists)
    <div class="mt-6 grid gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-3">
        <div>
            <p class="text-gray-500">Stok saat ini</p>
            <p class="font-semibold text-gray-900">{{ $barang->stok }} {{ $barang->satuan }}
                <x-badge :status="$barang->statusStok()" class="ml-1" /></p>
        </div>
        <div>
            <p class="text-gray-500">HPP rata-rata</p>
            <p class="font-semibold text-gray-900">@rupiah($barang->hpp_rata_rata)</p>
        </div>
        <div>
            <p class="text-gray-500">Harga beli terakhir</p>
            <p class="font-semibold text-gray-900">@rupiah($barang->harga_beli_terakhir)</p>
        </div>
        <p class="text-xs text-gray-500 sm:col-span-3">Nilai di atas dihitung otomatis dari transaksi dan tidak dapat diubah di form ini.</p>
    </div>
@endif
