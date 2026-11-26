<x-layouts::app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Selamat datang, '.$user->nama.' ('.$user->labelRole().') · '.\App\Support\Format::tanggal(now())" />

    @php
        $kartu = [
            ['label' => 'Barang aktif', 'nilai' => number_format($jumlahBarangAktif, 0, ',', '.'), 'icon' => 'cube',
                'warna' => 'bg-primary-100 text-primary-700', 'link' => route('barang.index')],
            ['label' => 'Stok menipis', 'nilai' => number_format($jumlahStokMenipis, 0, ',', '.'), 'icon' => 'exclamation-triangle',
                'warna' => $jumlahStokMenipis > 0 ? 'bg-warning-100 text-warning-700' : 'bg-success-100 text-success-700',
                'link' => route('stok.peringatan')],
        ];

        if ($user->isAdministrator()) {
            $laba = $labaBulanIni['nilai'];
            $kartu = array_merge([
                ['label' => 'Penjualan hari ini', 'nilai' => \App\Support\Format::rupiah($penjualanHariIni), 'icon' => 'shopping-cart',
                    'warna' => 'bg-success-100 text-success-700', 'link' => route('laporan.penjualan')],
                ['label' => 'Laba bulan ini', 'icon' => 'calculator', 'nilai' => \App\Support\Format::rupiah($laba),
                    'warna' => \App\Support\Uang::banding($laba, '0') < 0 ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700',
                    // Estimasi: HPP periodik bulan berjalan belum dihitung → pakai HPP rata-rata dari tabel penjualan.
                    'catatan' => $labaBulanIni['estimasi'] ? 'Estimasi (HPP rata-rata)' : 'Laba bersih (HPP periodik)',
                    'link' => $labaBulanIni['estimasi'] ? route('hpp.index') : route('laporan.laba-rugi'),
                    'teksLink' => $labaBulanIni['estimasi'] ? 'Hitung HPP periodik' : 'Laporan laba rugi'],
                ['label' => 'Saldo kas', 'nilai' => \App\Support\Format::rupiah($saldoKas), 'icon' => 'banknotes',
                    'warna' => 'bg-primary-100 text-primary-700', 'link' => route('kas.index')],
            ], $kartu);
        }
    @endphp

    <div @class(['grid gap-4 sm:grid-cols-2', 'lg:grid-cols-3 2xl:grid-cols-5' => count($kartu) === 5])>
        @foreach ($kartu as $k)
            <x-card>
                <div class="flex items-center gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $k['warna'] }}">
                        <x-icon :name="$k['icon']" class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500">{{ $k['label'] }}</p>
                        <p class="truncate text-2xl font-semibold text-gray-900">{{ $k['nilai'] }}</p>
                        @isset($k['catatan'])
                            <p class="text-xs text-gray-500">{{ $k['catatan'] }}</p>
                        @endisset
                        @if ($k['link'])
                            <a href="{{ $k['link'] }}" class="text-xs font-medium text-primary-700 hover:underline">{{ $k['teksLink'] ?? 'Lihat detail' }}</a>
                        @endif
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    @if ($user->isAdministrator())
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2" x-data="grafikPenjualan(@js($grafik))">
                <x-card title="Grafik penjualan">
                    <x-slot:actions>
                        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 text-sm">
                            @foreach (['7' => '7 hari', '30' => '30 hari'] as $nilai => $label)
                                <button type="button" x-on:click="pilih('{{ $nilai }}')"
                                    x-bind:class="rentang === '{{ $nilai }}' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                                    class="rounded-md px-3 py-1 font-medium">{{ $label }}</button>
                            @endforeach
                        </div>
                    </x-slot:actions>
                    <div class="h-64"><canvas x-ref="kanvas" aria-label="Grafik total penjualan per hari"></canvas></div>
                </x-card>
            </div>

            <x-card title="5 barang terlaris (30 hari)" :padding="false">
                @if ($terlaris->isEmpty())
                    <x-empty-state icon="shopping-cart" title="Belum ada penjualan" description="Belum ada penjualan selesai dalam 30 hari terakhir." />
                @else
                    <ol class="divide-y divide-gray-100">
                        @foreach ($terlaris as $i => $b)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $b['nama'] }}</p>
                                    <p class="text-xs text-gray-500">@rupiah($b['omzet'])</p>
                                </div>
                                <span class="text-sm font-semibold whitespace-nowrap text-gray-700">{{ $b['qty'] }} {{ $b['satuan'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
                <x-slot:footer>
                    <a href="{{ route('laporan.penjualan') }}" class="text-sm font-medium text-primary-700 hover:underline">Laporan penjualan</a>
                </x-slot:footer>
            </x-card>
        </div>
    @endif

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
                <a href="{{ route('stok.peringatan') }}" class="text-sm font-medium text-primary-700 hover:underline">
                    Lihat semua {{ $jumlahStokMenipis }} barang stok menipis
                </a>
            </x-slot:footer>
        @endif
    </x-card>
</x-layouts::app>
