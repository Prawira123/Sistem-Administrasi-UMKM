<x-layouts::app title="Tambah Pengguna">
    <x-page-header title="Tambah Pengguna" />

    <form method="POST" action="{{ route('pengguna.store') }}" class="max-w-3xl">
        @csrf
        <x-card>
            @include('master.pengguna._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('pengguna.index')">Batal</x-button>
                    <x-button>Simpan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
