{{--
    Tabel data dengan gulir horizontal di layar kecil. Sel memakai px-4 py-3.
    Contoh:
    <x-table :headers="['Kode', 'Nama', 'Stok', 'Aksi']" :kanan="['Stok']">
        <tr> <td>...</td> <td class="text-right">...</td> <td><x-table.aksi> ...tombol... </x-table.aksi></td> </tr>
    </x-table>
    - :kanan = daftar judul kolom angka; judulnya rata kanan. Beri juga class "text-right" pada <td> kolom itu.
    - Kolom aksi: bungkus tombol dengan <x-table.aksi> (sejajar, gap-2, tidak terlipat).
    - Di dalam x-card pakai :polos="true" agar tanpa border/bayangan ganda.
--}}
@props(['headers' => [], 'polos' => false, 'kanan' => []])

<div {{ $attributes->merge(['class' => $polos ? 'overflow-x-auto' : 'overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        @if (count($headers))
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" @class([
                            'px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap text-gray-600 uppercase',
                            'text-right' => in_array($header, $kanan, true),
                            'text-left' => ! in_array($header, $kanan, true),
                        ])>
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-gray-100 text-gray-700 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td.text-right]:tabular-nums">
            {{ $slot }}
        </tbody>
    </table>
</div>
