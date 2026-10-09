<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $stage = array_key_exists($request->query('etapa', ''), Patient::STAGES) ? $request->query('etapa') : null;

        $patients = Patient::query()
            ->when($stage, fn ($query) => $query->where('stage', $stage))
            ->when($q !== '', function ($query) use ($q) {
                $digits = Patient::phoneKey($q);
                $query->where(function ($w) use ($q, $digits) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('doc_number', 'like', "%{$q}%");
                    if ($digits !== '') {
                        $w->orWhere('phone_key', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $counts = Patient::selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        return view('patients.index', compact('patients', 'q', 'stage', 'counts'));
    }

    public function create(): View
    {
        return view('patients.create', ['patient' => new Patient(['city' => 'Medellín'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null, requireDocument: true);

        if (! $request->boolean('data_consent')) {
            return back()->withInput()->withErrors(['data_consent' => 'Falta la autorización de tratamiento de datos de la paciente.']);
        }

        $patient = Patient::create([
            ...$data,
            'stage' => 'prospecto',
            'stage_changed_at' => now(),
            'data_consent_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('patients.show', $patient)->with('ok', 'Paciente creada. Llena sus antecedentes médicos.');
    }

    public function show(Patient $patient): View
    {
        $patient->load([
            'appointments' => fn ($q) => $q->with(['service', 'sede'])->orderByDesc('date')->orderByDesc('start_time'),
            'quotes' => fn ($q) => $q->orderByDesc('number'),
        ]);

        $payments = Payment::with('sede')->where('patient_id', $patient->id)->latest('paid_on')->latest('id')->get();

        return view('patients.show', [
            'patient' => $patient,
            'payments' => $payments,
            'paidTotal' => $payments->whereNull('voided_at')->sum('amount'),
            'next' => $patient->nextAppointment(),
        ]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update([
            ...$this->validated($request, $patient, requireDocument: $patient->isComplete()),
            'photo_consent' => $request->boolean('photo_consent'),
        ]);

        return back()->with('ok', 'Datos actualizados.');
    }

    /** Pide lo que le falta a un prospecto para atenderse en persona. */
    public function complete(Patient $patient): View
    {
        return view('patients.complete', compact('patient'));
    }

    public function completeStore(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(Patient::DOC_TYPES)],
            'doc_number' => ['required', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'data_consent' => ['accepted'],
        ], [
            'data_consent.accepted' => 'Falta la autorización de tratamiento de datos de la paciente.',
        ], ['doc_type' => 'tipo de documento', 'doc_number' => 'número de documento', 'birth_date' => 'fecha de nacimiento']);

        $patient->update([
            'doc_type' => $data['doc_type'],
            'doc_number' => $data['doc_number'],
            'birth_date' => $data['birth_date'] ?? $patient->birth_date,
            'data_consent_at' => $patient->data_consent_at ?? now(),
        ]);

        return redirect()->route('appointments.create', ['paciente' => $patient->id])
            ->with('ok', 'Datos completos. Ahora elige el día de la valoración presencial.');
    }

    public function lost(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(Patient::LOST_REASONS)],
            'detail' => ['nullable', 'string', 'max:200'],
        ], ['reason.required' => 'Elige por qué no continuó.']);

        $reason = $data['reason'].(filled($data['detail'] ?? null) ? ': '.$data['detail'] : '');
        $patient->moveTo('no_continuo', $reason);
        $patient->quotes()->where('status', 'vigente')->update(['status' => 'no_aceptada']);

        return back()->with('ok', $patient->firstName().' quedó como “no continuó”. Se puede reactivar cuando vuelva.');
    }

    public function reactivate(Patient $patient): RedirectResponse
    {
        $patient->moveTo('prospecto');

        return back()->with('ok', $patient->firstName().' vuelve a estar en cotización.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Patient $patient = null, bool $requireDocument = true): array
    {
        $clinicId = $request->user()->clinic_id;
        $request->merge(['phone_key' => Patient::phoneKey($request->input('phone'))]);
        $doc = $requireDocument ? 'required' : 'nullable';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'doc_type' => [$doc, Rule::in(Patient::DOC_TYPES)],
            'doc_number' => [$doc, 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_key' => [
                'required', 'min:7',
                Rule::unique('patients', 'phone_key')->where('clinic_id', $clinicId)->ignore($patient?->id),
            ],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'source' => ['nullable', Rule::in(Patient::SOURCES)],
        ], [
            'phone_key.unique' => 'Ya hay una persona con ese WhatsApp. Búscala en Pacientes.',
            'phone_key.min' => 'El WhatsApp parece incompleto.',
        ], [
            'name' => 'nombre', 'doc_type' => 'tipo de documento', 'doc_number' => 'número de documento',
            'phone' => 'WhatsApp', 'phone_key' => 'WhatsApp', 'birth_date' => 'fecha de nacimiento', 'city' => 'ciudad', 'email' => 'correo',
        ]);

        $data['name'] = trim($data['name']);
        if (! $requireDocument && blank($data['doc_number'] ?? null)) {
            $data['doc_type'] = null;
            $data['doc_number'] = null;
        }

        return $data;
    }
}
