<?php

namespace App\Http\Requests\Keuangan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah/ubah pengeluaran operasional. Bukti opsional: jpg/png/pdf, maksimal 2 MB. */
class PengeluaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akses-keuangan');
    }

    public function rules(): array
    {
        return [
            'id_kategori_pengeluaran' => ['required', 'integer', Rule::exists('kategori_pengeluaran', 'id_kategori_pengeluaran')],
            'tanggal_pengeluaran' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->startOfMonth()->toDateString(), 'before_or_equal:today'],
            'deskripsi' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'metode_pembayaran' => ['required', Rule::in(['tunai', 'transfer'])],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'hapus_bukti' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_kategori_pengeluaran.required' => 'Kategori pengeluaran wajib dipilih.',
            'id_kategori_pengeluaran.exists' => 'Kategori pengeluaran tidak ditemukan.',
            'tanggal_pengeluaran.after_or_equal' => 'Tanggal pengeluaran harus di bulan berjalan (mulai '.now()->startOfMonth()->format('d/m/Y').').',
            'tanggal_pengeluaran.before_or_equal' => 'Tanggal pengeluaran tidak boleh melebihi hari ini.',
            'deskripsi.required' => 'Deskripsi wajib diisi.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.decimal' => 'Jumlah maksimal 2 angka desimal.',
            'jumlah.gt' => 'Jumlah harus lebih dari Rp0.',
            'bukti.mimes' => 'Bukti harus berupa file JPG, PNG, atau PDF.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tanggal_pengeluaran' => 'tanggal pengeluaran',
            'metode_pembayaran' => 'metode pembayaran',
        ];
    }
}
