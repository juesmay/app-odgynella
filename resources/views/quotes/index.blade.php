@extends('layouts.app')
@section('title', 'Cotizaciones')
@use('App\Support\Time')

@section('content')
<div class="head">
    <div>
        <h1>Cotizaciones</h1>
        <p class="sub">Las vigentes salen ordenadas por la que vence primero, para hacerles seguimiento.</p>
    </div>
    <a class="btn pri" href="{{ route('quotes.create') }}">Nueva cotización</a>
</div>

<div class="tabs" role="tablist">
    @foreach (['vigentes' => 'Vigentes', 'vencidas' => 'Vencidas', 'aceptadas' => 'Aceptadas', 'no_aceptadas' => 'No aceptadas', 'todas' => 'Todas'] as $key => $label)
        <a class="tab" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}" href="{{ route('quotes.index', ['estado' => $key]) }}" style="text-decoration:none">{{ $label }}</a>
    @endforeach
</div>

<div class="card">
    @if ($quotes->isEmpty())
        <div class="empty">No hay cotizaciones aquí. <a href="{{ route('quotes.create') }}">Hacer una</a></div>
    @else
        <div class="tblwrap"><table class="tbl">
            <thead><tr><th>Número</th><th>Persona</th><th>Emitida</th><th>Vence</th><th>Estado</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach ($quotes as $q)
                <tr>
                    <td><a class="mono" href="{{ route('quotes.show', $q) }}">{{ $q->code() }}</a></td>
                    <td><a href="{{ route('patients.show', $q->patient) }}">{{ $q->patient->name }}</a></td>
                    <td>{{ $q->issued_on->translatedFormat('j M') }}</td>
                    <td>
                        {{ $q->valid_until->translatedFormat('j M') }}
                        @if ($q->displayStatus() === 'vigente' && $q->daysLeft() <= 5)
                            <span class="chip c-noshow">{{ $q->daysLeft() === 0 ? 'hoy' : $q->daysLeft().' días' }}</span>
                        @endif
                    </td>
                    <td><span class="chip {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span></td>
                    <td class="r">{{ Time::money($q->total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div style="margin-top:12px">{{ $quotes->links('partials.pager') }}</div>
    @endif
</div>
@endsection
