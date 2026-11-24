{{--
    Label status berwarna. Warna dipilih dari nilai status.
    Contoh: <x-badge status="menipis" />, <x-badge :status="$barang->status" />,
            <x-badge status="final">Disetujui</x-badge>
--}}
@props(['status'])

@php
    $warna = match ($status) {
        'aman', 'aktif', 'selesai', 'final', 'sesuai', 'berhasil', 'masuk' => 'bg-success-50 text-success-700 ring-success-600/20',
        'menipis', 'diajukan', 'baru', 'selisih', 'penyesuaian' => 'bg-warning-50 text-warning-700 ring-warning-600/20',
        'habis', 'nonaktif', 'batal', 'gagal', 'keluar' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
        'administrator' => 'bg-primary-50 text-primary-700 ring-primary-600/20',
        default => 'bg-gray-50 text-gray-600 ring-gray-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap $warna"]) }}>
    {{ $slot->isEmpty() ? \Illuminate\Support\Str::of($status)->replace('_', ' ')->ucfirst() : $slot }}
</span>
