<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUserRequest;
use App\Http\Requests\Master\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 2.0 Kelola Data Master: pengguna (khusus Administrator).
 * Termasuk reset password (isi password baru di form ubah) dan aktif/nonaktif.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('kelola-master');

        $cari = trim((string) $request->query('q'));

        $pengguna = User::query()
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")
                    ->orWhere('username', 'like', "%{$cari}%");
            }))
            ->orderBy('role')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('master.pengguna.index', compact('pengguna', 'cari'));
    }

    public function create(): View
    {
        Gate::authorize('kelola-master');

        return view('master.pengguna.create', [
            'pengguna' => new User(['role' => User::ROLE_KASIR, 'status' => User::STATUS_AKTIF]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('kelola-master');

        return view('master.pengguna.edit', ['pengguna' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Password kosong = tidak direset.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        $pesan = isset($data['password'])
            ? 'Data pengguna diperbarui dan password berhasil direset.'
            : 'Data pengguna berhasil diperbarui.';

        return redirect()->route('pengguna.index')->with('success', $pesan);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('kelola-master');

        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        return $this->hapusAtauTolak($user, 'pengguna.index', 'Pengguna berhasil dihapus.');
    }
}
