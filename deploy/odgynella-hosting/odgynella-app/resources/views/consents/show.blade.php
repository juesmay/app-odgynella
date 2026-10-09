@extends('layouts.app')
@section('title', $consent->title)

@section('content')
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.consents', $patient) }}">← {{ $patient->name }}</a>
        <h1 style="margin-top:6px">{{ $consent->title }}</h1>
        <p class="sub">Firmado el {{ $consent->signed_at->translatedFormat('j \d\e F \d\e Y, g:i a') }}</p>
    </div>
    <a class="btn pri" href="{{ route('consents.pdf', [$patient, $consent]) }}">Descargar PDF</a>
</div>

<div class="card stack">
    <div class="consent-text">{{ $consent->body }}</div>
    <div class="grid2" style="margin-top:12px">
        <div class="stack-sm">
            <span class="sigimg"><img src="{{ $consent->patient_signature }}" alt="Firma de la paciente"></span>
            <span class="small"><strong>{{ $patient->name }}</strong><br>{{ $patient->documentLabel() }}</span>
        </div>
        <div class="stack-sm">
            <span class="sigimg"><img src="{{ $consent->doctor_signature }}" alt="Firma de la doctora"></span>
            <span class="small"><strong>{{ $consent->doctor_name }}</strong></span>
        </div>
    </div>
    <p class="small muted">Este documento no se puede modificar. Si algo cambia, se firma uno nuevo.</p>
</div>
@endsection
