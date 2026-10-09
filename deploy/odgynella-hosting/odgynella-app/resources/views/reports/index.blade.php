@extends('layouts.app')
@section('title', 'Reportes')
@use('App\Support\Time')

@section('content')
@php
    $prevM = $month->copy()->subMonth(); $nextM = $month->copy()->addMonth();
    $delta = function (int $now, int $before) {
        if ($before === 0) return null;
        $pct = round(($now - $before) * 100 / $before);
        return ($pct >= 0 ? '+' : '').$pct.'% vs mes anterior';
    };
    $bar = fn ($v, $max) => $max > 0 && $v > 0 ? max(1, $v * 100 / $max) : 0;
@endphp
<div class="head">
    <div>
        <h1>Reportes</h1>
        <p class="sub">Cómo le fue al consultorio en {{ $month->translatedFormat('F \d\e Y') }}{{ $month->isSameMonth(today()) ? ' (va hasta hoy)' : '' }}.</p>
    </div>
    <nav class="row" aria-label="Mes">
        <a class="btn sm" href="{{ route('reports.index', ['mes' => $prevM->format('Y-m')]) }}">←</a>
        <strong style="min-width:130px;text-align:center">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
        @if ($nextM->lte(today()))
            <a class="btn sm" href="{{ route('reports.index', ['mes' => $nextM->format('Y-m')]) }}">→</a>
        @else
            <span class="btn sm" aria-disabled="true" style="opacity:.4">→</span>
        @endif
    </nav>
</div>

<div class="stats">
    <div class="stat"><div class="label">Entró</div><div class="v">{{ Time::money($income) }}</div><div class="d">{{ $delta($income, $prevIncome) ?? 'Pagos recibidos' }}</div></div>
    <div class="stat"><div class="label">Salió</div><div class="v">{{ Time::money($spent) }}</div><div class="d">{{ $delta($spent, $prevSpent) ?? 'Gastos registrados' }}</div></div>
    <div class="stat {{ $profit < 0 ? 'neg' : '' }}"><div class="label">Quedó</div><div class="v">{{ $profit < 0 ? '-' : '' }}{{ Time::money(abs($profit)) }}</div><div class="d">{{ $profit < 0 ? 'Salió más de lo que entró' : ($income ? round($profit * 100 / $income).'% de lo que entró' : 'Entró menos salió') }}</div></div>
    <a class="stat" href="{{ route('receivables.index') }}" style="text-decoration:none;color:inherit"><div class="label">Me deben</div><div class="v">{{ Time::money($receivable) }}</div><div class="d">{{ $receivableOld ? Time::money($receivableOld).' con más de 60 días' : 'Cartera de hoy' }}</div></a>
</div>

<div class="stats" style="margin-top:12px">
    <div class="stat"><div class="label">Producción</div><div class="v">{{ Time::money($production) }}</div><div class="d">{{ $doneCount === 1 ? '1 procedimiento realizado' : $doneCount.' procedimientos realizados' }}</div></div>
    <div class="stat"><div class="label">Cotizaciones aceptadas</div><div class="v">{{ $quotes['rate'] !== null ? $quotes['rate'].'%' : '—' }}</div><div class="d">{{ $quotes['accepted'] }} de {{ $quotes['count'] }} · {{ Time::money($quotes['amount']) }}</div></div>
    <div class="stat"><div class="label">Personas nuevas</div><div class="v">{{ $newPeople }}</div><div class="d">{{ $newPatients }} ya agendaron o están en tratamiento</div></div>
    <div class="stat {{ ($noShow ?? 0) >= 15 ? 'neg' : '' }}"><div class="label">No vinieron</div><div class="v">{{ $noShow !== null ? $noShow.'%' : '—' }}</div><div class="d">de {{ $apptCount === 1 ? '1 cita que ya pasó' : $apptCount.' citas que ya pasaron' }}</div></div>
</div>
<p class="small muted" style="margin-top:8px">Producción es lo que se hizo en el mes; “Entró” es lo que se cobró. Pueden ser distintos: un abono de hoy puede ser de un tratamiento del mes pasado.</p>

