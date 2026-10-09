<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\Clinical;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdontogramController extends Controller
{
    public function show(Request $request, Patient $patient): View
    {
        $teeth = $patient->teeth()->get()->keyBy('tooth');
        // ?denticion= deja mirar otra dentición sin cambiar la guardada.
        $dentition = array_key_exists((string) $request->query('denticion'), Clinical::DENTITIONS)
            ? $request->query('denticion')
            : $patient->dentitionShown();

        return view('patients.odontogram', [
            'patient' => $patient,
            'tab' => 'odontograma',
            'teeth' => $teeth,
            'dentition' => $dentition,
            'chart' => Clinical::chart($dentition),
            'index' => Clinical::cariesIndex($teeth),
        ]);
    }

    /** La doctora fija la dentición del paciente (si no, se elige por la edad). */
    public function dentition(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate(['dentition' => ['required', Rule::in(array_keys(Clinical::DENTITIONS))]]);
        $patient->update(['dentition' => $data['dentition']]);

        return redirect()->route('patients.odontogram', $patient)->with('ok', 'Odontograma en dentición '.mb_strtolower(Clinical::DENTITIONS[$data['dentition']]).'.');
    }

    /** Guarda todos los hallazgos y la observación de un diente. */
    public function update(Request $request, Patient $patient, int $tooth): RedirectResponse
    {
        abort_unless(in_array($tooth, Clinical::allTeeth(), true), 404);

        $data = $request->validate([
            'findings' => ['nullable', 'array'],
            'findings.*' => [Rule::in(array_keys(Clinical::FINDINGS))],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['nullable', 'array'],
            'surfaces.*.*' => [Rule::in(array_keys(Clinical::SURFACES))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $findings = [];
        foreach ($data['findings'] ?? [] as $type) {
            if (Clinical::isPrimary($tooth) && in_array($type, Clinical::ADULT_ONLY, true)) {
                continue;
            }
            $byFace = Clinical::FINDINGS[$type]['faces'];
            $surfaces = $byFace ? array_values(array_unique($data['surfaces'][$type] ?? [])) : [];
            if ($byFace && ! $surfaces) {
                return back()->withErrors(['findings' => 'Marca en qué caras está: '.mb_strtolower(Clinical::FINDINGS[$type]['name']).'.'])->withInput();
            }
            $findings[] = ['type' => $type, 'surfaces' => $surfaces];
        }

        $note = trim((string) ($data['note'] ?? ''));

        if (! $findings && $note === '') {
            $patient->teeth()->where('tooth', $tooth)->delete();
        } else {
            $patient->teeth()->updateOrCreate(['tooth' => $tooth], [
                'findings' => $findings,
                'note' => $note ?: null,
                'updated_by' => $request->user()->id,
            ]);
        }

        $back = ['diente' => $tooth] + ($request->filled('denticion') ? ['denticion' => $request->input('denticion')] : []);

        return redirect()->route('patients.odontogram', [$patient] + $back)->with('ok', 'Diente '.$tooth.' guardado.');
    }
}
