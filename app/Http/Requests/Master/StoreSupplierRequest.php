<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

/**
 * kode_supplier tidak ada di form: dibuat otomatis (SUP-001) saat simpan dan tidak bisa diubah.
 */
class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kelola-master');
    }

    public function rules(): array
    {
        return [
            'nama_supplier' => ['required', 'string', 'max:100'],
            'nama_kontak' => ['nullable', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_supplier.required' => 'Nama supplier wajib diisi.',
            'no_telepon.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ];
    }
}
