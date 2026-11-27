<x-layouts::app title="Data Pengguna">
    <x-page-header title="Data Pengguna" subtitle="Akun Administrator dan Kasir. Akun nonaktif tidak dapat login.">
        <x-button :href="route('pengguna.create')" icon="plus">Tambah Pengguna</x-button>
    </x-page-header>

    <x-filter-bar :cari="$cari" placeholder="Cari nama atau username..." />

    @if ($pengguna->isEmpty())
        <x-card>
            <x-empty-state title="Pengguna tidak ditemukan" description="Tidak ada pengguna yang cocok dengan pencarian." />
        </x-card>
    @else
        <x-table :headers="['Nama', 'Username', 'Role', 'Status', 'Login Terakhir', 'Aksi']">
            @foreach ($pengguna as $item)
                <tr>
                    <td>
                        <p class="font-medium text-gray-900">{{ $item->nama }}
                            @if ($item->is(auth()->user()))
                                <span class="text-xs font-normal text-gray-500">(Anda)</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $item->email ?? '-' }}</p>
                    </td>
                    <td class="font-mono text-xs">{{ $item->username }}</td>
                    <td><x-badge :status="$item->role">{{ $item->labelRole() }}</x-badge></td>
                    <td><x-badge :status="$item->status" /></td>
                    <td class="whitespace-nowrap">{{ \App\Support\Format::tanggalWaktu($item->terakhir_login) }}</td>
                    <td>
                        <x-table.aksi>
                            <x-button variant="secondary" size="sm" icon="pencil" :href="route('pengguna.edit', $item)">Ubah</x-button>
                            @unless ($item->is(auth()->user()))
                                <x-delete-button :action="route('pengguna.destroy', $item)" :id="'pengguna-'.$item->id_user"
                                    :nama="$item->nama" />
                            @endunless
                        </x-table.aksi>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div>{{ $pengguna->links() }}</div>
    @endif
</x-layouts::app>
