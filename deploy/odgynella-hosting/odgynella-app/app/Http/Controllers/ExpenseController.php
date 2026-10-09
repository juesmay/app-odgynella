<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Sede;
use App\Support\CurrentSede;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gastos. Los registra cualquiera del equipo. La asistente solo ve los que ella registró
 * (y nunca la nómina); la doctora ve todos y es la única que anula.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = self::month($request->input('mes'));

        $query = Expense::with(['sede', 'author'])
            ->whereDate('spent_on', '>=', $month->copy()->startOfMonth()->toDateString())->whereDate('spent_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->latest('spent_on')->latest('id');

        if (! $user->isDoctor()) {
            $query->where('created_by', $user->id)->whereNotIn('category', Expense::PRIVATE_CATEGORIES);
        } else {
            $query->when($request->filled('categoria'), fn ($q) => $q->where('category', $request->input('categoria')))
                ->when($request->filled('sede'), fn ($q) => $q->where('sede_id', $request->integer('sede')));
        }

        $expenses = $query->get();
        $valid = $expenses->whereNull('voided_at')->where('historical', false);

        return view('expenses.index', [
            'expenses' => $expenses,
            'month' => $month,
            'total' => (int) $valid->sum('amount'),
            'byCategory' => $valid->groupBy('category')->map->sum('amount')->sortDesc(),
            'sedes' => Sede::orderBy('id')->get(),
            'currentSede' => CurrentSede::get(),
            'categories' => $user->isDoctor() ? Expense::CATEGORIES : array_values(array_diff(Expense::CATEGORIES, Expense::PRIVATE_CATEGORIES)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['amount' => Time::parseMoney($request->input('amount'))]);

        if (! $user->isDoctor()) {
            // La asistente registra gastos de la sede donde está.
            $request->merge(['sede_id' => CurrentSede::get()?->id]);
        }

        $categories = $user->isDoctor() ? Expense::CATEGORIES : array_diff(Expense::CATEGORIES, Expense::PRIVATE_CATEGORIES);

        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in($categories)],
            'amount' => ['required', 'integer', 'min:1', 'max:500000000'],
            'method' => ['required', Rule::in(Expense::METHODS)],
            'sede_id' => ['nullable', Rule::exists('sedes', 'id')->where('clinic_id', $user->clinic_id)],
            'detail' => ['required', 'string', 'max:200'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'support' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'amount.min' => 'Escribe el valor del gasto.',
            'spent_on.before_or_equal' => 'La fecha no puede ser futura.',
            'support.mimes' => 'El soporte debe ser una foto o un PDF.',
            'support.max' => 'El soporte puede pesar máximo 10 MB.',
        ], ['spent_on' => 'fecha', 'category' => 'categoría', 'amount' => 'valor', 'method' => 'medio de pago', 'detail' => 'detalle', 'supplier' => 'proveedor']);

        $data['cash_session_id'] = null;
        if ($data['method'] === 'Efectivo de la caja') {
            $session = $data['sede_id'] ? CashSession::openFor((int) $data['sede_id']) : null;
            if (! $session) {
                return back()->withInput()->withErrors(['method' => 'Para pagar con el efectivo de la caja, la caja de esa sede debe estar abierta.']);
            }
            if (! Carbon::parse($data['spent_on'])->isToday()) {
                return back()->withInput()->withErrors(['spent_on' => 'Un gasto pagado con la caja de hoy debe tener la fecha de hoy.']);
            }
            $data['cash_session_id'] = $session->id;
        }

        unset($data['support']);
        if ($request->hasFile('support')) {
            $data['support_path'] = $request->file('support')->store('expenses/'.$user->clinic_id, 'local');
        }

        Expense::create([...$data, 'created_by' => $user->id]);

        return redirect()->route('expenses.index', ['mes' => Carbon::parse($data['spent_on'])->format('Y-m')])
            ->with('ok', 'Gasto de '.Time::money($data['amount']).' registrado'.($data['cash_session_id'] ? ' y descontado de la caja.' : '.'));
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:200']], [
            'void_reason.required' => 'Escribe por qué se anula el gasto.',
            'void_reason.min' => 'Explica un poco más por qué se anula.',
        ]);

        if ($expense->isVoided()) {
            return back()->withErrors(['void_reason' => 'Este gasto ya estaba anulado.']);
        }

        $session = $expense->cash_session_id ? CashSession::find($expense->cash_session_id) : null;
        if ($session?->closed_at) {
            return back()->withErrors(['void_reason' => 'Salió de una caja que ya se cerró. Registra la corrección como un movimiento nuevo.']);
        }

        $expense->update(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $data['void_reason']]);

        return back()->with('ok', 'Gasto anulado. Queda en el historial.');
    }

    public function support(Request $request, Expense $expense): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isDoctor() || $expense->created_by === $user->id, 403);
        abort_unless($expense->support_path && Storage::disk('local')->exists($expense->support_path), 404);

        return Storage::disk('local')->response($expense->support_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Mes pedido como AAAA-MM; si no es válido, el actual. */
    public static function month(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return today()->startOfMonth();
    }
}
