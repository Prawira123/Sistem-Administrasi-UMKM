{{-- Pembungkus tombol aksi di sel tabel: sejajar, gap-2, tidak terlipat. Contoh: <td><x-table.aksi>...</x-table.aksi></td> --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-2 whitespace-nowrap']) }}>
    {{ $slot }}
</div>
