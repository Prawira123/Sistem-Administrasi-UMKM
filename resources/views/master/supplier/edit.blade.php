<x-layouts::app title="Ubah Supplier">
    <x-page-header title="Ubah Supplier" :subtitle="$supplier->kode_supplier.' · '.$supplier->nama_supplier" />

    <form method="POST" action="{{ route('supplier.update', $supplier) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card>
            @include('master.supplier._form', ['kodeBerikutnya' => null])

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('supplier.index')">Batal</x-button>
                    <x-button>Simpan Perubahan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
