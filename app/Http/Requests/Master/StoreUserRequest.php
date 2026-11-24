<?php

namespace App\Http\Requests\Master;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Password di-hash otomatis oleh cast 'hashed' di model User.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kelola-master');
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', $this->aturanUnik('username')],
            'email' => ['nullable', 'email', 'max:100', $this->aturanUnik('email')],
            'role' => ['required', Rule::in([User::ROLE_ADMINISTRATOR, User::ROLE_KASIR])],
            'status' => ['required', Rule::in([User::STATUS_AKTIF, User::STATUS_NONAKTIF])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah dipakai pengguna lain.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah (tanpa spasi).',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    protected function aturanUnik(string $kolom): Unique
    {
        return Rule::unique('users', $kolom);
    }
}
