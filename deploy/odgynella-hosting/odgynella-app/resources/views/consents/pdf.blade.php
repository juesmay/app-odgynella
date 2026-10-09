<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $consent->title }}</title>
<style>
@include('pdf._styles')
    body { font-size: 9.5pt; line-height: 1.6; }
    h1 { font-family: 'Bebas Neue', 'DejaVu Sans'; font-weight: normal; font-size: 26pt; line-height: 1; margin: 18pt 0 10pt; color: {{ $brand['ink'] }}; }
    .text { white-space: pre-line; }
    table { width: 100%; border-collapse: collapse; margin-top: 30px; }
    td { width: 50%; vertical-align: bottom; padding-right: 20px; }
    .sig img { height: 70px; }
    .line { border-top: 1px solid {{ $brand['ink'] }}; padding-top: 4px; font-size: 8.5pt; }
</style>
</head>
<body>
@include('pdf._frame')
@if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ $clinic->name }}">@else<div class="b" style="font-size:16pt">{{ $clinic->doctor_name }}</div>@endif
<h1>Consentimiento informado: {{ $consent->title }}</h1>
<div class="text">{{ $consent->body }}</div>
<table>
    <tr>
        <td class="sig"><img src="{{ $consent->patient_signature }}" alt=""></td>
        <td class="sig"><img src="{{ $consent->doctor_signature }}" alt=""></td>
    </tr>
    <tr>
        <td class="line"><strong>{{ $patient->name }}</strong><br>{{ $patient->documentLabel() }}</td>
        <td class="line"><strong>{{ $consent->doctor_name }}</strong>{{ $clinic->doctor_license ? ' · Registro profesional '.$clinic->doctor_license : '' }}</td>
    </tr>
</table>
<p class="muted" style="font-size:7.5pt;margin-top:14pt">Firmado electrónicamente el {{ $consent->signed_at->translatedFormat('j \d\e F \d\e Y, g:i a') }}.</p>
</body>
</html>
