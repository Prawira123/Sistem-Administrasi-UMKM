<?php

namespace App\Http\Requests\Keuangan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah/ubah kategori pengeluaran (khusus Administrator). */
class KategoriPengeluaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akses-keuangan');
    }

    public function rules(): array
    {
        return [
            'nama_kategori' => [
                'required', 'string', 'max:100',
                Rule::unique('kategori_pengeluaran', 'nama_kategori')->ignore($this->route('kategori_pengeluaran'), 'id_kategori_pengeluaran'),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.unique' => 'Nama kategori sudah ada.',
        ];
    }
}
