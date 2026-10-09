<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Payment;
use App\Support\CurrentSede;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashController extends Controller
{
    public function index(): View
    {
        $sede = CurrentSede::get();
        $open = $sede ? CashSession::openFor($sede->id) : null;

        $todayPayments = Payment::with('patient')
            ->whereDate('paid_on', today())
            ->where('sede_id', $sede?->id)
            ->latest('id')
            ->get();

        return view('cash.index', [
            'sede' => $sede,
            'open' => $open,
            'payments' => $todayPayments,
            'byMethod' => $todayPayments->whereNull('voided_at')->groupBy('method')->map->sum('amount'),
            'history' => CashSession::with('sede')->whereNotNull('closed_at')->latest('closed_at')->limit(10)->get(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $sede = CurrentSede::get();
        abort_unless($sede, 422);

        $request->merge(['opening_amount' => Time::parseMoney($request->input('opening_amount'))]);
        $data = $request->validate(['opening_amount' => ['required', 'integer', 'min:0']], [], ['opening_amount' => 'base']);

        if (CashSession::openFor($sede->id)) {
            return back()->withErrors(['opening_amount' => 'La caja de '.$sede->name.' ya está abierta.']);
        }

        CashSession::create([
            'sede_id' => $sede->id,
            'opened_at' => now(),
            'opened_by' => $request->user()->id,
            'opening_amount' => $data['opening_amount'],
        ]);

        $to = $request->input('then') === 'pago' ? route('payments.create', ['paciente' => $request->input('paciente')]) : route('cash.index');

        return redirect($to)->with('ok', 'Caja de '.$sede->name.' abierta con '.Time::money($data['opening_amount']).'.');
    }

    public function close(Request $request): RedirectResponse
    {
        $sede = CurrentSede::get();
        $session = $sede ? CashSession::openFor($sede->id) : null;

        if (! $session) {
            return back()->withErrors(['counted_cash' => 'La caja ya está cerrada.']);
        }

        $request->merge(['counted_cash' => Time::parseMoney($request->input('counted_cash'))]);
        $data = $request->validate(['counted_cash' => ['required', 'integer', 'min:0']], [], ['counted_cash' => 'efectivo contado']);

        $expected = $session->expectedNow();

        $session->update([
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
            'expected_cash' => $expected,
            'counted_cash' => $data['counted_cash'],
        ]);

        $diff = $data['counted_cash'] - $expected;
        $msg = $diff === 0 ? 'Caja cerrada. Cuadró exacto.' : 'Caja cerrada. '.($diff > 0 ? 'Sobraron ' : 'Faltaron ').Time::money(abs($diff)).'.';

        return redirect()->route('cash.index')->with($diff === 0 ? 'ok' : 'warn', $msg);
    }
}
