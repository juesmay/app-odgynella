@extends('layouts.app')
@section('title', 'Agenda')
@use('App\Support\Time')
@use('App\Support\Scheduler')

@section('content')
@php
    $byStart = $appointments->keyBy('start_time');
    $skipUntil = 0;
@endphp
<div class="head">
    <div>
        <h1>Agenda</h1>
        <p class="sub">{{ $sede?->name }} · {{ ucfirst($date->translatedFormat('l j \d\e F')) }}</p>
    </div>
    <div class="row">
        <a class="btn sm" href="{{ route('agenda', ['fecha' => $date->subDay()->toDateString()]) }}" aria-label="Día anterior">‹</a>
        <a class="btn sm" href="{{ route('agenda') }}">Hoy</a>
        <a class="btn sm" href="{{ route('agenda', ['fecha' => $date->addDay()->toDateString()]) }}" aria-label="Día siguiente">›</a>
        <div class="seg" role="group" aria-label="Vista">
            <a class="btn sm" aria-pressed="true" href="{{ route('agenda', ['fecha' => $date->toDateString()]) }}" style="border:0">Día</a>
            <a class="btn sm ghost" href="{{ route('agenda', ['fecha' => $date->toDateString(), 'vista' => 'semana']) }}" style="border:0">Semana</a>
        </div>
        <a class="btn pri" href="{{ route('appointments.create', ['fecha' => $date->toDateString()]) }}">Nueva cita</a>
    </div>
</div>

@if ($doctorSedeId && $sede && $doctorSedeId !== $sede->id)
    @php $other = \App\Models\Sede::find($doctorSedeId); @endphp
    <div class="infobar">
        <span>Ese día la doctora atiende en {{ $other?->name }}.</span>
        <span class="sp"></span>
        <form method="POST" action="{{ route('sede.switch') }}">@csrf<input type="hidden" name="sede_id" value="{{ $doctorSedeId }}"><button class="btn sm">Ver {{ $other?->name }}</button></form>
    </div>
@endif

<div class="card">
    <div class="slots">
        @for ($m = Time::toMinutes(Scheduler::DAY_START); $m < Time::toMinutes(Scheduler::DAY_END); $m += Scheduler::STEP)
            @continue($m < $skipUntil)
            @php
                $slot = Time::fromMinutes($m);
                $a = $appointments->first(fn ($x) => Time::toMinutes($x->start_time) >= $m && Time::toMinutes($x->start_time) < $m + Scheduler::STEP);
            @endphp
            <div class="slot">
                <div class="h">{{ Time::human($slot) }}</div>
                @if ($a)
                    @php $skipUntil = Time::toMinutes($a->end_time); @endphp
                    <div class="ev" style="min-height: {{ max(44, $a->durationMinutes() / 30 * 46) }}px">
                        <strong>{{ Time::human($a->start_time) }} · {{ $a->patient->name }}</strong>
                        <span class="muted small">{{ $a->service->name }} · {{ $a->durationMinutes() }} min</span>
                        <span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
                        <span style="flex:1"></span>
                        <a class="btn sm" href="{{ route('patients.show', $a->patient) }}">Ver ficha</a>
                    </div>
                @else
                    <a class="free" href="{{ route('appointments.create', ['fecha' => $date->toDateString(), 'hora' => $slot]) }}">Libre · agendar</a>
                @endif
            </div>
        @endfor
    </div>
</div>
@endsection
