<?php

namespace App\Http\Requests\Master;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * Ubah pengguna. Password boleh dikosongkan; bila diisi berarti reset password.
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        $aturan = parent::rules();
        $aturan['password'] = ['nullable', 'string', 'min:8', 'confirmed'];

        return $aturan;
    }

    /**
     * Administrator tidak boleh menonaktifkan akunnya sendiri atau menurunkan role-nya sendiri,
     * agar tidak terkunci dari sistem.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->sedangMengubahDiriSendiri()) {
                    return;
                }

                if ($this->input('status') === User::STATUS_NONAKTIF) {
                    $validator->errors()->add('status', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
                }

                if ($this->input('role') !== User::ROLE_ADMINISTRATOR) {
                    $validator->errors()->add('role', 'Anda tidak bisa mengubah role akun Anda sendiri.');
                }
            },
        ];
    }

    protected function aturanUnik(string $kolom): Unique
    {
        return Rule::unique('users', $kolom)->ignore($this->route('user'));
    }

    private function sedangMengubahDiriSendiri(): bool
    {
        return $this->route('user')?->is($this->user()) ?? false;
    }
}
