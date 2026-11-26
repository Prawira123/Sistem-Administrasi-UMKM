<?php

namespace App\Http\Requests\StokOpname;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Buat draft stok opname: semua barang aktif atau satu kategori. */
class StoreStokOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Administrator dan Kasir (dibatasi middleware role).
    }

    public function rules(): array
    {
        return [
            'id_kategori' => ['nullable', 'integer', Rule::exists('kategori_barang', 'id_kategori')],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_kategori.exists' => 'Kategori tidak ditemukan.',
        ];
    }
}
