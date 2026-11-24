{{--
    Tabel data dengan gulir horizontal di layar kecil.
    Contoh:
    <x-table :headers="['Kode', 'Nama', 'Stok']">
        <tr> <td class="px-4 py-2">...</td> </tr>
    </x-table>
    Kolom angka: beri class "text-right" pada <td>.
    Di dalam x-card pakai :polos="true" agar tanpa border/bayangan ganda.
--}}
@props(['headers' => [], 'polos' => false])

<div {{ $attributes->merge(['class' => $polos ? 'overflow-x-auto' : 'overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        @if (count($headers))
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-600">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-gray-100 text-gray-700 [&_td]:px-4 [&_td]:py-3">
            {{ $slot }}
        </tbody>
    </table>
</div>
