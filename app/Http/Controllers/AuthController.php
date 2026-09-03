<?php

namespace App\Http\Controllers;

use App\Enums\SeguridadAccion;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\SeguridadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly SeguridadService $seguridad) {}

    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $key = mb_strtolower($request->input('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['email' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."])->onlyInput('email');
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Las credenciales no son correctas.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->seguridad->registrar(SeguridadAccion::Login, 'Inicio de sesión', Auth::id());

        return redirect()->intended(route('app.situacion'));
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'nombre' => $request->string('nombre')->toString(),
            'email' => mb_strtolower($request->string('email')->toString()),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $this->seguridad->registrar(SeguridadAccion::Registro, 'Registro de usuario', $user->id);

        return redirect()->route('app.situacion');
    }

    public function logout(\Illuminate\Http\Request $request): RedirectResponse
    {
        $userId = Auth::id();
        if ($userId) {
            $this->seguridad->registrar(SeguridadAccion::Logout, 'Cierre de sesión', $userId);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
