{{-- Form filter periode (dari/sampai) untuk laporan penjualan. Tidak ikut dicetak (x-filter-bar memakai print:hidden). --}}
<x-filter-bar :dari="$dari->toDateString()" :sampai="$sampai->toDateString()" tombol="Tampilkan" :reset="false" />
