<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $order->code() }}</title>
<style>
@include('pdf._styles')
.title { font-size: 30pt; }
.kv td { padding: 2.5pt 0; vertical-align: top; }
.kv .k { width: 92pt; font-size: 8pt; letter-spacing: .8pt; text-transform: uppercase; color: #6B5A70; }
.sig { height: 48pt; }
.sigline { border-top: 1pt solid {{ $brand['ink'] }}; padding-top: 4pt; width: 280pt; }
</style>
</head>
<body>
@include('pdf._frame')
@php $p = $patient; $age = $p->age(); @endphp

<table>
    <tr>
        <td style="width:42%;vertical-align:top">
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ $clinic->name }}">@else<div class="title" style="text-align:left;font-size:24pt">{{ $clinic->doctor_name }}</div>@endif
        </td>
        <td style="vertical-align:top">
            <div class="title">Orden de radiografía</div>
            <div class="code">{{ $order->code() }} · {{ $order->issued_on->translatedFormat('j \d\e F \d\e Y') }}</div>
        </td>
    </tr>
</table>

<table style="margin-top:12pt">
    <tr>
        <td style="width:55%;vertical-align:top;padding-right:14pt">
            <div class="label">Centro radiológico:</div>
            <div class="semi" style="margin-top:5pt;font-size:10.5pt">{{ $order->center ?: 'El de preferencia del paciente' }}</div>
        </td>
        <td style="vertical-align:top" class="r">
            @include('pdf._doctor')
        </td>
    </tr>
</table>

<div class="box" style="margin-top:12pt">
    <div class="label" style="margin-bottom:4pt">Paciente:</div>
    <table class="kv">
        <tr><td class="k">Nombre</td><td class="b">{{ $p->name }}</td><td class="k">Documento</td><td>{{ $p->documentLabel() }}</td></tr>
        <tr><td class="k">Nacimiento</td><td>{{ $p->birth_date ? $p->birth_date->format('d/m/Y') : '—' }}{{ $age !== null ? ' · '.$age.' años' : '' }}</td><td class="k">Teléfono</td><td>{{ $p->phone }}</td></tr>
        <tr><td class="k">Correo</td><td>{{ $p->email ?: '—' }}</td><td class="k">Ciudad</td><td>{{ $p->city ?: '—' }}</td></tr>
    </table>
</div>

<div class="label" style="margin-top:14pt;margin-bottom:6pt">Estudios a realizar</div>
<table class="grid">
    <thead><tr><th>Estudio</th><th style="width:150pt">Diente o zona</th><th class="c" style="width:60pt">Cantidad</th></tr></thead>
    <tbody>
    @foreach ($order->studies as $s)
        <tr><td class="semi">{{ $s['name'] }}</td><td>{{ $s['zone'] ?: '—' }}</td><td class="c">{{ $s['qty'] }}</td></tr>
    @endforeach
    </tbody>
</table>

@if ($order->indication)
    <div class="label" style="margin-top:14pt">Indicación clínica</div>
    <div style="margin-top:4pt;white-space:pre-line">{{ $order->indication }}</div>
@endif

<div class="label" style="margin-top:14pt">Entrega de resultados</div>
<div style="margin-top:4pt"><span class="check">☑</span> {{ $order->deliveryLabel() }}@if ($order->delivery === 'correo_doctora' && $clinic->email): <span class="b">{{ $clinic->email }}</span>@elseif ($order->delivery === 'virtual_paciente' && $p->email): <span class="b">{{ $p->email }}</span>@endif</div>

@if ($order->notes)
    <div class="label" style="margin-top:16pt">Observaciones</div>
    <div style="margin-top:4pt;white-space:pre-line">{{ $order->notes }}</div>
@endif

<table style="margin-top:14pt;width:280pt;page-break-inside:avoid">
    <tr><td style="height:50pt;vertical-align:bottom">@if ($signature)<img class="sig" src="{{ $signature }}" alt="Firma">@endif</td></tr>
    <tr><td class="sigline">
        <div class="b">{{ $clinic->doctor_name }}</div>
        <div class="muted">{{ $clinic->doctor_title ?: 'Odontóloga' }}{{ $clinic->doctor_license ? ' · Registro profesional '.$clinic->doctor_license : '' }}</div>
    </td></tr>
</table>
</body>
</html>
