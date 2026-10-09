@extends('layouts.app')
@section('title', 'Gastos')
@use('App\Support\Time')
@use('App\Models\Expense')

@section('content')
@php $isDoc = auth()->user()->isDoctor(); $prev = $month->copy()->subMonth(); $next = $month->copy()->addMonth(); @endphp
<div class="head">
    <div>
        <h1>Gastos</h1>
        <p class="sub">{{ $isDoc ? 'Todo lo que salió en el mes, por categoría y sede.' : 'Los gastos que has registrado.' }}</p>
    </div>
    <nav class="row" aria-label="Mes">
        <a class="btn sm" href="{{ route('expenses.index', ['mes' => $prev->format('Y-m')] + request()->only('categoria', 'sede')) }}">←</a>
        <strong style="min-width:130px;text-align:center">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        @if ($next->lte(today()))
            <a class="btn sm" href="{{ route('expenses.index', ['mes' => $next->format('Y-m')] + request()->only('categoria', 'sede')) }}">→</a>
        @else
            <span class="btn sm" aria-disabled="true" style="opacity:.4">→</span>
        @endif
    </nav>
</div>

<div class="grid-side">
    <div class="stack">
        @if ($isDoc)
            <form method="GET" class="card row" style="gap:10px">
                <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
                <select name="categoria" class="input" style="width:auto" onchange="this.form.submit()" aria-label="Categoría">
                    <option value="">Todas las categorías</option>
                    @foreach (Expense::CATEGORIES as $c)<option @selected(request('categoria') === $c)>{{ $c }}</option>@endforeach
                </select>
                <select name="sede" class="input" style="width:auto" onchange="this.form.submit()" aria-label="Sede">
                    <option value="">Todas las sedes</option>
                    @foreach ($sedes as $s)<option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <noscript><button class="btn sm">Filtrar</button></noscript>
                <span class="sp" style="flex:1"></span>
                <strong>Total {{ Time::money($total) }}</strong>
            </form>
        @endif

        <div class="card">
            @if ($expenses->isEmpty())
                <div class="empty">No hay gastos registrados en {{ $month->translatedFormat('F') }}.</div>
            @else
                <div class="tblwrap"><table class="tbl">
                    <thead><tr><th>Fecha</th><th>Categoría</th><th>Detalle</th><th>Sede</th><th>Medio</th><th class="r">Valor</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($expenses as $e)
                        <tr class="{{ $e->isVoided() ? 'voided' : '' }}">
                            <td style="white-space:nowrap">{{ $e->spent_on->translatedFormat('j M') }}</td>
                            <td>{{ $e->category }}</td>
                            <td>@if ($e->historical)<span class="chip c-sched" style="padding:1px 7px;font-size:11px">Histórico</span> @endif{{ $e->detail }}@if ($e->supplier)<br><span class="small muted">{{ $e->supplier }}</span>@endif
                                @if ($e->isVoided())<br><span class="small">Anulado: {{ $e->void_reason }}</span>@endif</td>
                            <td>{{ $e->sede->name ?? 'General' }}</td>
                            <td class="small">{{ $e->method }}</td>
                            <td class="r">{{ Time::money($e->amount) }}</td>
                            <td style="white-space:nowrap">
                                @if ($e->support_path)<a class="small" href="{{ route('expenses.support', $e) }}" target="_blank">Soporte</a>@endif
                                @if ($isDoc && ! $e->isVoided())
                                    <details class="inline-void">
                                        <summary class="small">Anular</summary>
                                        <form method="POST" action="{{ route('expenses.void', $e) }}" class="stack-sm">
                                            @csrf
                                            <input name="void_reason" class="input" required minlength="5" maxlength="200" placeholder="Motivo" aria-label="Motivo de anulación">
                                            <button class="btn sm danger">Anular gasto</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        @if ($isDoc && $byCategory->isNotEmpty())
            <div class="card">
                <h2>Por categoría</h2>
                @php $max = max(1, $byCategory->max()); @endphp
                <div class="bars" style="margin-top:12px">
                    @foreach ($byCategory as $cat => $amt)
                        <div class="bar"><span>{{ $cat }}</span><div class="track"><div class="fill" style="width:{{ $amt * 100 / $max }}%"></div></div><span class="val">{{ Time::money($amt) }}</span></div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="card stack">
        @csrf
        <h2>Registrar gasto</h2>
        <label class="field" for="amount"><span>Valor</span>
            <input id="amount" name="amount" class="input" inputmode="numeric" value="{{ old('amount') }}" required placeholder="Ej: 180000">
        </label>
        <label class="field" for="category"><span>Categoría</span>
            <select id="category" name="category" class="input" required>
                <option value="">Elige</option>
                @foreach ($categories as $c)<option @selected(old('category') === $c)>{{ $c }}</option>@endforeach
            </select>
        </label>
        <label class="field" for="detail"><span>Detalle</span>
            <input id="detail" name="detail" class="input" value="{{ old('detail') }}" required maxlength="200" placeholder="Ej: resinas y anestesia">
        </label>
        <label class="field" for="supplier"><span>Proveedor <span class="muted">(opcional)</span></span>
            <input id="supplier" name="supplier" class="input" value="{{ old('supplier') }}" maxlength="120">
        </label>
        <div class="fgrid">
            <label class="field" for="spent_on"><span>Fecha</span>
                <input id="spent_on" name="spent_on" type="date" class="input" value="{{ old('spent_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            </label>
            @if ($isDoc)
                <label class="field" for="sede_id"><span>Sede</span>
                    <select id="sede_id" name="sede_id" class="input">
                        <option value="">General (todas)</option>
                        @foreach ($sedes as $s)<option value="{{ $s->id }}" @selected(old('sede_id', $currentSede?->id) == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </label>
            @else
                <div class="field"><span>Sede</span><div class="input" style="background:var(--surface-2)">{{ $currentSede?->name }}</div></div>
            @endif
        </div>
        <label class="field" for="method"><span>Cómo se pagó</span>
            <select id="method" name="method" class="input">
                @foreach (Expense::METHODS as $m)<option @selected(old('method', 'Transferencia') === $m)>{{ $m }}</option>@endforeach
            </select>
        </label>
        <p class="small muted">“Efectivo de la caja” se descuenta del cuadre de la caja abierta de esa sede.</p>
        <label class="field" for="support"><span>Foto de la factura <span class="muted">(opcional)</span></span>
            <input id="support" name="support" type="file" class="input" accept="image/*,application/pdf">
        </label>
        <button class="btn pri block">Guardar gasto</button>
    </form>
</div>
@endsection
