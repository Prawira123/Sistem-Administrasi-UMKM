<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateBarangRequest extends StoreBarangRequest
{
    public function rules(): array
    {
        $aturan = parent::rules();

        // Saat ubah, kode barang wajib (tidak dibuat ulang otomatis).
        $aturan['kode_barang'][0] = 'required';

        return $aturan;
    }

    protected function aturanKodeUnik(): Unique
    {
        return Rule::unique('barang', 'kode_barang')->ignore($this->route('barang'));
    }
}
