<x-layouts::app title="Tambah Supplier">
    <x-page-header title="Tambah Supplier" />

    <form method="POST" action="{{ route('supplier.store') }}" class="max-w-3xl">
        @csrf
        <x-card>
            @include('master.supplier._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('supplier.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
