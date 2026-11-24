<x-layouts::app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Selamat datang, '.$user->nama.' ('.$user->labelRole().') · '.\App\Support\Format::tanggal(now())" />

    @php
        $kartu = [
            ['label' => 'Barang aktif', 'nilai' => number_format($jumlahBarangAktif, 0, ',', '.'), 'icon' => 'cube',
                'warna' => 'bg-primary-100 text-primary-700', 'link' => route('barang.index')],
            ['label' => 'Stok menipis', 'nilai' => number_format($jumlahStokMenipis, 0, ',', '.'), 'icon' => 'exclamation-triangle',
                'warna' => $jumlahStokMenipis > 0 ? 'bg-warning-100 text-warning-700' : 'bg-success-100 text-success-700',
                'link' => route('barang.index', ['stok' => 'menipis'])],
        ];

        if ($user->isAdministrator()) {
            $kartu[] = ['label' => 'Penjualan hari ini', 'nilai' => \App\Support\Format::rupiah($penjualanHariIni), 'icon' => 'shopping-cart',
                'warna' => 'bg-success-100 text-success-700', 'link' => null];
            $kartu[] = ['label' => 'Saldo kas', 'nilai' => \App\Support\Format::rupiah($saldoKas), 'icon' => 'banknotes',
                'warna' => 'bg-primary-100 text-primary-700', 'link' => null];
        }
    @endphp

    <div @class(['grid gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => count($kartu) === 4])>
        @foreach ($kartu as $k)
            <x-card>
                <div class="flex items-center gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $k['warna'] }}">
                        <x-icon :name="$k['icon']" class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500">{{ $k['label'] }}</p>
                        <p class="truncate text-2xl font-semibold text-gray-900">{{ $k['nilai'] }}</p>
                        @if ($k['link'])
                            <a href="{{ $k['link'] }}" class="text-xs font-medium text-primary-700 hover:underline">Lihat detail</a>
                        @endif
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    <x-card class="mt-6" title="Barang dengan stok menipis" :padding="false">
        @if ($barangMenipis->isEmpty())
            <x-empty-state icon="check-circle" title="Semua stok aman"
                description="Tidak ada barang aktif dengan stok di bawah atau sama dengan stok minimum." />
        @else
            <x-table :headers="['Kode', 'Nama Barang', 'Kategori', 'Stok', 'Stok Min.', 'Status']" :polos="true">
                @foreach ($barangMenipis as $item)
                    <tr>
                        <td class="font-mono text-xs">{{ $item->kode_barang }}</td>
                        <td class="font-medium text-gray-900">{{ $item->nama_barang }}</td>
                        <td>{{ $item->kategori->nama_kategori }}</td>
                        <td class="text-right">{{ $item->stok }} {{ $item->satuan }}</td>
                        <td class="text-right">{{ $item->stok_minimum }}</td>
                        <td><x-badge :status="$item->statusStok()" /></td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        @if ($jumlahStokMenipis > $barangMenipis->count())
            <x-slot:footer>
                <a href="{{ route('barang.index', ['stok' => 'menipis']) }}" class="text-sm font-medium text-primary-700 hover:underline">
                    Lihat semua {{ $jumlahStokMenipis }} barang stok menipis
                </a>
            </x-slot:footer>
        @endif
    </x-card>
</x-layouts::app>
