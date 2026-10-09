<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\CurrentSede;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $sede = CurrentSede::get();
        $view = $request->query('vista') === 'semana' ? 'semana' : 'dia';

        try {
            $date = CarbonImmutable::parse($request->query('fecha', today()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            $date = CarbonImmutable::today();
        }

        $clinic = auth()->user()->clinic;

        if ($view === 'semana') {
            $start = $date->startOfWeek(); // lunes
            $days = collect(range(0, 5))->map(fn ($i) => $start->addDays($i));
            $appointments = Appointment::with(['patient', 'service', 'sede'])
                ->whereBetween('date', [$start->toDateString(), $start->addDays(5)->endOfDay()->toDateTimeString()])
                ->where('status', '!=', 'cancelada')
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn ($a) => $a->date->toDateString());

            return view('agenda.week', compact('sede', 'date', 'days', 'appointments', 'clinic'));
        }

        $appointments = Appointment::with(['patient', 'service'])
            ->whereDate('date', $date)
            ->where('sede_id', $sede?->id)
            ->where('status', '!=', 'cancelada')
            ->orderBy('start_time')
            ->get();

        $doctorSedeId = $clinic->sedeIdForWeekday((int) $date->dayOfWeek);

        return view('agenda.day', compact('sede', 'date', 'appointments', 'doctorSedeId'));
    }
}
