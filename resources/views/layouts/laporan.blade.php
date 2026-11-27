{{--
    Layout bersama halaman laporan (web + cetak lewat tombol Cetak / Ctrl+P).
    Pemakaian:
    <x-layouts::laporan title="Laporan Arus Kas" :kop="$kop"> ... filter + isi laporan ... </x-layouts::laporan>

    - Di layar: tampil seperti halaman aplikasi biasa (layout app), diawali tombol Cetak/Unduh PDF dan kop toko.
    - Saat dicetak: aturan @media print di bawah berlaku untuk SEMUA laporan, sehingga hasil cetak seragam:
      A4 margin 15 mm; sidebar, header, tombol, filter, dan pesan flash disembunyikan; tabel lebar 100% dengan
      garis tipis; kolom angka/Rp (td.text-right) rata kanan tanpa terlipat; baris .baris-total tebal; kop di tengah.
    - Nota penjualan (struk 58/80 mm) TIDAK memakai layout ini; ukurannya diatur sendiri di penjualan/nota.blade.php.
    - CSS ditulis biasa (bukan @layer Tailwind) agar menang atas utility Tailwind tanpa mengubah tampilan layar.
--}}
@props(['title', 'kop'])

<x-layouts::app :title="$title">
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }

            html,
            body {
                background: #fff !important;
            }

            body {
                color: #111827;
                font-size: 10pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Bukan bagian laporan: pesan flash, tombol, dan form filter. (Sidebar/header sudah print:hidden di layout app.) */
            main > [role='alert'],
            .laporan-cetak form,
            .laporan-cetak button {
                display: none !important;
            }

            /* Kartu: tanpa bayangan dan sudut; kartu yang berisi tabel tidak diberi bingkai ganda. */
            .laporan-cetak .rounded-xl {
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .laporan-cetak .rounded-xl:has(table) {
                border: 0 !important;
            }
            .laporan-cetak .overflow-x-auto {
                overflow: visible !important;
            }
            .laporan-cetak .max-w-3xl {
                max-width: none !important;
            }

            /* Tabel: lebar penuh, garis tipis, judul kolom diulang di setiap halaman. */
            .laporan-cetak table {
                width: 100% !important;
                border-collapse: collapse;
                font-size: 9pt;
            }
            .laporan-cetak thead {
                display: table-header-group;
            }
            .laporan-cetak tr {
                break-inside: avoid;
            }
            .laporan-cetak th,
            .laporan-cetak td {
                border: 0.5pt solid #9ca3af !important;
                padding: 3pt 5pt !important;
            }
            .laporan-cetak th {
                background: #f3f4f6 !important;
                color: #111827 !important;
            }

            /* Kolom angka/Rp: rata kanan dan tidak terlipat. */
            .laporan-cetak th.text-right,
            .laporan-cetak td.text-right {
                text-align: right !important;
                white-space: nowrap !important;
            }

            /* Baris total/subtotal: tebal dengan garis atas lebih tegas. */
            .laporan-cetak .baris-total td {
                font-weight: 700 !important;
                border-top: 1pt solid #111827 !important;
            }

            /* Kop toko di tengah, judul laporan tidak terpisah dari isinya. */
            .laporan-cetak .kop-toko {
                text-align: center;
            }
            .laporan-cetak .judul-laporan {
                break-after: avoid;
            }
        }
    </style>

    <div class="laporan-cetak space-y-6">
        @include('laporan._kop')

        {{ $slot }}
    </div>
</x-layouts::app>
