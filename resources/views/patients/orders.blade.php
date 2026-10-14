@extends('layouts.app')
@section('title', 'Órdenes · '.$patient->name)

@section('content')
@include('patients._header')

@if (session('download'))
    <div class="infobar">
        <span>La orden está lista para enviar al paciente por WhatsApp{{ $orders->first()?->isFree() ? '' : ' o al centro radiológico' }}.</span>
        <span class="sp"></span>
        <a class="btn sm pri" href="{{ session('download') }}">Descargar PDF</a>
    </div>
@endif

<div class="card stack">
    <div class="card-h">
        <h2>Órdenes</h2>
        @if (auth()->user()->isDoctor())
            <div class="row">
                <a class="btn" href="{{ route('orders.create', [$patient, 'tipo' => 'libre']) }}">Orden en blanco</a>
                <a class="btn pri" href="{{ route('orders.create', $patient) }}">Orden de radiografía</a>
            </div>
        @endif
    </div>
    @if ($orders->isEmpty())
        <div class="empty">Ninguna todavía.{{ auth()->user()->isDoctor() ? '' : ' Solo la doctora puede crear órdenes.' }}</div>
    @else
        <div class="list">
            @foreach ($orders as $o)
                <div class="li">
                    <span class="grow">
                        <strong>{{ $o->summary() }}</strong><br>
                        <span class="small muted">{{ $o->isFree() ? 'Orden en blanco' : 'Radiografía' }} · {{ $o->code() }} · {{ $o->issued_on->translatedFormat('j \d\e F \d\e Y') }}{{ $o->center ? ' · '.$o->center : '' }}</span>
                    </span>
                    <a class="btn sm" href="{{ route('orders.pdf', [$patient, $o]) }}">Descargar PDF</a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
