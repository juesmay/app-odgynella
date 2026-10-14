<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    /** Foto de perfil: se recorta cuadrada y se guarda pequeña (256 px) en almacenamiento privado. */
    public function avatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('quitar')) {
            if ($user->avatar_path) {
                Storage::disk('local')->delete($user->avatar_path);
            }
            $user->update(['avatar_path' => null]);

            return back()->with('ok', 'Foto quitada.');
        }

        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], [
            'avatar.required' => 'Elige una foto.',
            'avatar.image' => 'El archivo debe ser una foto.',
            'avatar.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'avatar.max' => 'La foto puede pesar máximo 8 MB.',
        ]);

        $file = $request->file('avatar');
        $dir = 'avatars/'.$user->clinic_id;
        $square = self::squareJpeg($file->getRealPath());
        $path = $dir.'/'.$user->id.'-'.Str::random(8).($square ? '.jpg' : '.'.$file->extension());
        Storage::disk('local')->put($path, $square ?? file_get_contents($file->getRealPath()));

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $path]);

        return back()->with('ok', 'Foto de perfil guardada.');
    }

    /** Muestra la foto de alguien del mismo consultorio. */
    public function showAvatar(Request $request, User $user)
    {
        abort_unless($user->clinic_id === $request->user()->clinic_id && $user->avatar_path, 404);
        abort_unless(Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=604800']);
    }

    /** Recorta al centro y reduce a 256x256. Null si el servidor no tiene GD (entonces se guarda la original). */
    private static function squareJpeg(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($src = @imagecreatefromstring((string) file_get_contents($path)))) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = imagecreatetruecolor(256, 256);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
        ob_start();
        imagejpeg($out, null, 86);
        $data = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($out);

        return $data;
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
