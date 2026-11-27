<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form barang.
 * - stok, hpp_rata_rata, harga_beli_terakhir TIDAK ada di aturan, sehingga walaupun dikirim
 *   tidak ikut ke $request->validated() dan tidak pernah berubah lewat CRUD.
 * - harga_jual hanya masuk aturan bila pengguna boleh 'ubah-harga-jual' (Administrator).
 *   Untuk Kasir field itu diabaikan di server walaupun dikirim.
 * - status hanya masuk aturan bila pengguna boleh 'hapus-barang' (Administrator). Barang baru dari Kasir
 *   memakai default kolom 'aktif'; saat diubah Kasir, status lama tetap.
 */
class StoreBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin dan Kasir boleh menambah/mengubah barang (dibatasi middleware role).
    }

    public function rules(): array
    {
        $aturan = [
            'kode_barang' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-_.]+$/', $this->aturanKodeUnik()],
            'id_kategori' => ['required', 'integer', Rule::exists('kategori_barang', 'id_kategori')],
            'nama_barang' => ['required', 'string', 'max:150'],
            'satuan' => ['required', 'string', 'max:20'],
            'stok_minimum' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];

        if ($this->bolehUbahHargaJual()) {
            $aturan['harga_jual'] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];
        }

        // Menonaktifkan barang setara dengan menariknya dari penjualan, jadi hanya untuk yang boleh menghapus barang.
        if ($this->bolehUbahStatus()) {
            $aturan['status'] = ['required', 'in:aktif,nonaktif'];
        }

        return $aturan;
    }

    public function messages(): array
    {
        return [
            'kode_barang.unique' => 'Kode/barcode barang sudah dipakai barang lain.',
            'kode_barang.regex' => 'Kode barang hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah.',
            'id_kategori.required' => 'Kategori wajib dipilih.',
            'id_kategori.exists' => 'Kategori yang dipilih tidak ditemukan.',
            'harga_jual.required' => 'Harga jual wajib diisi.',
        ];
    }

    protected function bolehUbahHargaJual(): bool
    {
        return $this->user()->can('ubah-harga-jual');
    }

    protected function bolehUbahStatus(): bool
    {
        return $this->user()->can('hapus-barang');
    }

    protected function aturanKodeUnik(): \Illuminate\Validation\Rules\Unique
    {
        return Rule::unique('barang', 'kode_barang');
    }
}
