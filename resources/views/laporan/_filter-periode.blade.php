{{-- Form filter periode (dari/sampai) untuk laporan penjualan. Tidak ikut dicetak. --}}
<form method="GET" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
    <x-form-field for="dari" label="Dari tanggal">
        <x-input name="dari" type="date" :value="$dari->toDateString()" />
    </x-form-field>
    <x-form-field for="sampai" label="Sampai tanggal">
        <x-input name="sampai" type="date" :value="$sampai->toDateString()" />
    </x-form-field>
    <x-button variant="secondary">Tampilkan</x-button>
</form>
