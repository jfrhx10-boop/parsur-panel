<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Máximo 5 intentos por email e IP cada 15 minutos.
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $min = ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages(['email' => "Demasiados intentos. Vuelve a probar en {$min} min."]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 900);
            Activity::log('login_fallido', $data['email'], null);
            throw ValidationException::withMessages(['email' => 'Email o contraseña incorrectos.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        Activity::log('login');

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Activity::log('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/entrar');
    }

    /* ---------- Alta del primer administrador (solo existe mientras no hay usuarios) ---------- */

    public function showSetup()
    {
        return view('auth.setup');
    }

    public function setup(Request $request)
    {
        abort_if(User::query()->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create($data + ['role' => 'admin', 'active' => true]);
        Auth::login($user);
        $request->session()->regenerate();
        Activity::log('alta_primer_admin', $user->email);

        return redirect('/ajustes')->with('ok', 'Cuenta creada. Ahora conecta la tienda.');
    }

    /* ---------- Invitaciones: el usuario pone su propia contraseña con un enlace firmado ---------- */

    public function showInvite(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403, 'El enlace ha caducado. Pide uno nuevo.');

        return view('auth.invite', ['user' => $user]);
    }

    public function acceptInvite(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403, 'El enlace ha caducado. Pide uno nuevo.');

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password']), 'active' => true])->save();
        Activity::log('contraseña_establecida', $user->email, $user->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('ok', 'Contraseña guardada. Bienvenido al panel.');
    }
}
