<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Mi cuenta (cada persona) y el equipo (solo la doctora). */
class AccountSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.show', [
            'user' => $request->user(),
            'team' => $request->user()->isDoctor() ? User::where('clinic_id', $request->user()->clinic_id)->orderBy('role')->orderBy('name')->get() : collect(),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
        ], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        $request->session()->regenerate();

        return redirect()->route('today')->with('ok', 'Contraseña cambiada.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $clinicId = $request->user()->clinic_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([User::DOCTORA, User::ASISTENTE])],
        ], ['email.unique' => 'Ya hay un usuario con ese correo.'], ['name' => 'nombre', 'email' => 'correo', 'role' => 'rol']);

        $temp = self::tempPassword();
        User::create([...$data, 'clinic_id' => $clinicId, 'password' => $temp, 'active' => true, 'must_change_password' => true]);

        return back()->with('ok', 'Usuario creado.')->with('temp_password', [$data['email'], $temp]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->clinic_id === $request->user()->clinic_id, 404);

        $temp = self::tempPassword();
        $user->update(['password' => $temp, 'must_change_password' => true]);

        return back()->with('ok', 'Contraseña temporal creada para '.$user->name.'.')->with('temp_password', [$user->email, $temp]);
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->clinic_id === $request->user()->clinic_id, 404);
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propio usuario.']);
        }

        $user->update(['active' => ! $user->active]);

        return back()->with('ok', $user->name.($user->active ? ' puede volver a entrar.' : ' ya no puede entrar.'));
    }

    /** Fácil de dictar por teléfono: sin caracteres que se confundan. */
    public static function tempPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $s = '';
        for ($i = 0; $i < 10; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($s, 0, 5).'-'.substr($s, 5);
    }
}
