<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\CurrentSede;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(Request $request): View
    {
        $sede = CurrentSede::get();

        // Planes con saldo por paciente, para el selector "Aplicar a".
        $plans = Quote::with(['items', 'payments'])->where('status', 'aceptada')->get()
            ->map(fn (Quote $q) => ['patient' => $q->patient_id, 'id' => $q->id, 'code' => $q->code()] + $q->account())
            ->filter(fn ($a) => $a['balance'] > 0)
            ->groupBy('patient');

        return view('payments.create', [
            'sede' => $sede,
            'cashOpen' => $sede ? CashSession::openFor($sede->id) : null,
            'patients' => Patient::orderBy('name')->get(['id', 'name']),
            'selectedPatient' => $request->integer('paciente') ?: null,
            'selectedQuote' => $request->integer('cotizacion') ?: null,
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clinicId = $request->user()->clinic_id;
        $request->merge(['amount' => Time::parseMoney($request->input('amount'))]);

        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'amount' => ['required', 'integer', 'min:1', 'max:200000000'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'quote_id' => ['nullable', 'integer'],
            'concept' => ['required_without:quote_id', 'nullable', 'string', 'max:200'],
        ], [
            'amount.min' => 'Escribe el valor recibido.',
            'concept.required_without' => 'Escribe el concepto del pago.',
        ], ['patient_id' => 'paciente', 'amount' => 'valor', 'method' => 'medio de pago', 'concept' => 'concepto']);

        if (! empty($data['quote_id'])) {
            $quote = Quote::whereKey($data['quote_id'])->where('patient_id', $data['patient_id'])->first();

            if (! $quote || $quote->status !== 'aceptada') {
                return back()->withInput()->withErrors(['quote_id' => 'Ese tratamiento no es de este paciente o no está aceptado.']);
            }
            if ($data['amount'] > $quote->balance()) {
                return back()->withInput()->withErrors(['amount' => 'El saldo de '.$quote->code().' es '.Time::money($quote->balance()).'. Si paga más, registra la diferencia aparte con otro concepto.']);
            }
            $data['concept'] = filled($data['concept'] ?? null) ? $data['concept'] : 'Abono a tratamiento '.$quote->code();
        } else {
            $data['quote_id'] = null;
        }

        $sede = CurrentSede::get();
        $session = $sede ? CashSession::openFor($sede->id) : null;

        if (! $session) {
            return back()->withInput()->withErrors(['amount' => 'La caja de '.($sede?->name ?? 'esta sede').' está cerrada. Ábrela primero.']);
        }

        $payment = DB::transaction(function () use ($data, $sede, $session, $request, $clinicId) {
            // Bloquea la clínica para que dos recibos nunca salgan con el mismo número.
            $clinic = Clinic::whereKey($clinicId)->lockForUpdate()->first();
            $number = $clinic->next_receipt;
            $clinic->increment('next_receipt');

            return Payment::create([
                ...$data,
                'sede_id' => $sede->id,
                'cash_session_id' => $session->id,
                'receipt_number' => $number,
                'paid_on' => today(),
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('payments.receipt', $payment)->with('ok', 'Pago registrado.');
    }

    public function receipt(Payment $payment): View
    {
        $payment->load(['patient', 'sede', 'author', 'quote']);
        $clinic = auth()->user()->clinic;
        $balance = $payment->quote?->balance();

        $first = $payment->patient->firstName();
        $whatsapp = "Hola {$first}, recibimos tu pago de ".Time::money($payment->amount)." ({$payment->method}) por {$payment->concept}. Recibo {$payment->receiptCode()}."
            .($payment->quote && ! $payment->isVoided() ? ' Saldo de tu tratamiento: '.Time::money($balance).'.' : '')
            ." ¡Gracias! Consultorio {$clinic->doctor_name}.";

        return view('payments.receipt', compact('payment', 'clinic', 'whatsapp', 'balance'));
    }

    public function void(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:200']], [
            'void_reason.required' => 'Escribe por qué se anula el pago.',
            'void_reason.min' => 'Explica un poco más por qué se anula.',
        ]);

        if ($payment->isVoided()) {
            return back()->withErrors(['void_reason' => 'Este pago ya estaba anulado.']);
        }
        if ($payment->historical) {
            return back()->withErrors(['void_reason' => 'Los pagos históricos no se anulan desde aquí.']);
        }

        $session = $payment->cash_session_id ? CashSession::find($payment->cash_session_id) : null;
        if ($session?->closed_at) {
            return back()->withErrors(['void_reason' => 'La caja de ese día ya se cerró. Registra la corrección como un movimiento nuevo.']);
        }

        $payment->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['void_reason'],
        ]);

        return back()->with('ok', 'Pago '.$payment->receiptCode().' anulado. Queda en el historial.');
    }
}
