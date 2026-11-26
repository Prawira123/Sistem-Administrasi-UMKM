{{-- Selisih dihitung langsung di browser (sen, bilangan bulat) hanya untuk tampilan; server menghitung ulang. --}}
<x-layouts::app title="Validasi Kas">
    <x-page-header :title="'Validasi Kas '.\App\Support\Format::tanggal($tanggal)"
        subtitle="Hitung uang tunai di laci, lalu masukkan jumlahnya. Bila berbeda, kas penyesuaian dicatat otomatis." />

    <form method="POST" action="{{ route('validasi-kas.store') }}" class="max-w-2xl"
        x-data="{
            sistem: {{ (int) round((float) $totalTunaiSistem * 100) }},
            fisik: @js((string) old('uang_fisik', '')),
            selisih() { const n = Number(this.fisik); return this.fisik === '' || isNaN(n) ? null : Math.round(n * 100) - this.sistem; },
            rp(sen) { return (sen < 0 ? '-' : '') + 'Rp' + new Intl.NumberFormat('id-ID').format(Math.abs(sen) / 100); },
        }">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">

        <x-card>
            <x-input-error for="tanggal" class="mb-3" />

            <dl class="mb-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-sm text-gray-500">Total penjualan tunai di sistem</dt>
                    <dd class="text-2xl font-semibold text-gray-900">@rupiah($totalTunaiSistem)</dd>
                    <p class="mt-1 text-xs text-gray-500">Penjualan tunai berstatus selesai pada tanggal ini.</p>
                </div>
                <div class="rounded-lg p-4" x-bind:class="selisih() === null || selisih() === 0 ? 'bg-success-50' : 'bg-warning-50'">
                    <dt class="text-sm text-gray-500">Selisih (fisik − sistem)</dt>
                    <dd class="text-2xl font-semibold" x-bind:class="selisih() > 0 ? 'text-success-700' : (selisih() < 0 ? 'text-danger-700' : 'text-gray-900')"
                        x-text="selisih() === null ? '-' : (selisih() > 0 ? '+' : '') + rp(selisih())">-</dd>
                    <p class="mt-1 text-xs text-gray-500"
                        x-text="selisih() > 0 ? 'Uang lebih → kas penyesuaian masuk' : (selisih() < 0 ? 'Uang kurang → kas penyesuaian keluar' : 'Sesuai')"></p>
                </div>
            </dl>

            <div class="grid gap-4">
                <x-form-field for="uang_fisik" label="Uang tunai fisik (Rp)" wajib>
                    <x-input name="uang_fisik" type="number" min="0" step="0.01" required x-model="fisik" class="text-right" :value="old('uang_fisik')" autofocus />
                </x-form-field>

                <x-form-field for="catatan" label="Catatan" bantuan="Wajib bila ada selisih, mis. kembalian salah atau uang receh tertukar.">
                    <x-textarea name="catatan" maxlength="255" x-bind:required="selisih() !== null && selisih() !== 0">{{ old('catatan') }}</x-textarea>
                </x-form-field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('validasi-kas.index')">Batal</x-button>
                    <x-button>Simpan Validasi</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
