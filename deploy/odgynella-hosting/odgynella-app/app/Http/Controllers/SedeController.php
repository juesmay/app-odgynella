<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Support\CurrentSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $sede = Sede::where('active', true)->findOrFail($request->integer('sede_id'));
        CurrentSede::set($sede->id);

        return back();
    }
}
