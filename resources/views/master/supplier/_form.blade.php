{{-- Form supplier (create dan edit). Variabel: $supplier, $kodeBerikutnya (hanya create) --}}
<div class="grid gap-4 sm:grid-cols-2">
    <x-form-field for="kode_supplier" label="Kode Supplier" bantuan="{{ $supplier->exists ? 'Kode tidak bisa diubah.' : 'Dibuat otomatis saat disimpan.' }}">
        <x-input name="kode_supplier" :value="$supplier->kode_supplier ?? $kodeBerikutnya" disabled />
    </x-form-field>

    <x-form-field for="status" label="Status" wajib>
        <x-select name="status" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']"
            :selected="old('status', $supplier->status)" />
    </x-form-field>

    <x-form-field for="nama_supplier" label="Nama Supplier" wajib class="sm:col-span-2">
        <x-input name="nama_supplier" :value="old('nama_supplier', $supplier->nama_supplier)" maxlength="100" required autofocus />
    </x-form-field>

    <x-form-field for="nama_kontak" label="Nama Kontak">
        <x-input name="nama_kontak" :value="old('nama_kontak', $supplier->nama_kontak)" maxlength="100" />
    </x-form-field>

    <x-form-field for="no_telepon" label="No. Telepon / WhatsApp">
        <x-input name="no_telepon" type="tel" :value="old('no_telepon', $supplier->no_telepon)" maxlength="20" />
    </x-form-field>

    <x-form-field for="alamat" label="Alamat" class="sm:col-span-2">
        <x-textarea name="alamat">{{ old('alamat', $supplier->alamat) }}</x-textarea>
    </x-form-field>
</div>
