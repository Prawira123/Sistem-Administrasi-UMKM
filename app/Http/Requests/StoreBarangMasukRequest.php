<?php

namespace App\Http\Requests;

use App\Support\Format;
use App\Support\Uang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form barang masuk. Subtotal dan total TIDAK diterima dari browser; server menghitung ulang di service.
 */
class StoreBarangMasukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Administrator dan Kasir (dibatasi middleware role).
    }

    public function rules(): array
    {
        return [
            'id_supplier' => ['required', 'integer', Rule::exists('supplier', 'id_supplier')->where('status', 'aktif')],
            'no_faktur' => ['nullable', 'string', 'max:50'],
            // Hanya bulan berjalan s.d. hari ini: kartu_stok memakai waktu pencatatan, sedangkan HPP periodik
            // (modul 7) menghitung pembelian dari tanggal_masuk, jadi keduanya harus jatuh di bulan yang sama.
            'tanggal_masuk' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.now()->startOfMonth()->toDateString(),
                'before_or_equal:today',
            ],
            'metode_pembayaran' => ['required', Rule::in(['tunai', 'transfer'])],
            'keterangan' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => [
                'required', 'integer', 'distinct',
                Rule::exists('barang', 'id_barang')->where('status', 'aktif'),
            ],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.harga_beli' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'items.*.tanggal_kadaluarsa' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_masuk'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_supplier.required' => 'Supplier wajib dipilih.',
            'id_supplier.exists' => 'Supplier tidak ditemukan atau sudah nonaktif.',
            'tanggal_masuk.after_or_equal' => 'Tanggal masuk harus di bulan berjalan (mulai '
                .Format::tanggal(now()->startOfMonth()).'). Barang masuk bulan lalu tidak bisa dicatat lagi.',
            'tanggal_masuk.before_or_equal' => 'Tanggal masuk tidak boleh melebihi hari ini.',
            'items.required' => 'Minimal satu barang harus diisi.',
            'items.min' => 'Minimal satu barang harus diisi.',
            'items.*.id_barang.required' => 'Barang pada baris ke-:position wajib dipilih.',
            'items.*.id_barang.distinct' => 'Barang pada baris ke-:position sudah ada di baris lain.',
            'items.*.id_barang.exists' => 'Barang pada baris ke-:position tidak ditemukan atau nonaktif.',
            'items.*.jumlah.required' => 'Jumlah pada baris ke-:position wajib diisi.',
            'items.*.jumlah.integer' => 'Jumlah pada baris ke-:position harus bilangan bulat.',
            'items.*.jumlah.min' => 'Jumlah pada baris ke-:position minimal 1.',
            'items.*.harga_beli.required' => 'Harga beli pada baris ke-:position wajib diisi.',
            'items.*.harga_beli.numeric' => 'Harga beli pada baris ke-:position harus berupa angka.',
            'items.*.harga_beli.decimal' => 'Harga beli pada baris ke-:position maksimal 2 angka desimal.',
            'items.*.harga_beli.min' => 'Harga beli pada baris ke-:position tidak boleh negatif.',
            'items.*.tanggal_kadaluarsa.after_or_equal' => 'Tanggal kedaluwarsa baris ke-:position tidak boleh sebelum tanggal masuk.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_supplier' => 'supplier',
            'no_faktur' => 'nomor faktur',
            'tanggal_masuk' => 'tanggal masuk',
            'metode_pembayaran' => 'metode pembayaran',
        ];
    }

    /** Total pembelian harus > 0 karena dicatat sebagai kas keluar (CHECK kas.jumlah > 0). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = '0.00';
                foreach ($this->input('items', []) as $item) {
                    $total = Uang::tambah($total, Uang::kali((int) $item['jumlah'], Uang::normal($item['harga_beli'])));
                }

                if (Uang::banding($total, '0') <= 0) {
                    $validator->errors()->add('items', 'Total pembelian harus lebih dari Rp0.');
                }
            },
        ];
    }
}
