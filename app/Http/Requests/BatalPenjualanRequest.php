<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Pembatalan penjualan: khusus Administrator (gate batalkan-penjualan), alasan wajib.
 */
class BatalPenjualanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('batalkan-penjualan');
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan minimal 5 karakter.',
            'alasan.max' => 'Alasan pembatalan maksimal 255 karakter.',
        ];
    }
}
