{{-- Form pengguna (create dan edit). Variabel: $pengguna --}}
@php
    $diriSendiri = $pengguna->exists && $pengguna->is(auth()->user());
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-form-field for="nama" label="Nama Lengkap" wajib class="sm:col-span-2">
        <x-input name="nama" :value="old('nama', $pengguna->nama)" maxlength="100" required autofocus />
    </x-form-field>

    <x-form-field for="username" label="Username" wajib bantuan="Dipakai untuk login. Tanpa spasi.">
        <x-input name="username" :value="old('username', $pengguna->username)" maxlength="50" autocomplete="off" required />
    </x-form-field>

    <x-form-field for="email" label="Email" bantuan="Opsional.">
        <x-input name="email" type="email" :value="old('email', $pengguna->email)" maxlength="100" />
    </x-form-field>

    <x-form-field for="role" label="Role" wajib
        bantuan="{{ $diriSendiri ? 'Role akun Anda sendiri tidak bisa diubah.' : '' }}">
        <x-select name="role" :options="['administrator' => 'Administrator', 'kasir' => 'Kasir']"
            :selected="old('role', $pengguna->role)" />
    </x-form-field>

    <x-form-field for="status" label="Status" wajib
        bantuan="{{ $diriSendiri ? 'Akun Anda sendiri tidak bisa dinonaktifkan.' : 'Akun nonaktif tidak dapat login.' }}">
        <x-select name="status" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']"
            :selected="old('status', $pengguna->status)" />
    </x-form-field>
</div>

<div class="mt-6 border-t border-gray-200 pt-4">
    <h3 class="text-sm font-semibold text-gray-800">{{ $pengguna->exists ? 'Reset Password' : 'Password' }}</h3>
    <p class="mb-3 text-xs text-gray-500">
        {{ $pengguna->exists ? 'Kosongkan bila password tidak diubah.' : 'Minimal 8 karakter.' }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field for="password" label="{{ $pengguna->exists ? 'Password Baru' : 'Password' }}" :wajib="! $pengguna->exists">
            <x-input name="password" type="password" minlength="8" autocomplete="new-password" :required="! $pengguna->exists" />
        </x-form-field>

        <x-form-field for="password_confirmation" label="Konfirmasi Password" :wajib="! $pengguna->exists">
            <x-input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" :required="! $pengguna->exists" />
        </x-form-field>
    </div>
</div>
