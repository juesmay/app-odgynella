<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Evolution;
use App\Models\Patient;
use App\Models\QuoteItem;
use App\Support\Clinical;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EvolutionController extends Controller
{
    public function index(Patient $patient): View
    {
        return view('patients.evolutions', [
            'patient' => $patient,
            'tab' => 'evoluciones',
            'evolutions' => $patient->evolutions()->get(),
            'pending' => $this->pendingItems($patient),
            'todayAppointment' => $this->todayAppointment($patient),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Clinical::EVOLUTION_TYPES))],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string', 'max:3000'],
            'dx' => ['nullable', 'array', 'max:10'],
            'dx.*' => ['string', 'max:220'],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['integer'],
        ]);

        // Solo se guardan los campos del tipo elegido; los demás quedan en el borrador.
        $keys = Clinical::EVOLUTION_TYPES[$data['type']]['fields'];
        $fields = [];
        foreach ($keys as $key) {
            $value = trim((string) ($data['fields'][$key] ?? ''));
            if ($value !== '') {
                $fields[$key] = $value;
            }
        }

        if (! $fields) {
            throw ValidationException::withMessages(['fields' => 'Escribe al menos un campo antes de firmar la nota.']);
        }

        $items = $this->pendingItems($patient)->whereIn('id', $data['procedures'] ?? []);
        $user = $request->user();

        $evolution = DB::transaction(function () use ($patient, $data, $fields, $items, $user) {
            $appointment = $this->todayAppointment($patient);

            $evolution = Evolution::create([
                'patient_id' => $patient->id,
                'appointment_id' => $appointment?->id,
                'type' => $data['type'],
                'fields' => $fields,
                'procedures' => $items->map(fn ($i) => $i->description.($i->teeth ? ' ('.$i->teeth.')' : ''))->values()->all() ?: null,
                'signed_by' => $user->id,
                'signed_name' => $user->isDoctor() ? $user->clinic->doctor_name : $user->name,
                'signed_at' => now(),
            ]);

            foreach ($items as $item) {
                $item->update(['done_at' => now(), 'done_by' => $user->id, 'evolution_id' => $evolution->id]);
            }

            // Diagnósticos elegidos del buscador CIE-10 en la valoración: "K02.1|Caries de la dentina".
            foreach ($data['dx'] ?? [] as $dx) {
                [$code, $name] = array_pad(explode('|', $dx, 2), 2, null);
                $patient->diagnoses()->create([
                    'code' => $name !== null ? ($code ?: null) : null,
                    'name' => $name ?? $code,
                    'created_by' => $user->id,
                ]);
            }

            if ($appointment) {
                $appointment->update(['status' => 'atendida']);
            }

            return $evolution;
        });

        $msg = 'Nota firmada.';
        if ($items->count()) {
            $msg .= ' '.$items->count().' procedimiento'.($items->count() > 1 ? 's' : '').' marcado'.($items->count() > 1 ? 's' : '').' como realizado'.($items->count() > 1 ? 's' : '').'.';
        }
        if ($evolution->appointment_id) {
            $msg .= ' La cita de hoy queda como atendida.';
        }

        return redirect()->route('patients.evolutions', $patient)->with(['ok' => $msg, 'clear_draft' => true]);
    }

    /** Procedimientos de cotizaciones aceptadas que todavía no se han hecho. */
    private function pendingItems(Patient $patient)
    {
        return QuoteItem::query()
            ->whereNull('done_at')
            ->whereHas('quote', fn ($q) => $q->where('patient_id', $patient->id)->where('status', 'aceptada'))
            ->with('quote')
            ->orderBy('quote_id')->orderBy('position')
            ->get();
    }

    private function todayAppointment(Patient $patient): ?Appointment
    {
        return $patient->appointments()
            ->whereDate('date', today())
            ->whereIn('status', Appointment::OPEN_STATUSES)
            ->orderByRaw("case when status = 'en_sala' then 0 else 1 end")
            ->orderBy('start_time')
            ->first();
    }
}
