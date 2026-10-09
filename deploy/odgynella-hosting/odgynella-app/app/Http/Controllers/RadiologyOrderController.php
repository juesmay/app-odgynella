<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\RadiologyOrder;
use App\Support\Brand;
use App\Support\QuotePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Órdenes de radiografía. Las crea y firma la doctora; todo el equipo las ve y descarga. */
class RadiologyOrderController extends Controller
{
    public function index(Patient $patient): View
    {
        return view('patients.orders', [
            'patient' => $patient,
            'tab' => 'ordenes',
            'orders' => $patient->radiologyOrders()->get(),
        ]);
    }

    public function create(Patient $patient): View|RedirectResponse
    {
        if (! $patient->isComplete()) {
            return redirect()->route('patients.complete', $patient)
                ->with('warn', 'Para la orden faltan el documento y la autorización de datos del paciente.');
        }

        $clinic = auth()->user()->clinic;

        return view('patients.order-form', [
            'patient' => $patient,
            'tab' => 'ordenes',
            'centers' => $clinic->radiology_centers ?? [],
            'hasSignature' => Brand::signature($clinic) !== null,
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->isComplete(), 422);

        $rows = collect($request->input('studies', []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => [
                'name' => trim((string) $r['name']),
                'zone' => trim((string) ($r['zone'] ?? '')) ?: null,
                'qty' => max(1, (int) ($r['qty'] ?? 1)),
            ])->values();
        $request->merge(['studies' => $rows->all()]);

        $data = $request->validate([
            'studies' => ['required', 'array', 'min:1', 'max:15'],
            'studies.*.name' => ['required', 'string', 'max:120'],
            'studies.*.zone' => ['nullable', 'string', 'max:80'],
            'studies.*.qty' => ['integer', 'min:1', 'max:32'],
            'center' => ['nullable', 'string', 'max:150'],
            'indication' => ['nullable', 'string', 'max:500'],
            'delivery' => ['required', Rule::in(array_keys(RadiologyOrder::DELIVERY))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'studies.required' => 'Marca al menos un estudio.',
            'studies.min' => 'Marca al menos un estudio.',
        ], ['center' => 'centro radiológico', 'indication' => 'indicación', 'delivery' => 'entrega']);

        if ($data['delivery'] === 'virtual_paciente' && ! $patient->email) {
            return back()->withInput()->withErrors(['delivery' => 'El paciente no tiene correo. Agrégalo en su ficha o elige otra forma de entrega.']);
        }

        $order = DB::transaction(function () use ($data, $patient, $request) {
            $clinic = Clinic::whereKey($request->user()->clinic_id)->lockForUpdate()->first();
            $number = $clinic->next_order;
            $clinic->increment('next_order');

            return $patient->radiologyOrders()->create([
                ...$data,
                'clinic_id' => $clinic->id,
                'number' => $number,
                'issued_on' => today(),
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('patients.orders', $patient)
            ->with('ok', 'Orden '.$order->code().' lista. Descarga el PDF para enviarla.')
            ->with('download', route('orders.pdf', [$patient, $order]));
    }

    public function pdf(Patient $patient, RadiologyOrder $order): Response|RedirectResponse
    {
        abort_unless($order->patient_id === $patient->id, 404);

        if (! QuotePdf::available()) {
            return redirect()->route('patients.orders', $patient)
                ->with('warn', 'Falta instalar el generador de PDF. En la terminal del proyecto corre: composer require dompdf/dompdf');
        }

        $clinic = auth()->user()->clinic->load('sedes');
        $html = view('orders.pdf', ['order' => $order, 'patient' => $patient, 'clinic' => $clinic] + QuotePdf::brand($clinic))->render();

        return response(QuotePdf::render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['"', '/'], '', 'Orden '.$order->code().' '.$patient->name).'.pdf"',
        ]);
    }
}
