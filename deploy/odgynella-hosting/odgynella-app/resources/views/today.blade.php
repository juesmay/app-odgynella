@extends('layouts.app')
@section('title', 'Hoy')
@use('App\Support\Time')

@section('content')
<div class="head">
    <div>
        <h1>Hoy en {{ $sede?->name ?? 'tu consultorio' }}</h1>
        <p class="sub">{{ $todaySede ? 'La doctora atiende hoy en '.$todaySede->name.'.' : 'Hoy la doctora no tiene sede asignada.' }}</p>
    </div>
    <div class="row">
        <a class="btn" href="{{ route('appointments.create') }}">Nueva cita</a>
        <a class="btn pri" href="{{ route('quotes.create') }}">Nueva cotización</a>
    </div>
</div>

@if ($sede && ! $cashOpen)
    <div class="infobar">
        @include('partials.icon', ['name' => 'caja', 'size' => 20])
        <span>La caja de {{ $sede->name }} está cerrada. Ábrela para recibir pagos hoy.</span>
        <span class="sp"></span>
        <a class="btn sm" href="{{ route('cash.index') }}">Ir a caja</a>
    </div>
@endif

<div class="stats">
    <div class="stat"><div class="label">Citas</div><div class="v">{{ $stats['total'] }}</div><div class="d">{{ $stats['done'] }} atendidas</div></div>
    <div class="stat"><div class="label">En sala</div><div class="v">{{ $stats['room'] }}</div><div class="d">esperando a la doctora</div></div>
    <div class="stat"><div class="label">Por llegar</div><div class="v">{{ $stats['pending'] }}</div><div class="d">{{ $stats['confirmed'] }} confirmadas</div></div>
    <div class="stat"><div class="label">Cobrado hoy</div><div class="v">{{ Time::money($collected) }}</div><div class="d">en esta sede</div></div>
</div>

<div class="grid-side">
    <section class="stack" aria-label="Citas de hoy">
        @forelse ($appointments as $a)
            @php $alerts = $a->patient->alerts(); @endphp
            <article class="appt {{ $a->status === 'en_sala' ? 'room' : '' }}">
                <div class="t">{{ Time::human($a->start_time) }}</div>
                <div class="who">
                    <a href="{{ route('patients.show', $a->patient) }}" style="font-weight:700;font-size:17px;color:var(--ink)">{{ $a->patient->name }}</a>
                    <div class="muted small">{{ $a->service->name }} · {{ $a->durationMinutes() }} min</div>
                </div>
                @if ($alerts)
                    <span class="chip c-alert">@include('partials.icon', ['name' => 'alert', 'size' => 14]) {{ $alerts[0] }}</span>
                @endif
                <span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
                @include('partials.status-actions', ['a' => $a])
            </article>
        @empty
            <div class="card empty">No hay citas hoy en {{ $sede?->name }}. <a href="{{ route('appointments.create') }}">Agendar una</a></div>
        @endforelse
    </section>
    <aside class="stack">
        <div class="card">
            <div class="card-h"><h2>En cotización</h2><a class="small" href="{{ route('quotes.index') }}">Ver todas</a></div>
            <div class="list">
                @forelse ($openQuotes as $q)
                    <a class="rowbtn" href="{{ route('quotes.show', $q) }}">
                        <span style="flex:1;min-width:140px"><strong>{{ $q->patient->name }}</strong><br><span class="small muted">{{ Time::money($q->total) }}</span></span>
                        <span class="chip {{ $q->daysLeft() <= 5 ? 'c-noshow' : 'c-sched' }}">{{ $q->daysLeft() === 0 ? 'vence hoy' : 'vence en '.$q->daysLeft().' d' }}</span>
                    </a>
                @empty
                    <p class="muted">Nadie en cotización. Las asesorías virtuales aparecen aquí al guardar su cotización.</p>
                @endforelse
            </div>
        </div>
        @if ($funnel !== null)
            <div class="card">
                <h2>De la asesoría al tratamiento</h2>
                <p class="small muted" style="margin:4px 0 10px">Personas cotizadas en los últimos 90 días, por cómo llegaron.</p>
                @if (empty($funnel))
                    <p class="muted">Todavía no hay cotizaciones.</p>
                @else
                    <div class="tblwrap"><table class="tbl compact">
                        <thead><tr><th>Origen</th><th class="r">Cotizó</th><th class="r">Agendó</th><th class="r">Aceptó</th></tr></thead>
                        <tbody>
                        @foreach ($funnel as $source => $f)
                            <tr>
                                <td>{{ $source }}</td>
                                <td class="r">{{ $f['quoted'] }}</td>
                                <td class="r">{{ $f['booked'] }} <span class="muted small">({{ round($f['booked'] / $f['quoted'] * 100) }}%)</span></td>
                                <td class="r">{{ $f['accepted'] }} <span class="muted small">({{ round($f['accepted'] / $f['quoted'] * 100) }}%)</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endif
        <div class="card">
            <h2>Mañana</h2>
            <p class="muted">{{ $tomorrow ? $tomorrow.' citas en total.' : 'Sin citas.' }}</p>
            @if ($tomorrow)<p class="small muted" style="margin-top:6px">Los recordatorios los sigue enviando GoHighLevel.</p>@endif
        </div>
        <div class="card">
            <h2>Atajos</h2>
            <div class="stack-sm">
                <a href="{{ route('patients.create') }}">Nuevo paciente</a>
                <a href="{{ route('payments.create') }}">Registrar un pago</a>
                <a href="{{ route('agenda', ['vista' => 'semana']) }}">Ver la semana</a>
                <a href="{{ route('patients.index') }}">Buscar paciente</a>
            </div>
        </div>
    </aside>
</div>
@endsection
