@extends('layouts.app')
@section('title', 'Consentimientos · '.$patient->name)

@section('content')
@include('patients._header')

<div class="card stack">
    <div class="card-h"><h2>Consentimientos firmados</h2><a class="btn pri" href="{{ route('consents.create', $patient) }}">Firmar consentimiento</a></div>
    @if ($consents->isEmpty())
        <div class="empty">Ninguno todavía.</div>
    @else
        <div class="list">
            @foreach ($consents as $c)
                <a class="rowbtn" href="{{ route('consents.show', [$patient, $c]) }}">
                    <span style="flex:1;min-width:200px"><strong>{{ $c->title }}</strong><br><span class="small muted">Firmado el {{ $c->signed_at->translatedFormat('j \d\e F \d\e Y, g:i a') }}</span></span>
                    <span class="btn sm">Ver</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
