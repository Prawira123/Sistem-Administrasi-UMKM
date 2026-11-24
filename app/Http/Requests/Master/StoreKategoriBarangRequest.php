<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKategoriBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kelola-master');
    }

    public function rules(): array
    {
        return [
            'nama_kategori' => ['required', 'string', 'max:100', Rule::unique('kategori_barang', 'nama_kategori')],
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
