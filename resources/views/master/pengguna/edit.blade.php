<x-layouts::app title="Ubah Pengguna">
    <x-page-header title="Ubah Pengguna" :subtitle="$pengguna->nama.' ('.$pengguna->username.')'" />

    <form method="POST" action="{{ route('pengguna.update', $pengguna) }}" class="max-w-3xl">
        @csrf
        @method('PUT')
        <x-card>
            @include('master.pengguna._form')

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('pengguna.index')">Batal</x-button>
                    <x-button>Simpan Perubahan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
