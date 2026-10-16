@extends('layouts.app')
@section('title', $consent->title)

@section('content')
@php
    $fd = $consent->form_data;
    $signer = $fd['signer'] ?? ['name' => $patient->name, 'detail' => $patient->documentLabel()];
@endphp
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.consents', $patient) }}">← {{ $patient->name }}</a>
        <h1 style="margin-top:6px">{{ $consent->title }}</h1>
        <p class="sub">Firmado el {{ $consent->signed_at->translatedFormat('j \d\e F \d\e Y, g:i a') }}</p>
    </div>
    <a class="btn pri" href="{{ route('consents.pdf', [$patient, $consent]) }}">Descargar PDF</a>
</div>

<div class="card stack">
    @if (! empty($fd['decision']))
        <div><span class="chip {{ $fd['decision']['accepted'] ? 'c-done' : 'c-cancel' }}">{{ $fd['decision']['options'][$fd['decision']['accepted'] ? 0 : 1] }}</span></div>
    @endif
    @if (! empty($fd['rows']))
        <div class="tblwrap"><table class="tbl compact">
            <tbody>
            @foreach ($fd['rows'] as [$k, $v])
                <tr><td class="muted" style="width:34%">{{ $k }}</td><td style="white-space:pre-line"><strong>{{ $v }}</strong></td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
    <details @if (! $fd) open @endif>
        <summary style="cursor:pointer;font-weight:700">Texto explicado</summary>
        <div class="consent-doc" style="margin-top:10px">
            @foreach (\App\Support\ConsentForms::blocks($consent->body) as [$type, $content])
                @if ($type === 'h')<h3>{{ $content }}</h3>
                @elseif ($type === 'ul')<ul>@foreach ($content as $li)<li>{{ $li }}</li>@endforeach</ul>
                @elseif ($type === 'p')<p>{{ $content }}</p>
                @endif
            @endforeach
        </div>
    </details>
    <div class="grid2" style="margin-top:12px">
        <div class="stack-sm">
            <span class="sigimg"><img src="{{ $consent->patient_signature }}" alt="Firma de {{ $signer['name'] }}"></span>
            <span class="small"><strong>{{ $signer['name'] }}</strong><br>{{ $signer['detail'] }}</span>
        </div>
        <div class="stack-sm">
            <span class="sigimg"><img src="{{ $consent->doctor_signature }}" alt="Firma de la doctora"></span>
            <span class="small"><strong>{{ $consent->doctor_name }}</strong></span>
        </div>
    </div>
    @if (! empty($fd['assent']))<p class="small">Asentimiento del menor ({{ $fd['assent']['name'] }}): <strong>{{ $fd['assent']['answer'] }}</strong></p>@endif
    @if (! empty($fd['witness']))<p class="small">Testigo: <strong>{{ $fd['witness']['name'] }}</strong>{{ $fd['witness']['doc'] ? ' · '.$fd['witness']['doc'] : '' }}</p>@endif
    <p class="small muted">Este documento no se puede modificar. Si algo cambia, se firma uno nuevo.</p>
</div>
@endsection
