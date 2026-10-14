<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
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
 * Gastos por tipo (consultorio, negocio, personal) y categoría. Los registra cualquiera del equipo.
 * La asistente solo ve los que ella registró, nunca los personales ni la nómina; la doctora ve todos y es la única que anula.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = self::month($request->input('mes'));

        $query = Expense::with(['sede', 'author', 'patient'])
            ->whereDate('spent_on', '>=', $month->copy()->startOfMonth()->toDateString())->whereDate('spent_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->latest('spent_on')->latest('id');

        if (! $user->isDoctor()) {
            $query->where('created_by', $user->id)->visibleToAssistant();
        } else {
            $query->when(array_key_exists((string) $request->input('tipo'), Expense::TYPES), fn ($q) => $q->where('type', $request->input('tipo')))
                ->when($request->filled('categoria'), fn ($q) => $q->where('category', $request->input('categoria')))
                ->when($request->filled('sede'), fn ($q) => $q->where('sede_id', $request->integer('sede')));
        }

        $expenses = $query->get();
        $valid = $expenses->whereNull('voided_at')->where('historical', false);

        return view('expenses.index', [
            'expenses' => $expenses,
            'month' => $month,
            'total' => (int) $valid->sum('amount'),
            'byCategory' => $valid->groupBy('category')->map->sum('amount')->sortDesc(),
            'byType' => collect(Expense::TYPES)->map(fn ($l, $t) => (int) $valid->where('type', $t)->sum('amount')),
            'sedes' => Sede::orderBy('id')->get(),
            'currentSede' => CurrentSede::get(),
            'categories' => self::categoriesFor($user),
        ]);
    }

    /**
     * Categorías activas que puede usar la persona, agrupadas por tipo.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ExpenseCategory>>
     */
    public static function categoriesFor($user)
    {
        return ExpenseCategory::where('active', true)->orderBy('name')->get()
            ->reject(fn ($c) => ! $user->isDoctor() && $c->hiddenFromAssistant())
            ->sortBy(fn ($c) => array_search($c->type, array_keys(Expense::TYPES), true))
            ->groupBy('type')->toBase();
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['amount' => Time::parseMoney($request->input('amount'))]);

        if (! $user->isDoctor()) {
            // La asistente registra gastos de la sede donde está.
            $request->merge(['sede_id' => CurrentSede::get()?->id]);
        }

        // Categoría por id (formulario) o por nombre.
        $category = $request->filled('category_id')
            ? ExpenseCategory::where('active', true)->find($request->integer('category_id'))
            : ExpenseCategory::where('active', true)->where('name', (string) $request->input('category'))
                ->orderByRaw("case type when 'consultorio' then 0 when 'negocio' then 1 else 2 end")->first();
        if (! $category || (! $user->isDoctor() && $category->hiddenFromAssistant())) {
            return back()->withInput()->withErrors([$request->filled('category_id') ? 'category_id' : 'category' => 'Elige una categoría de la lista.']);
        }

        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'patient_id' => ['nullable', Rule::exists('patients', 'id')->where('clinic_id', $user->clinic_id)],
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
        ], ['spent_on' => 'fecha', 'patient_id' => 'paciente', 'amount' => 'valor', 'method' => 'medio de pago', 'detail' => 'detalle', 'supplier' => 'proveedor']);

        $data['category'] = $category->name;
        $data['type'] = $category->type;
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

        $expense = Expense::create([...$data, 'created_by' => $user->id]);

        if ($request->input('volver') === 'paciente' && $expense->patient_id) {
            return redirect()->route('patients.account', $expense->patient_id)->with('ok', 'Costo de '.Time::money($expense->amount).' agregado al paciente.');
        }

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
