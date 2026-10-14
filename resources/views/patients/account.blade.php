@extends('layouts.app')
@section('title', 'Cuenta · '.$patient->name)
@use('App\Support\Time')

@section('content')
@include('patients._header')

@if ($plans->isEmpty())
    <div class="card stack" style="margin-top:4px">
        <h2>Sin tratamiento aceptado</h2>
        <p class="muted">Cuando acepte una cotización, aquí verás el total del plan, lo realizado, lo pagado y el saldo, calculados solos.</p>
        <div class="row">
            <a class="btn" href="{{ route('quotes.create', ['paciente' => $patient->id]) }}">Nueva cotización</a>
        </div>
    </div>
@else
    <div class="stats">
        <div class="stat"><div class="label">Total de tratamientos</div><div class="v">{{ Time::money($totals['total']) }}</div><div class="d">{{ $plans->count() === 1 ? '1 plan' : $plans->count().' planes' }}</div></div>
        <div class="stat"><div class="label">Realizado</div><div class="v">{{ Time::money($totals['done']) }}</div><div class="d">{{ $totals['total'] ? round($totals['done'] * 100 / $totals['total']) : 0 }}% del plan</div></div>
        <div class="stat"><div class="label">Pagado</div><div class="v">{{ Time::money($totals['paid']) }}</div><div class="d">Saldo del plan {{ Time::money($totals['balance']) }}</div></div>
        @if ($totals['owed'] > 0)
            <div class="stat neg"><div class="label">Debe de lo ya hecho</div><div class="v">{{ Time::money($totals['owed']) }}</div><div class="d">Está en cartera</div></div>
        @else
            <div class="stat"><div class="label">Anticipo</div><div class="v">{{ Time::money($totals['advance']) }}</div><div class="d">Pagado por adelantado</div></div>
        @endif
    </div>
@endif

