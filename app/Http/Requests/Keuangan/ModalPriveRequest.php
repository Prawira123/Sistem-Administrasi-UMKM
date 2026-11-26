<?php

namespace App\Http\Requests\Keuangan;

use App\Services\KasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Input manual buku kas: modal (kas masuk) atau prive (kas keluar untuk keperluan pribadi). */
class ModalPriveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akses-keuangan');
    }

    public function rules(): array
    {
        return [
            'kategori' => ['required', Rule::in(array_keys(KasService::KATEGORI_MANUAL))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jumlah' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.in' => 'Jenis transaksi harus modal atau prive.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh melebihi hari ini.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.decimal' => 'Jumlah maksimal 2 angka desimal.',
            'jumlah.gt' => 'Jumlah harus lebih dari Rp0.',
            'keterangan.required' => 'Keterangan wajib diisi.',
        ];
    }
}
