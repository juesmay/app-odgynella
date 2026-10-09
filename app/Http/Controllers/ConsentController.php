<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Support\QuotePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConsentController extends Controller
{
    public function index(Patient $patient): View
    {
        return view('patients.consents', [
            'patient' => $patient,
            'tab' => 'consentimientos',
            'consents' => $patient->consents()->get(),
        ]);
    }

    public function create(Request $request, Patient $patient): View|RedirectResponse
    {
        if (! $patient->isComplete()) {
            return redirect()->route('patients.complete', $patient)
                ->with('warn', 'Para firmar un consentimiento se necesitan el documento y la autorización de datos de '.$patient->firstName().'.');
        }

        $templates = ConsentTemplate::where('active', true)->orderBy('name')->get();
        $selected = $templates->firstWhere('id', $request->integer('plantilla')) ?? $templates->first();
        $doctor = auth()->user()->clinic->doctor_name;

        return view('consents.create', [
            'patient' => $patient,
            'templates' => $templates,
            'selected' => $selected,
            'text' => $selected?->render($patient, $doctor),
            'doctor' => $doctor,
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->isComplete(), 422);

        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'patient_signature' => ['required', 'string'],
            'doctor_signature' => ['required', 'string'],
        ], [
            'patient_signature.required' => 'Falta la firma de la paciente.',
            'doctor_signature.required' => 'Falta la firma de la doctora.',
        ]);

        foreach (['patient_signature', 'doctor_signature'] as $key) {
            if (! self::isSignature($data[$key])) {
                throw ValidationException::withMessages([$key => 'La firma no se pudo leer. Bórrala y firma de nuevo.']);
            }
        }

        $template = ConsentTemplate::findOrFail($data['template_id']);
        $doctor = $request->user()->clinic->doctor_name;

        $consent = $patient->consents()->create([
            'consent_template_id' => $template->id,
            'title' => $template->name,
            'body' => $template->render($patient, $doctor),
            'patient_signature' => $data['patient_signature'],
            'doctor_signature' => $data['doctor_signature'],
            'doctor_name' => $doctor,
            'signed_by' => $request->user()->id,
            'signed_at' => now(),
        ]);

        return redirect()->route('consents.show', [$patient, $consent])->with('ok', 'Consentimiento firmado y guardado en la historia.');
    }

    public function show(Patient $patient, Consent $consent): View
    {
        abort_unless($consent->patient_id === $patient->id, 404);

        return view('consents.show', compact('patient', 'consent'));
    }

    public function pdf(Patient $patient, Consent $consent): Response
    {
        abort_unless($consent->patient_id === $patient->id, 404);

        if (! QuotePdf::available()) {
            return redirect()->route('consents.show', [$patient, $consent])
                ->with('warn', 'Falta instalar el generador de PDF. En la terminal del proyecto corre: composer require dompdf/dompdf');
        }

        $clinic = auth()->user()->clinic->load('sedes');
        $html = view('consents.pdf', compact('patient', 'consent', 'clinic') + QuotePdf::brand($clinic))->render();
        $name = 'Consentimiento '.$consent->title.' '.$patient->name.'.pdf';

        return response(QuotePdf::render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['"', '/'], '', $name).'"',
        ]);
    }

    /** Una firma válida es una imagen PNG pequeña dibujada en pantalla. */
    private static function isSignature(string $value): bool
    {
        if (! str_starts_with($value, 'data:image/png;base64,') || strlen($value) > 600_000) {
            return false;
        }
        $bin = base64_decode(substr($value, 22), true);

        return $bin !== false && str_starts_with($bin, "\x89PNG");
    }
}
