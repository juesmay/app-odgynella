<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Sede;
use App\Models\Service;
use App\Support\CurrentSede;
use App\Support\Scheduler;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function create(Request $request): View
    {
        $date = $request->query('fecha', today()->toDateString());
        $clinic = auth()->user()->clinic;
        $sedeId = $clinic->sedeIdForWeekday((int) CarbonImmutable::parse($date)->dayOfWeek) ?? CurrentSede::get()?->id;

        return view('appointments.create', [
            'patients' => Patient::orderBy('name')->get(['id', 'name', 'phone', 'doc_number', 'data_consent_at']),
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'sedes' => Sede::where('active', true)->orderBy('id')->get(),
            'selected' => [
                'patient_id' => $request->integer('paciente') ?: null,
                'date' => $date,
                'time' => $request->query('hora'),
                'sede_id' => $sedeId,
            ],
        ]);
    }

    /** Horas libres para una fecha y un servicio (lo usa el formulario de nueva cita). */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'servicio' => ['required', 'integer'],
        ]);

        $service = Service::findOrFail($data['servicio']);
        $date = CarbonImmutable::parse($data['fecha'])->toDateString();
        $clinic = auth()->user()->clinic;

        return response()->json([
            'slots' => collect(Scheduler::freeSlots($date, $service->duration_min))
                ->map(fn ($t) => ['value' => $t, 'label' => Time::human($t)])->values(),
            'doctor_sede_id' => $clinic->sedeIdForWeekday((int) CarbonImmutable::parse($date)->dayOfWeek),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clinicId = $request->user()->clinic_id;

        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'service_id' => ['required', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'sede_id' => ['required', Rule::exists('sedes', 'id')->where('clinic_id', $clinicId)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'start_time.required' => 'Elige una hora. Si no aparece ninguna, ese día está lleno.',
        ], [
            'patient_id' => 'paciente', 'service_id' => 'servicio', 'sede_id' => 'sede', 'date' => 'fecha',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        if (! $patient->isComplete()) {
            return redirect()->route('patients.complete', $patient)
                ->with('warn', 'Antes de agendar en persona, completa el documento y la autorización de datos de '.$patient->firstName().'.');
        }

        $service = Service::findOrFail($data['service_id']);
        $date = CarbonImmutable::parse($data['date'])->toDateString();
        $end = Time::toMinutes($data['start_time']) + $service->duration_min;

        if ($end > Time::toMinutes(Scheduler::DAY_END)) {
            return back()->withInput()->withErrors(['start_time' => 'La cita terminaría después de las 7:00 p.m.']);
        }

        $appointment = DB::transaction(function () use ($data, $date, $end, $service, $request) {
            if (! Scheduler::isFree($date, $data['start_time'], $service->duration_min)) {
                return null;
            }

            return Appointment::create([
                ...$data,
                'date' => $date,
                'end_time' => Time::fromMinutes($end),
                'status' => 'agendada',
                'created_by' => $request->user()->id,
            ]);
        });

        if (! $appointment) {
            return back()->withInput()->withErrors(['start_time' => 'Esa hora se cruza con otra cita. Elige otra.']);
        }

        if (in_array($patient->stage, ['prospecto', 'no_continuo'], true)) {
            $patient->moveTo('agendado');
        }

        CurrentSede::set($appointment->sede_id);

        return redirect()->route('agenda', ['fecha' => $date])
            ->with('ok', 'Cita agendada: '.$appointment->patient->firstName().', '.$appointment->date->translatedFormat('j \d\e F').' a las '.Time::human($appointment->start_time).'.');
    }

    public function status(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Appointment::STATUSES))],
        ]);

        if (! $appointment->canMoveTo($data['status'])) {
            return back()->withErrors(['status' => 'Esa cita ya está '.mb_strtolower($appointment->statusLabel()).' y no se puede cambiar a '.mb_strtolower(Appointment::STATUSES[$data['status']][0]).'.']);
        }

        $appointment->update(['status' => $data['status']]);

        return back()->with('ok', $appointment->patient->firstName().': '.mb_strtolower($appointment->statusLabel()).'.');
    }
}
