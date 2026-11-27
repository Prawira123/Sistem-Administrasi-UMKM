<?php

namespace App\Http\Requests\Keuangan;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi kas harian. Catatan wajib bila ada selisih (dicek di ValidasiKasService). */
class StoreValidasiKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akses-keuangan');
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->startOfMonth()->toDateString(), 'before_or_equal:today', 'unique:validasi_kas,tanggal'],
            'uang_fisik' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.after_or_equal' => 'Validasi kas hanya untuk tanggal di bulan berjalan (mulai '.now()->startOfMonth()->format('d/m/Y').').',
            'tanggal.before_or_equal' => 'Validasi kas tidak bisa dibuat untuk tanggal yang akan datang.',
            'tanggal.unique' => 'Kas tanggal ini sudah divalidasi. Validasi hanya satu kali per tanggal.',
            'uang_fisik.required' => 'Uang fisik wajib diisi.',
            'uang_fisik.numeric' => 'Uang fisik harus berupa angka.',
            'uang_fisik.min' => 'Uang fisik tidak boleh negatif.',
        ];
    }
}
