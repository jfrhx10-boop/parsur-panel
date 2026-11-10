<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    /** Crea el usuario sin contraseña usable y devuelve un enlace para que la ponga él mismo. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        $user = User::create($data + ['password' => Hash::make(Str::random(64)), 'active' => false]);
        Activity::log('usuario_creado', $user->email);

        return redirect('/usuarios')->with('invite', [
            'name' => $user->name,
            'url' => $this->inviteUrl($user),
        ]);
    }

    public function invite(User $user)
    {
        Activity::log('enlace_generado', $user->email);

        return redirect('/usuarios')->with('invite', [
            'name' => $user->name,
            'url' => $this->inviteUrl($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $request->boolean('active'))) {
            return back()->withErrors(['role' => 'No puedes quitarte el rol de administrador ni desactivarte a ti mismo.']);
        }

        $user->update(['role' => $data['role'], 'active' => $request->boolean('active')]);
        Activity::log('usuario_editado', $user->email.' → '.$data['role'].($request->boolean('active') ? '' : ' (desactivado)'));

        return back()->with('ok', 'Usuario actualizado.');
    }

    private function inviteUrl(User $user): string
    {
        // El enlace vale una sola vez: lleva una huella de la contraseña actual y deja de servir en cuanto se cambia.
        return URL::temporarySignedRoute('invite.show', now()->addHours(48), ['user' => $user->id, 'h' => AuthController::inviteMark($user)]);
    }

    /* ---------- Mi cuenta ---------- */

    public function account()
    {
        return view('users.account');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $request->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();
        Activity::log('contraseña_cambiada');

        return back()->with('ok', 'Contraseña cambiada.');
    }
}
