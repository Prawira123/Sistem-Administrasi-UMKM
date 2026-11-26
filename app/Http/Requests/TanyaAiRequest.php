<?php

namespace App\Http\Requests;

use App\Services\AiAssistantService;
use Illuminate\Foundation\Http\FormRequest;

class TanyaAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akses-ai');
    }

    public function rules(): array
    {
        return ['pertanyaan' => ['required', 'string', 'max:'.AiAssistantService::MAKS_PERTANYAAN]];
    }

    public function messages(): array
    {
        return [
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'pertanyaan.max' => 'Pertanyaan maksimal :max karakter.',
        ];
    }
}
