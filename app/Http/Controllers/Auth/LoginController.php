<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Maksimal percobaan login gagal per menit (per username + IP). */
    private const MAKS_PERCOBAAN = 5;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'username' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $kredensial = [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'status' => User::STATUS_AKTIF,
        ];

        if (! Auth::attempt($kredensial, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => $this->pesanGagal($request),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['terakhir_login' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil keluar.');
    }

    /**
     * Bedakan akun nonaktif (password benar tetapi status nonaktif) dari kredensial salah.
     */
    private function pesanGagal(LoginRequest $request): string
    {
        $user = User::where('username', $request->input('username'))->first();

        if ($user && ! $user->isAktif() && Hash::check($request->input('password'), $user->password)) {
            return __('auth.inactive');
        }

        return __('auth.failed');
    }
}
