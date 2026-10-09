<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\Accounts;
use Illuminate\View\View;

/** Cuenta del paciente (pestaña de la ficha) y cartera de la clínica. */
class AccountController extends Controller
{
    public function show(Patient $patient): View
    {
        $plans = $patient->plans()->with(['items', 'payments'])->get();
        $payments = $patient->payments()->with(['quote', 'sede'])->latest('paid_on')->latest('id')->get();

        $totals = ['total' => 0, 'done' => 0, 'paid' => 0, 'balance' => 0, 'owed' => 0, 'advance' => 0];
        foreach ($plans as $q) {
            foreach ($q->account() as $k => $v) {
                if (isset($totals[$k])) {
                    $totals[$k] += $v;
                }
            }
        }

        return view('patients.account', [
            'patient' => $patient,
            'tab' => 'cuenta',
            'plans' => $plans,
            'payments' => $payments,
            'totals' => $totals,
            'otherPaid' => (int) $payments->whereNull('voided_at')->whereNull('quote_id')->sum('amount'),
        ]);
    }

    public function receivables(): View
    {
        $rows = Accounts::receivables();
        $clinic = auth()->user()->clinic;

        $rows = $rows->map(function ($r) use ($clinic) {
            $r['message'] = Accounts::reminder($r['patient'], $r['quote'], $r['account']['owed'], $clinic);
            $r['link'] = Accounts::whatsappLink($r['patient'], $r['message']);

            return $r;
        });

        $buckets = [];
        foreach (Accounts::BUCKETS as $key => $b) {
            $in = $rows->where('bucket', $key);
            $buckets[$key] = ['count' => $in->count(), 'amount' => (int) $in->sum(fn ($r) => $r['account']['owed'])];
        }

        return view('receivables.index', [
            'rows' => $rows,
            'buckets' => $buckets,
            'total' => (int) $rows->sum(fn ($r) => $r['account']['owed']),
        ]);
    }
}
