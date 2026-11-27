{{--
    Tombol hapus kecil (outline merah agar tidak dominan di tabel) + modal konfirmasi (x-modal).
    Contoh: <x-delete-button :action="route('barang.destroy', $item)" :id="'barang-'.$item->id_barang"
                             :nama="$item->nama_barang" />
--}}
@props(['action', 'id', 'nama', 'label' => 'Hapus'])

<x-button type="button" variant="danger-outline" size="sm" icon="trash" x-on:click="$dispatch('buka-modal', 'hapus-{{ $id }}')">
    {{ $label }}
</x-button>

<x-modal :name="'hapus-'.$id" title="Hapus data?" :action="$action" method="DELETE" confirm-label="Ya, hapus">
    Data <strong>{{ $nama }}</strong> akan dihapus permanen. Data yang sudah dipakai transaksi tidak dapat dihapus.
</x-modal>
