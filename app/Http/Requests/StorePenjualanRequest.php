<?php

namespace App\Http\Requests;

use App\Services\PenjualanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transaksi penjualan dari halaman kasir (POS).
 * Harga, subtotal, total, dan kembalian TIDAK diterima dari browser; server menghitung ulang di PenjualanService.
 * Cek stok cukup juga di service, karena harus dilakukan setelah baris barang dikunci.
 */
class StorePenjualanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Administrator dan Kasir (dibatasi middleware role).
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => [
                'required', 'integer', 'distinct',
                Rule::exists('barang', 'id_barang')->where('status', 'aktif'),
            ],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'metode_pembayaran' => ['required', Rule::in(PenjualanService::METODE)],
            'jumlah_bayar' => [
                Rule::requiredIf($this->input('metode_pembayaran') === 'tunai'),
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
            'items.*.id_barang.required' => 'Barang pada baris ke-:position wajib dipilih.',
            'items.*.id_barang.distinct' => 'Barang pada baris ke-:position sudah ada di keranjang.',
            'items.*.id_barang.exists' => 'Barang pada baris ke-:position tidak ditemukan atau nonaktif.',
            'items.*.jumlah.required' => 'Jumlah pada baris ke-:position wajib diisi.',
            'items.*.jumlah.integer' => 'Jumlah pada baris ke-:position harus bilangan bulat.',
            'items.*.jumlah.min' => 'Jumlah pada baris ke-:position minimal 1.',
            'metode_pembayaran.in' => 'Metode pembayaran harus tunai, QRIS, atau transfer.',
            'jumlah_bayar.required' => 'Jumlah bayar wajib diisi untuk pembayaran tunai.',
            'jumlah_bayar.numeric' => 'Jumlah bayar harus berupa angka.',
            'jumlah_bayar.decimal' => 'Jumlah bayar maksimal 2 angka desimal.',
            'jumlah_bayar.min' => 'Jumlah bayar tidak boleh negatif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'metode_pembayaran' => 'metode pembayaran',
            'jumlah_bayar' => 'jumlah bayar',
        ];
    }
}
