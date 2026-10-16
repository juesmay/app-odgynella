<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Support\Brand;
use App\Support\ConsentForms;
use App\Support\CurrentSede;
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

        // Primero los consentimientos con campos (los de la doctora), luego los de texto libre.
        $templates = ConsentTemplate::where('active', true)->orderByRaw('form is null')->orderBy('name')->get();
        $selected = $templates->firstWhere('id', $request->integer('plantilla'));
        $clinic = auth()->user()->clinic;
        $doctor = $clinic->doctor_name;

        return view('consents.create', [
            'patient' => $patient,
            'templates' => $templates,
            'selected' => $selected,
            'form' => $selected?->formDef(),
            'blocks' => $selected ? ConsentForms::blocks($selected->render($patient, $doctor)) : [],
            'defaults' => ConsentForms::defaults($patient, $clinic, CurrentSede::get()?->name),
            'doctor' => $doctor,
            'clinic' => $clinic,
            'savedSignature' => Brand::signature($clinic),
            'minor' => $patient->age() !== null && $patient->age() < 18,
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->isComplete(), 422);

        $clinic = $request->user()->clinic;
        $saved = Brand::signature($clinic);
        // La doctora firma con su firma guardada en Configuración, salvo que elija firmar a mano.
        if ($saved && ! $request->boolean('doctor_draws')) {
            $request->merge(['doctor_signature' => $saved]);
        }

        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'patient_signature' => ['required', 'string'],
            'doctor_signature' => ['required', 'string'],
        ], [
            'patient_signature.required' => 'Falta la firma del paciente.',
            'doctor_signature.required' => 'Falta la firma de la doctora.',
        ]);

        foreach (['patient_signature', 'doctor_signature'] as $key) {
            if (! self::isSignature($data[$key])) {
                throw ValidationException::withMessages([$key => 'La firma no se pudo leer. Bórrala y firma de nuevo.']);
            }
        }

        $template = ConsentTemplate::findOrFail($data['template_id']);
        $doctor = $clinic->doctor_name;
        $formData = $this->formData($request, $template, $patient);

        $consent = $patient->consents()->create([
            'consent_template_id' => $template->id,
            'title' => $template->name,
            'body' => $template->render($patient, $doctor),
            'form_data' => $formData,
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

    /**
     * Valida lo llenado en pantalla y lo guarda tal como sale en el documento:
     * filas de datos (etiqueta, valor), decisión, quién firma, asentimiento del menor y testigo.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request, ConsentTemplate $template, Patient $patient): array
    {
        $form = $template->formDef();
        $minorForm = ! empty($form['minor']);
        $isMinor = $patient->age() !== null && $patient->age() < 18;
        $hasRep = $minorForm || $isMinor || $request->boolean('has_rep');

        // Si no llegan (formularios viejos), los datos de arriba toman sus valores por defecto.
        $defaults = ConsentForms::defaults($patient, $request->user()->clinic, CurrentSede::get()?->name);
        $request->merge(['f' => array_merge(['fecha_lugar' => $defaults['fecha_lugar'], 'hc' => $defaults['hc']], (array) $request->input('f', []))]);

        $rules = [
            'f.hc' => ['nullable', 'string', 'max:60'],
            'f.fecha_lugar' => ['required', 'string', 'max:120'],
            'f.registro' => ['nullable', 'string', 'max:60'],
            'rep.nombre' => [$hasRep ? 'required' : 'nullable', 'string', 'max:150'],
            'rep.documento' => [$hasRep ? 'required' : 'nullable', 'string', 'max:60'],
            'rep.vinculo' => [$hasRep ? 'required' : 'nullable', 'string', 'max:60'],
        ];
        $names = ['f.fecha_lugar' => 'fecha y lugar', 'rep.nombre' => 'nombre del representante', 'rep.documento' => 'documento del representante', 'rep.vinculo' => 'vínculo del representante', 'decision' => 'decisión'];

        foreach ($form['fields'] ?? [] as $f) {
            $req = ! empty($f['required']) ? 'required' : 'nullable';
            $k = 'f.'.$f['key'];
            $names[$k] = mb_strtolower($f['label']);
            $rules[$k] = match ($f['type']) {
                'checks' => [$req, 'array'],
                'radio' => [$req, 'string', \Illuminate\Validation\Rule::in([...$f['options'], ...(! empty($f['other']) ? ['__otro'] : [])])],
                'number' => [$req, 'integer', 'min:1', 'max:40'],
                'textarea' => [$req, 'string', 'max:1000'],
                default => [$req, 'string', 'max:200'],
            };
            if ($f['type'] === 'checks') {
                $rules[$k.'.*'] = [\Illuminate\Validation\Rule::in($f['options'])];
            }
            if (! empty($f['other'])) {
                $rules['otro.'.$f['key']] = [$request->input($k) === '__otro' ? 'required' : 'nullable', 'string', 'max:100'];
                $names['otro.'.$f['key']] = mb_strtolower($f['label']).' (otro)';
            }
        }
        if ($form) {
            $rules['decision'] = ['required', 'in:0,1'];
        }
        if (! empty($form['assent'])) {
            $rules['assent'] = ['nullable', \Illuminate\Validation\Rule::in($form['assent'])];
            $rules['menor'] = ['nullable', 'string', 'max:150'];
        }
        if (! empty($form['witness'])) {
            $rules['testigo.nombre'] = ['nullable', 'string', 'max:150'];
            $rules['testigo.documento'] = ['nullable', 'string', 'max:60'];
        }

        $v = $request->validate($rules, [
            'decision.required' => 'Marca si el paciente acepta o no el tratamiento.',
            'rep.nombre.required' => $minorForm ? 'Este consentimiento lo firma la madre, el padre o el representante: escribe su nombre.' : 'Escribe el nombre del representante.',
        ], $names);

        $clinic = $request->user()->clinic;
        $rows = [
            ['Paciente', $patient->name],
            ['Documento', $patient->documentLabel()],
        ];
        if ($minorForm && $patient->age() !== null) {
            $rows[] = ['Edad', $patient->age().' años'];
        }
        if (filled($v['f']['hc'] ?? null)) {
            $rows[] = ['Historia clínica', $v['f']['hc']];
        }
        $rows[] = ['Fecha y lugar', $v['f']['fecha_lugar']];
        $rows[] = ['Profesional tratante', $clinic->doctor_name];
        $license = $v['f']['registro'] ?? $clinic->doctor_license;
        if (filled($license)) {
            $rows[] = ['Registro profesional', $license];
        }
        if ($hasRep) {
            $rows[] = [$minorForm ? 'Madre/padre o representante' : 'Representante', $v['rep']['nombre'].' · '.$v['rep']['documento'].' · '.$v['rep']['vinculo']];
        }

        foreach ($form['fields'] ?? [] as $f) {
            $value = $v['f'][$f['key']] ?? null;
            if ($f['type'] === 'checks') {
                $value = $value ? implode("\n", array_values(array_intersect($f['options'], $value))) : null;
            } elseif ($value === '__otro') {
                $value = 'Otro: '.trim($v['otro'][$f['key']]);
            }
            if (filled($value)) {
                $rows[] = [$f['label'], (string) $value];
            }
        }

        $accepted = ($v['decision'] ?? '1') === '1';

        return [
            'subtitle' => $form['subtitle'] ?? null,
            'rows' => $rows,
            'decision' => $form ? ['accepted' => $accepted, 'options' => $form['decision']] : null,
            'signer' => $hasRep
                ? ['name' => $v['rep']['nombre'], 'detail' => $v['rep']['documento'].' · '.$v['rep']['vinculo'].' de '.$patient->name]
                : ['name' => $patient->name, 'detail' => $patient->documentLabel()],
            'assent' => ! empty($form['assent']) && filled($v['assent'] ?? null)
                ? ['answer' => $v['assent'], 'name' => $v['menor'] ?: $patient->name] : null,
            'witness' => ! empty($form['witness']) && filled($v['testigo']['nombre'] ?? null)
                ? ['name' => $v['testigo']['nombre'], 'doc' => $v['testigo']['documento'] ?? ''] : null,
            'note' => $form['note'] ?? null,
            'license' => $license,
        ];
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
