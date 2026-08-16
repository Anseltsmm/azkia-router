<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()->is_admin && $request->getHost() === 'admin.azkia.cloud') {
            return redirect()->intended(route('admin.index'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'balance' => 0,
            'status' => 'active',
        ]);

        // Kuota gratis harian otomatis untuk setiap user baru.
        Plan::grantFreePlan($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Mulai login Google: redirect ke halaman consent Google.
     * Hanya untuk user site; admin panel tetap pakai email/password.
     */
    public function redirectToGoogle()
    {
        abort_unless(config('services.google.client_id'), 404, 'Google login tidak dikonfigurasi.');

        return Socialite::driver('google')->redirect();
    }

    /**
     * Callback Google: login user yang sudah ada (email sama) atau buat akun baru.
     * User baru otomatis dapat plan gratis harian (sama seperti register biasa).
     */
    public function handleGoogleCallback(Request $request)
    {
        abort_unless(config('services.google.client_id'), 404, 'Google login tidak dikonfigurasi.');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google gagal. Silakan coba lagi.']);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            return redirect()->route('login')->withErrors(['email' => 'Akun Google Anda tidak memiliki alamat email.']);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google User',
                'email' => $email,
                // Tidak ada password — user login via Google. Bisa di-set lewat pengaturan bila perlu.
                'password' => null,
                'balance' => 0,
                'status' => 'active',
            ]);
            Plan::grantFreePlan($user);
        } elseif ($user->status !== 'active') {
            return redirect()->route('login')->withErrors(['email' => 'Akun Anda sedang dinonaktifkan.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($request->getHost() === 'admin.azkia.cloud' ? 'admin.login' : 'login');
    }
}
