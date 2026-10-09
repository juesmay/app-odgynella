<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\Service;
use App\Support\QuotePdf;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('estado', 'vigentes');

        $quotes = Quote::with('patient')
            ->when($filter === 'vigentes', fn ($q) => $q->where('status', 'vigente')->whereDate('valid_until', '>=', today()))
            ->when($filter === 'vencidas', fn ($q) => $q->where('status', 'vigente')->whereDate('valid_until', '<', today()))
            ->when($filter === 'aceptadas', fn ($q) => $q->where('status', 'aceptada'))
            ->when($filter === 'no_aceptadas', fn ($q) => $q->where('status', 'no_aceptada'))
            ->orderBy($filter === 'vigentes' ? 'valid_until' : 'number', $filter === 'vigentes' ? 'asc' : 'desc')
            ->paginate(30)
            ->withQueryString();

        return view('quotes.index', compact('quotes', 'filter'));
    }

    public function create(Request $request): View
    {
        $patient = $request->integer('paciente') ? Patient::findOrFail($request->integer('paciente')) : null;

        return view('quotes.form', [
            'quote' => new Quote(['discount_amount' => 0]),
            'items' => [],
            'patient' => $patient,
            'services' => $this->serviceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clinicId = $request->user()->clinic_id;
        [$items, $totals] = $this->parseItems($request);

        if ($request->filled('patient_id')) {
            $request->validate(['patient_id' => [Rule::exists('patients', 'id')->where('clinic_id', $clinicId)]]);
            $patient = Patient::findOrFail($request->integer('patient_id'));
        } else {
            $patient = $this->findOrCreateProspect($request);
        }

        $quote = DB::transaction(function () use ($request, $clinicId, $patient, $items, $totals) {
            // Bloquea la clínica para que dos cotizaciones nunca salgan con el mismo número.
            $clinic = Clinic::whereKey($clinicId)->lockForUpdate()->first();
            $number = $clinic->next_quote;
            $clinic->increment('next_quote');

            $quote = Quote::create([
                ...$totals,
                ...$this->notes($request),
                'patient_id' => $patient->id,
                'number' => $number,
                'issued_on' => today(),
                'valid_until' => today()->addDays($clinic->quote_validity_days ?: 30),
                'status' => 'vigente',
                'created_by' => $request->user()->id,
            ]);
            $quote->items()->createMany($items);

            return $quote;
        });

        return redirect()->route('quotes.show', $quote)->with('ok', 'Cotización '.$quote->code().' guardada. Ya puedes descargar el PDF.');
    }

    public function show(Quote $quote): View
    {
        $quote->load(['items', 'patient', 'author']);
        $clinic = auth()->user()->clinic;

        $whatsapp = 'Hola '.$quote->patient->firstName().', te comparto la cotización de tu tratamiento con la '
            .$clinic->doctor_name.': '.$quote->code().' por '.Time::money($quote->total)
            .', válida hasta el '.$quote->valid_until->translatedFormat('j \d\e F').'. Te envío el PDF por aquí. '
            .'Cuando quieras, agendamos tu valoración presencial.';

        return view('quotes.show', compact('quote', 'clinic', 'whatsapp'));
    }

    public function edit(Quote $quote): View|RedirectResponse
    {
        if (! $quote->isEditable()) {
            return redirect()->route('quotes.show', $quote)->with('warn', 'Esta cotización ya está '.mb_strtolower($quote->statusLabel()).' y no se puede editar. Crea una nueva.');
        }

        $quote->load('items', 'patient');

        return view('quotes.form', [
            'quote' => $quote,
            'items' => $quote->items->map->only(['service_id', 'description', 'teeth', 'quantity', 'unit_price', 'price_options'])->all(),
            'patient' => $quote->patient,
            'services' => $this->serviceOptions(),
        ]);
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        abort_unless($quote->isEditable(), 422, 'Esta cotización ya no se puede editar.');
        [$items, $totals] = $this->parseItems($request);

        DB::transaction(function () use ($quote, $items, $totals, $request) {
            $quote->update([...$totals, ...$this->notes($request)]);
            $quote->items()->delete();
            $quote->items()->createMany($items);
        });

        return redirect()->route('quotes.show', $quote)->with('ok', 'Cotización '.$quote->code().' actualizada.');
    }

    public function pdf(Quote $quote): Response
    {
        $quote->load(['items', 'patient']);
        $clinic = auth()->user()->clinic->load('sedes');

        if (! QuotePdf::available()) {
            return redirect()->route('quotes.show', $quote)
                ->with('warn', 'Falta instalar el generador de PDF. En la terminal del proyecto corre: composer require dompdf/dompdf');
        }

        $html = view('quotes.pdf', compact('quote', 'clinic') + QuotePdf::brand($clinic))->render();
        $filename = $quote->code().' '.$quote->patient->name.'.pdf';

        return response(QuotePdf::render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $filename).'"',
        ]);
    }

    public function accept(Quote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'vigente', 422);

        $quote->update(['status' => 'aceptada']);

        if (in_array($quote->patient->stage, ['prospecto', 'agendado', 'no_continuo'], true)) {
            $quote->patient->moveTo('en_tratamiento');
        }

        return back()->with('ok', $quote->patient->firstName().' aceptó la cotización. Queda en tratamiento.');
    }

    // ---------------------------------------------------------------

    /** @return array{0: list<array<string, mixed>>, 1: array<string, mixed>} */
    private function parseItems(Request $request): array
    {
        $clinicId = $request->user()->clinic_id;

        // Ignora filas vacías y limpia los valores en pesos ("1.250.000" => 1250000).
        $rows = collect($request->input('items', []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['description'] ?? '')) !== '')
            ->map(fn ($r) => [
                'service_id' => ($r['service_id'] ?? null) ?: null,
                'description' => trim((string) $r['description']),
                'teeth' => trim((string) ($r['teeth'] ?? '')) ?: null,
                'quantity' => max(1, (int) ($r['quantity'] ?? 1)),
                'unit_price' => Time::parseMoney($r['unit_price'] ?? 0),
                'price_options' => trim((string) ($r['price_options'] ?? '')) ?: null,
            ])
            ->values();

        $request->merge([
            'items' => $rows->all(),
            'discount_amount' => Time::parseMoney($request->input('discount_amount')),
        ]);

        $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.service_id' => ['nullable', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.teeth' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['integer', 'min:0', 'max:500000000'],
            'items.*.price_options' => ['nullable', 'string', 'max:300'],
            'discount_amount' => ['integer', 'min:0'],
            'discount_label' => ['nullable', 'string', 'max:120'],
            'patient_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'items.required' => 'Agrega al menos un tratamiento a la cotización.',
            'items.min' => 'Agrega al menos un tratamiento a la cotización.',
        ]);

        $items = $rows->map(fn ($r, $i) => $r + [
            'line_total' => $r['quantity'] * $r['unit_price'],
            'position' => $i,
        ])->all();

        $subtotal = array_sum(array_column($items, 'line_total'));
        $discount = (int) $request->input('discount_amount');

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_amount' => 'El descuento no puede ser mayor que el subtotal ('.Time::money($subtotal).').']);
        }

        return [$items, [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'discount_label' => $discount > 0 ? ($request->input('discount_label') ?: 'Descuento') : null,
            'total' => $subtotal - $discount,
        ]];
    }

    /** @return array<string, ?string> */
    private function notes(Request $request): array
    {
        return [
            'patient_notes' => $request->input('patient_notes') ?: null,
            'internal_notes' => $request->input('internal_notes') ?: null,
        ];
    }

    /**
     * Durante la videollamada basta con nombre y WhatsApp. Si ese WhatsApp ya existe,
     * la cotización se le suma a esa persona en lugar de crear un duplicado.
     */
    private function findOrCreateProspect(Request $request): Patient
    {
        $request->merge(['phone_key' => Patient::phoneKey($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_key' => ['required', 'min:7'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in(Patient::SOURCES)],
        ], [
            'name.required' => 'Escribe el nombre de la persona.',
            'phone.required' => 'Escribe el WhatsApp de la persona.',
            'phone_key.min' => 'El WhatsApp parece incompleto.',
        ]);

        $existing = Patient::where('phone_key', $data['phone_key'])->first();
        if ($existing) {
            if ($existing->stage === 'no_continuo') {
                $existing->moveTo('prospecto');
            }

            return $existing;
        }

        return Patient::create([
            ...$data,
            'name' => trim($data['name']),
            'stage' => 'prospecto',
            'stage_changed_at' => now(),
            'created_by' => $request->user()->id,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function serviceOptions()
    {
        return Service::where('active', true)->orderBy('is_package', 'desc')->orderBy('name')
            ->get(['id', 'name', 'price', 'is_package'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'price' => $s->price, 'package' => $s->is_package]);
    }
}
