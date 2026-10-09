<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Saca a quien fue desactivado y obliga a cambiar una contraseña temporal antes de usar el sistema. */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->active === false) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Tu usuario está desactivado. Habla con la doctora.']);
        }

        if ($user?->must_change_password && ! $request->routeIs('account', 'account.password', 'logout')) {
            return redirect()->route('account')->with('warn', 'Antes de seguir, cambia la contraseña temporal por una tuya.');
        }

        return $next($request);
    }
}