<div class="grid-side" style="margin-top:20px">
    <div class="stack">
        @foreach ($plans as $q)
            @php $a = $q->account(); @endphp
            <div class="card">
                <div class="row" style="justify-content:space-between;margin-bottom:10px">
                    <h2><a href="{{ route('quotes.show', $q) }}">Tratamiento {{ $q->code() }}</a></h2>
                    <div class="row">
                        @if ($q->isPaused())
                            <span class="chip c-noshow">En pausa desde {{ $q->paused_at->translatedFormat('j M') }}</span>
                        @elseif ($q->isClosed())
                            <span class="chip c-sched">Cerrado {{ $q->closed_at->translatedFormat('j M Y') }}</span>
                        @endif
                        @if ($a['balance'] === 0)
                            <span class="chip c-done">Pagado completo</span>
                        @elseif ($a['owed'] > 0)
                            <span class="chip c-cancel">Debe {{ Time::money($a['owed']) }}</span>
                        @elseif ($a['advance'] > 0)
                            <span class="chip c-conf">Anticipo {{ Time::money($a['advance']) }}</span>
                        @else
                            <span class="chip c-done">Al día</span>
                        @endif
                        @unless ($q->isPaused() || ($q->isClosed() && $a['balance'] === 0))
                            <a class="btn sm pri" href="{{ route('payments.create', ['paciente' => $patient->id, 'cotizacion' => $q->id]) }}">Registrar abono</a>
                        @endunless
                    </div>
                </div>
                <div class="planbars" role="img" aria-label="Realizado {{ Time::money($a['done']) }} y pagado {{ Time::money($a['paid']) }} de {{ Time::money($a['total']) }}">
                    <div class="pr-row"><span>Realizado</span><div class="track"><div class="fill" style="width:{{ $a['total'] ? min(100, $a['done'] * 100 / $a['total']) : 0 }}%"></div></div><b>{{ Time::money($a['done']) }}</b></div>
                    <div class="pr-row"><span>Pagado</span><div class="track"><div class="fill ok" style="width:{{ $a['total'] ? min(100, $a['paid'] * 100 / $a['total']) : 0 }}%"></div></div><b>{{ Time::money($a['paid']) }}</b></div>
                </div>
                <div class="tblwrap" style="margin-top:12px"><table class="tbl compact">
                    <thead><tr><th>Procedimiento</th><th>Dientes</th><th class="r">Valor</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($q->items as $it)
                        <tr>
                            <td>{{ $it->description }}{{ $it->quantity > 1 ? ' × '.$it->quantity : '' }}</td>
                            <td class="mono">{{ $it->teeth ?: '—' }}</td>
                            <td class="r">{{ Time::money($q->netValue($it->line_total)) }}</td>
                            <td>@if ($it->done_at)<span class="chip c-done">Realizado {{ $it->done_at->translatedFormat('j M') }}</span>@else<span class="chip c-sched">Pendiente</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if ($q->isClosed())
                    <p class="small muted" style="margin-top:8px">Se cerró al volver con una cotización nueva: queda valiendo lo realizado ({{ Time::money($q->closed_total) }}). Lo pendiente y lo pagado de más pasaron a la cotización nueva.</p>
                @endif
                @if ($q->discount_amount)
                    <p class="small muted" style="margin-top:8px">Los valores ya tienen repartido el descuento “{{ $q->discount_label }}” ({{ Time::money($q->discount_amount) }}).</p>
                @endif
                <div class="receipt" style="max-width:380px;margin:14px 0 0 auto">
                    <div class="kv"><span>Total del plan</span><b>{{ Time::money($a['total']) }}</b></div>
                    <div class="kv"><span>Pagado</span><b>- {{ Time::money($a['paid']) }}</b></div>
                    <hr class="sep">
                    <div class="kv" style="font-size:18px"><span>Saldo</span><b>{{ Time::money($a['balance']) }}</b></div>
                </div>
            </div>
        @endforeach
    </div>

    <aside class="stack">
    <div class="card">
        <div class="row" style="justify-content:space-between">
            <h2>Pagos</h2>
            <a class="btn sm" href="{{ route('payments.create', ['paciente' => $patient->id]) }}">Registrar pago</a>
        </div>
        <div class="list" style="margin-top:8px">
            @forelse ($payments as $p)
                <a class="li {{ $p->isVoided() ? 'voided' : '' }}" href="{{ route('payments.receipt', $p) }}" style="text-decoration:none;color:inherit">
                    <span class="grow">
                        <strong>{{ Time::money($p->amount) }}</strong> · {{ $p->method }}<br>
                        <span class="small muted">{{ $p->paid_on->translatedFormat('j M Y') }} · {{ $p->receiptCode() }} · {{ $p->concept }}{{ $p->isVoided() ? ' (anulado)' : '' }}{{ $p->historical ? ' · histórico' : '' }}</span>
                    </span>
                </a>
            @empty
                <p class="muted">Todavía no hay pagos.</p>
            @endforelse
        </div>
        @if ($otherPaid > 0)
            <p class="small muted" style="margin-top:10px">{{ Time::money($otherPaid) }} en pagos sueltos (valoraciones, controles…) que no cuentan para el saldo de los tratamientos.</p>
        @endif
    </div>

    @if ($profit)
        <div class="card stack" id="costos">
            <h2>Costo y ganancia</h2>
            <div class="receipt">
                <div class="kv"><span>Ha pagado</span><b>{{ Time::money($profit['paid']) }}</b></div>
                <div class="kv"><span>Tiempo de consultorio</span><b>- {{ Time::money($profit['room']) }}</b></div>
                <div class="kv"><span>Otros costos</span><b>- {{ Time::money($profit['other']) }}</b></div>
                <hr class="sep">
                <div class="kv" style="font-size:18px"><span>Ganancia</span><b style="color:{{ $profit['profit'] < 0 ? 'var(--danger)' : 'var(--ok)' }}">{{ Time::money($profit['profit']) }}</b></div>
                @if ($profit['margin'] !== null)<p class="small muted" style="margin-top:4px">Le queda el {{ $profit['margin'] }}% de lo que ha pagado.</p>@endif
            </div>
            <div class="list">
                @forelse ($costs as $e)
                    <div class="li {{ $e->isVoided() ? 'voided' : '' }}">
                        <span class="grow"><strong>{{ Time::money($e->amount) }}</strong> · {{ $e->category }}{{ $e->hours ? ' ('.rtrim(rtrim(number_format($e->hours, 2, ',', ''), '0'), ',').' h)' : '' }}<br>
                            <span class="small muted">{{ $e->spent_on->translatedFormat('j M Y') }} · {{ $e->detail }}{{ $e->isVoided() ? ' (anulado)' : '' }}</span></span>
                        @unless ($e->isVoided())
                            <details class="inline-void">
                                <summary class="small">Anular</summary>
                                <form method="POST" action="{{ route('expenses.void', $e) }}" class="stack-sm">
                                    @csrf
                                    <input name="void_reason" class="input" required minlength="5" maxlength="200" placeholder="Motivo" aria-label="Motivo de anulación">
                                    <button class="btn sm danger">Anular costo</button>
                                </form>
                            </details>
                        @endunless
                    </div>
                @empty
                    <p class="small muted">Sin costos todavía. El tiempo de consultorio se suma solo al marcar sus citas como atendidas (si la sede tiene tarifa en Configuración).</p>
                @endforelse
            </div>
            <details>
                <summary style="cursor:pointer;font-weight:700">+ Agregar costo</summary>
                <form method="POST" action="{{ route('expenses.store') }}" class="stack-sm" style="margin-top:10px">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <input type="hidden" name="volver" value="paciente">
                    <input type="hidden" name="sede_id" value="{{ $patient->sede_id }}">
                    <label class="field" for="c-cat"><span>Qué fue</span>
                        <select id="c-cat" name="category_id" class="input" required>
                            @foreach ($costCategories as $type => $list)
                                <optgroup label="{{ \App\Models\Expense::TYPES[$type] }}">
                                    @foreach ($list as $c)<option value="{{ $c->id }}" @selected($c->name === 'Laboratorio dental')>{{ $c->name }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                    <label class="field" for="c-amount"><span>Valor</span><input id="c-amount" name="amount" class="input" inputmode="numeric" required placeholder="Ej: 250000"></label>
                    <label class="field" for="c-detail"><span>Detalle</span><input id="c-detail" name="detail" class="input" required maxlength="200" placeholder="Ej: corona en zirconio, laboratorio X"></label>
                    <div class="fgrid">
                        <label class="field" for="c-date"><span>Fecha</span><input id="c-date" name="spent_on" type="date" class="input" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                        <label class="field" for="c-method"><span>Cómo se pagó</span>
                            <select id="c-method" name="method" class="input">
                                @foreach (\App\Models\Expense::METHODS as $m)@if ($m !== 'Efectivo de la caja')<option @selected($m === 'Transferencia')>{{ $m }}</option>@endif @endforeach
                            </select>
                        </label>
                    </div>
                    <button class="btn">Agregar costo</button>
                    <p class="small muted">También queda en Gastos del mes.</p>
                </form>
            </details>
        </div>
    @endif
    </aside>
</div>
@endsection
