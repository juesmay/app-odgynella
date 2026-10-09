@extends('layouts.app')
@section('title', 'Agenda de la semana')
@use('App\Support\Time')

@section('content')
@php $sedeNames = \App\Models\Sede::pluck('name', 'id'); @endphp
<div class="head">
    <div>
        <h1>Agenda de la semana</h1>
        <p class="sub">Del {{ $days->first()->translatedFormat('j \d\e F') }} al {{ $days->last()->translatedFormat('j \d\e F') }} · todas las sedes</p>
    </div>
    <div class="row">
        <a class="btn sm" href="{{ route('agenda', ['vista' => 'semana', 'fecha' => $date->subWeek()->toDateString()]) }}" aria-label="Semana anterior">‹</a>
        <a class="btn sm" href="{{ route('agenda', ['vista' => 'semana']) }}">Esta semana</a>
        <a class="btn sm" href="{{ route('agenda', ['vista' => 'semana', 'fecha' => $date->addWeek()->toDateString()]) }}" aria-label="Semana siguiente">›</a>
        <a class="btn sm" href="{{ route('agenda', ['fecha' => $date->toDateString()]) }}">Ver día</a>
        <a class="btn pri" href="{{ route('appointments.create') }}">Nueva cita</a>
    </div>
</div>

<div class="week">
    @foreach ($days as $day)
        @php
            $list = $appointments->get($day->toDateString(), collect());
            $sid = $clinic->sedeIdForWeekday((int) $day->dayOfWeek);
        @endphp
        <div class="wday {{ $day->isToday() ? 'today' : '' }}">
            <div>
                <strong>{{ ucfirst($day->translatedFormat('l j')) }}</strong>
                <div class="muted small">{{ $sid ? $sedeNames[$sid] ?? '' : 'Sin sede' }}</div>
            </div>
            @forelse ($list as $a)
                <a class="wev" href="{{ route('patients.show', $a->patient) }}" style="text-decoration:none;color:inherit">
                    <strong>{{ Time::human($a->start_time) }}</strong>
                    {{ $a->patient->name }}<br>
                    <span class="muted">{{ $a->service->name }} · {{ $a->statusLabel() }}</span>
                </a>
            @empty
                <span class="muted small">Libre</span>
            @endforelse
            <a class="small" href="{{ route('appointments.create', ['fecha' => $day->toDateString()]) }}">+ Agendar</a>
        </div>
    @endforeach
</div>
@endsection
