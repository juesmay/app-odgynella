<?php

namespace App\Support;

use App\Models\Sede;

/**
 * La sede que se está viendo. Por defecto, la sede donde atiende la doctora hoy;
 * el usuario la puede cambiar en la barra superior y queda guardada en su sesión.
 */
class CurrentSede
{
    public static function get(): ?Sede
    {
        $id = session('sede_id');

        if ($id && ($sede = Sede::where('active', true)->find($id))) {
            return $sede;
        }

        $todayId = auth()->user()?->clinic?->sedeIdForWeekday((int) now()->dayOfWeek);
        $sede = ($todayId ? Sede::where('active', true)->find($todayId) : null)
            ?? Sede::where('active', true)->orderBy('id')->first();

        if ($sede) {
            session(['sede_id' => $sede->id]);
        }

        return $sede;
    }

    public static function set(int $id): void
    {
        session(['sede_id' => $id]);
    }
}
