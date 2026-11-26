<?php

namespace App\Http\Requests\StokOpname;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Admin menolak opname yang diajukan (kembali ke draft); catatan wajib. */
class TolakStokOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('setujui-opname');
    }

    public function rules(): array
    {
        return [
            'catatan' => ['required', 'string', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan.required' => 'Catatan penolakan wajib diisi.',
            'catatan.min' => 'Catatan penolakan minimal 5 karakter.',
            'catatan.max' => 'Catatan penolakan maksimal 200 karakter.',
        ];
    }
}