<div class="grid2" style="margin-top:16px">
    <div class="card">
        <h2>Por sede</h2>
        @php $maxS = max(1, $bySede->max('income'), $bySede->max('spent')); @endphp
        <div class="tblwrap"><table class="tbl compact" style="margin-top:8px">
            <thead><tr><th>Sede</th><th class="r">Entró</th><th class="r">Salió</th><th class="r">Quedó</th></tr></thead>
            <tbody>
            @foreach ($bySede as $s)
                <tr><td>{{ $s['name'] }}</td><td class="r">{{ Time::money($s['income']) }}</td><td class="r">{{ Time::money($s['spent']) }}</td>
                    <td class="r" style="{{ $s['income'] - $s['spent'] < 0 ? 'color:var(--danger)' : '' }}">{{ $s['income'] - $s['spent'] < 0 ? '-' : '' }}{{ Time::money(abs($s['income'] - $s['spent'])) }}</td></tr>
            @endforeach
            @if ($generalSpent)
                <tr><td class="muted">Gastos generales</td><td class="r">—</td><td class="r">{{ Time::money($generalSpent) }}</td><td class="r" style="color:var(--danger)">-{{ Time::money($generalSpent) }}</td></tr>
            @endif
            </tbody>
        </table></div>
        <div class="bars" style="margin-top:14px">
            @foreach ($bySede as $s)
                <div class="bar"><span>{{ $s['name'] }}</span><div class="track"><div class="fill" style="width:{{ $bar($s['income'], $maxS) }}%"></div></div><span class="val">{{ Time::money($s['income']) }}</span></div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <h2>Gastos por categoría</h2>
        @if ($byCategory->isEmpty())
            <div class="empty">Sin gastos en el mes.</div>
        @else
            @php $maxC = $byCategory->max(); @endphp
            <div class="bars" style="margin-top:12px">
                @foreach ($byCategory as $cat => $amt)
                    <div class="bar"><span>{{ $cat }}</span><div class="track"><div class="fill warn" style="width:{{ $bar($amt, $maxC) }}%"></div></div><span class="val">{{ Time::money($amt) }}</span></div>
                @endforeach
            </div>
        @endif
        <a class="small" href="{{ route('expenses.index', ['mes' => $month->format('Y-m')]) }}" style="display:inline-block;margin-top:12px">Ver el detalle de gastos →</a>
    </div>

    <div class="card">
        <h2>Cómo pagaron</h2>
        @if ($byMethod->isEmpty())
            <div class="empty">Sin pagos en el mes.</div>
        @else
            @php $maxM = $byMethod->max(); @endphp
            <div class="bars" style="margin-top:12px">
                @foreach ($byMethod as $m => $amt)
                    <div class="bar"><span>{{ $m }}</span><div class="track"><div class="fill ok" style="width:{{ $bar($amt, $maxM) }}%"></div></div><span class="val">{{ Time::money($amt) }}</span></div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h2>De dónde llegaron las personas nuevas</h2>
        @if ($bySource->isEmpty())
            <div class="empty">Nadie nuevo en el mes.</div>
        @else
            @php $maxO = $bySource->max(); @endphp
            <div class="bars" style="margin-top:12px">
                @foreach ($bySource as $src => $n)
                    <div class="bar"><span>{{ $src }}</span><div class="track"><div class="fill info" style="width:{{ $bar($n, $maxO) }}%"></div></div><span class="val">{{ $n }}</span></div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Cierres de caja</h2>
        <div class="list" style="margin-top:6px">
            @forelse ($closings as $h)
                @php $d = $h->difference(); @endphp
                <div class="li">
                    <span class="grow">{{ $h->closed_at->translatedFormat('j M') }} · {{ $h->sede->name }}</span>
                    <span class="chip {{ $d === 0 ? 'c-done' : 'c-cancel' }}">{{ $d === 0 ? 'Cuadró' : ($d > 0 ? 'Sobró ' : 'Faltó ').Time::money(abs($d)) }}</span>
                </div>
            @empty
                <p class="muted">Sin cierres en el mes.</p>
            @endforelse
        </div>
    </div>

    <form method="GET" action="{{ route('reports.export') }}" class="card stack">
        <h2>Para el contador</h2>
        <p class="small muted">Descarga un archivo de Excel con todos los recibos (también los anulados, para que la numeración quede completa) y todos los gastos del periodo.</p>
        <div class="fgrid">
            <label class="field" for="desde"><span>Desde</span><input id="desde" name="desde" type="date" class="input" value="{{ $month->copy()->startOfMonth()->toDateString() }}" required></label>
            <label class="field" for="hasta"><span>Hasta</span><input id="hasta" name="hasta" type="date" class="input" value="{{ min($month->copy()->endOfMonth(), today())->toDateString() }}" required></label>
        </div>
        <button class="btn pri">Descargar para Excel</button>
    </form>
</div>
@endsection
