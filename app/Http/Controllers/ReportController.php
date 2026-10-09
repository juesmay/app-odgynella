<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sede;
use App\Support\Accounts;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes del mes y exportación para el contador. Solo la doctora. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $month = ExpenseController::month($request->input('mes'));
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $prev = $month->copy()->subMonth();

        $payments = $this->payments($from, $to)->get();
        $expenses = Expense::counted()->whereDate('spent_on', '>=', $from->toDateString())->whereDate('spent_on', '<=', $to->toDateString())->get();

        $income = (int) $payments->sum('amount');
        $spent = (int) $expenses->sum('amount');
        $prevIncome = (int) $this->payments($prev->copy()->startOfMonth(), $prev->copy()->endOfMonth())->sum('amount');
        $prevSpent = (int) Expense::counted()->whereDate('spent_on', '>=', $prev->copy()->startOfMonth()->toDateString())->whereDate('spent_on', '<=', $prev->copy()->endOfMonth()->toDateString())->sum('amount');

        $sedes = Sede::orderBy('id')->pluck('name', 'id');
        $bySede = $sedes->map(fn ($name, $id) => ['name' => $name, 'income' => (int) $payments->where('sede_id', $id)->sum('amount'), 'spent' => (int) $expenses->where('sede_id', $id)->sum('amount')]);
        $generalSpent = (int) $expenses->whereNull('sede_id')->sum('amount');

        // Producción: lo realizado en el mes, valorado con el descuento de su cotización.
        $doneItems = QuoteItem::with('quote')
            ->whereBetween('done_at', [$from, $to->copy()->endOfDay()])
            ->whereHas('quote', fn ($q) => $q->where('clinic_id', $request->user()->clinic_id)->where('historical', false))
            ->get();
        $production = (int) $doneItems->sum(fn ($it) => $it->quote->netValue($it->line_total));

        // Cotizaciones emitidas en el mes y cuántas de ellas se aceptaron.
        $quotes = Quote::where('historical', false)->whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())->get(['id', 'status', 'total']);
        $accepted = $quotes->where('status', 'aceptada');

        // Asistencia: citas del mes que ya pasaron.
        $appts = Appointment::whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', min($to, today())->toDateString())
            ->whereIn('status', ['atendida', 'no_vino'])->pluck('status');
        $noShow = $appts->count() ? round($appts->filter(fn ($s) => $s === 'no_vino')->count() * 100 / $appts->count()) : null;

        $newPeople = Patient::where('historical', false)->whereBetween('created_at', [$from, $to->copy()->endOfDay()])->get(['source', 'stage']);

        $receivables = Accounts::receivables();

        return view('reports.index', [
            'month' => $month,
            'income' => $income,
            'spent' => $spent,
            'profit' => $income - $spent,
            'prevIncome' => $prevIncome,
            'prevSpent' => $prevSpent,
            'receivable' => (int) $receivables->sum(fn ($r) => $r['account']['owed']),
            'receivableOld' => (int) $receivables->filter(fn ($r) => $r['days'] >= 60)->sum(fn ($r) => $r['account']['owed']),
            'production' => $production,
            'doneCount' => $doneItems->count(),
            'quotes' => ['count' => $quotes->count(), 'accepted' => $accepted->count(), 'amount' => (int) $accepted->sum('total'),
                'rate' => $quotes->count() ? round($accepted->count() * 100 / $quotes->count()) : null],
            'noShow' => $noShow,
            'apptCount' => $appts->count(),
            'newPeople' => $newPeople->count(),
            'newPatients' => $newPeople->whereNotIn('stage', ['prospecto', 'no_continuo'])->count(),
            'bySource' => $newPeople->groupBy(fn ($p) => $p->source ?: 'Sin dato')->map->count()->sortDesc(),
            'bySede' => $bySede,
            'generalSpent' => $generalSpent,
            'byMethod' => $payments->groupBy('method')->map->sum('amount')->sortDesc(),
            'byCategory' => $expenses->groupBy('category')->map->sum('amount')->sortDesc(),
            'closings' => CashSession::with('sede')->whereBetween('closed_at', [$from, $to->copy()->endOfDay()])->latest('closed_at')->get(),
        ]);
    }

    /**
     * Archivo para el contador: ingresos (recibos, incluidos los anulados para que la numeración no tenga huecos)
     * y gastos del periodo. CSV separado por punto y coma, que Excel en español abre directo.
     */
    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ], [], ['desde' => 'fecha inicial', 'hasta' => 'fecha final']);

        $from = Carbon::parse($data['desde'])->toDateString();
        $to = Carbon::parse($data['hasta'])->toDateString();
        $clinic = $request->user()->clinic;

        $payments = Payment::with(['patient', 'sede'])->where('historical', false)->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->orderBy('receipt_number')->get();
        $expenses = Expense::with('sede')->where('historical', false)->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->orderBy('spent_on')->orderBy('id')->get();

        $name = 'movimientos-'.str($clinic->name)->slug().'-'.$from.'-a-'.$to.'.csv';

        return response()->streamDownload(function () use ($payments, $expenses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel reconoce las tildes.
            $row = fn (array $r) => fputcsv($out, $r, ';', '"', '');

            $row(['Fecha', 'Tipo', 'Documento', 'Tercero', 'Identificación', 'Concepto', 'Categoría', 'Sede', 'Medio de pago', 'Ingreso', 'Egreso', 'Estado', 'Observación']);

            foreach ($payments as $p) {
                $row([
                    $p->paid_on->format('d/m/Y'), 'Ingreso', $p->receiptCode(), $p->patient->name,
                    trim(($p->patient->doc_type ?? '').' '.($p->patient->doc_number ?? '')), $p->concept, 'Servicios odontológicos',
                    $p->sede->name ?? '', $p->method, $p->isVoided() ? 0 : $p->amount, 0,
                    $p->isVoided() ? 'Anulado' : 'Válido', $p->isVoided() ? $p->void_reason : '',
                ]);
            }
            foreach ($expenses as $e) {
                $row([
                    $e->spent_on->format('d/m/Y'), 'Gasto', 'G-'.str_pad((string) $e->id, 5, '0', STR_PAD_LEFT), $e->supplier ?? '', '',
                    $e->detail, $e->category, $e->sede->name ?? 'General', $e->method, 0, $e->isVoided() ? 0 : $e->amount,
                    $e->isVoided() ? 'Anulado' : 'Válido', trim(($e->isVoided() ? $e->void_reason.' ' : '').($e->support_path ? 'Con soporte' : 'Sin soporte')),
                ]);
            }

            $row([]);
            $row(['', '', '', '', '', 'TOTALES', '', '', '', (int) $payments->whereNull('voided_at')->sum('amount'), (int) $expenses->whereNull('voided_at')->sum('amount')]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function payments(Carbon $from, Carbon $to)
    {
        return Payment::counted()->whereDate('paid_on', '>=', $from->toDateString())->whereDate('paid_on', '<=', $to->toDateString());
    }
}
