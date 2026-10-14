<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\Patient;
use App\Support\Clinical;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pestaña "Historia clínica": antecedentes, examen clínico y diagnósticos. */
class HistoryController extends Controller
{
    public function show(Patient $patient): View
    {
        $patient->load('diagnoses');

        return view('patients.history', ['patient' => $patient, 'tab' => 'historia']);
    }

    /**
     * Guarda toda la historia de una vez: antecedentes y, si es la doctora, el examen clínico.
     * Así nada de lo escrito en una sección se pierde al guardar otra.
     * La asistente puede llenar los antecedentes con la paciente, salvo las enfermedades infecciosas.
     */
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'alert_text' => ['nullable', 'string', 'max:255'],
            'infectious' => ['nullable', 'array'],
            'infectious.*' => [Rule::in(Clinical::INFECTIOUS)],
            'infectious_other' => ['nullable', 'string', 'max:120'],
            'oncology_status' => ['nullable', Rule::in(array_keys(Clinical::ONCOLOGY_STATUS))],
            'oncology_ended_on' => ['nullable', 'date', 'before_or_equal:today'],
            'oncology_treatments' => ['nullable', 'array'],
            'oncology_treatments.*' => [Rule::in(array_keys(Clinical::ONCOLOGY_TREATMENTS))],
            'family_conditions' => ['nullable', 'array'],
            'family_conditions.*' => [Rule::in(Clinical::FAMILY_CONDITIONS)],
            'family_detail' => ['nullable', 'string', 'max:1000'],
            'hereditary_history' => ['nullable', 'string', 'max:1000'],
        ], [
            'oncology_ended_on.before_or_equal' => 'La fecha en que terminó el tratamiento no puede ser futura.',
        ]);

        $answers = [];
        foreach (array_keys(Patient::ANAMNESIS) as $key) {
            $answers[$key] = $request->input("anamnesis.{$key}") === '1';
        }

        $status = $data['oncology_status'] ?? 'no';
        $changes = [
            'anamnesis' => $answers,
            'alert_text' => $data['alert_text'] ?? null,
            'oncology' => $status === 'no' ? ['status' => 'no'] : [
                'status' => $status,
                'ended_on' => $status === 'previo' ? ($data['oncology_ended_on'] ?? null) : null,
                'treatments' => array_values($data['oncology_treatments'] ?? []),
            ],
            'family_history' => [
                'conditions' => array_values($data['family_conditions'] ?? []),
                'detail' => $data['family_detail'] ?? null,
            ],
            'hereditary_history' => $data['hereditary_history'] ?? null,
        ];

        // Solo la doctora ve y cambia las enfermedades infecciosas.
        if ($request->user()->isDoctor()) {
            $infectious = array_values($data['infectious'] ?? []);
            if (filled($data['infectious_other'] ?? null)) {
                $infectious[] = trim($data['infectious_other']);
            }
            $changes['infectious'] = $infectious ?: null;
        }

        if ($request->user()->isDoctor() && $request->has('exam')) {
            $changes['exam'] = $this->examData($request->input('exam', []));
        }

        $patient->update($changes);

        return back()->with('ok', 'Historia clínica guardada.')->with('clear_draft', true);
    }

    public function exam(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update(['exam' => $this->examData($request->all())]);

        return back()->with('ok', 'Examen clínico guardado.');
    }

    /** @return array<string, string|null> */
    private function examData(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $rules = [];
        foreach (array_keys(Clinical::EXAM_FIELDS) as $key) {
            $rules[$key] = ['nullable', 'string', 'max:1000'];
        }
        validator($input, $rules)->validate();

        $out = [];
        foreach (array_keys(Clinical::EXAM_FIELDS) as $key) {
            $out[$key] = filled($input[$key] ?? null) ? trim($input[$key]) : null;
        }

        return $out;
    }

    public function addDiagnosis(Request $request, Patient $patient): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'name' => ['required', 'string', 'max:200'],
            'tooth' => ['nullable', 'string', 'max:20'],
        ], ['name.required' => 'Escribe o elige el diagnóstico.']);

        $d = $patient->diagnoses()->create([...$data, 'created_by' => $request->user()->id]);

        // Desde la historia clínica se agrega sin recargar la página, para no perder lo que se está escribiendo.
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'tooth' => $d->tooth,
                'date' => $d->created_at->translatedFormat('j M Y'),
                'remove' => route('patients.diagnoses.destroy', [$patient, $d]),
            ], 201);
        }

        return back()->with('ok', 'Diagnóstico agregado.');
    }

    public function removeDiagnosis(Request $request, Patient $patient, Diagnosis $diagnosis): RedirectResponse|JsonResponse
    {
        abort_unless($diagnosis->patient_id === $patient->id, 404);
        $diagnosis->delete(); // queda guardado como descartado

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Diagnóstico descartado.');
    }
}
