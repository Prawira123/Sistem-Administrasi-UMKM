<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;

class UpdateKategoriBarangRequest extends StoreKategoriBarangRequest
{
    public function rules(): array
    {
        return [
            'nama_kategori' => [
                'required', 'string', 'max:100',
                Rule::unique('kategori_barang', 'nama_kategori')->ignore($this->route('kategori_barang')),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
