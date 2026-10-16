<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $consent->title }}</title>
@php
    $fd = $consent->form_data;
    $blocks = \App\Support\ConsentForms::blocks($consent->body);
    $hasMarker = collect($blocks)->contains(fn ($b) => $b[0] === 'datos');
    $signer = $fd['signer'] ?? ['name' => $patient->name, 'detail' => $patient->documentLabel()];
    $license = $fd['license'] ?? $clinic->doctor_license;
@endphp
<style>
@include('pdf._styles')
    .title { font-size: 30pt; }
    .sub { text-align: right; font-size: 8.5pt; color: #6B5A70; margin-top: 3pt; line-height: 1.25; }
    .kv td { padding: 2pt 0; vertical-align: top; line-height: 1.2; font-size: 8.5pt; }
    .kv .k { width: 78pt; font-size: 6.5pt; letter-spacing: .6pt; text-transform: uppercase; color: #6B5A70; padding-right: 6pt; padding-top: 3pt; }
    .kv .v { padding-right: 10pt; }
    .doc { font-size: 8.3pt; }
    .doc h3 { font-family: 'Poppins'; font-weight: bold; font-size: 8.8pt; margin: 7pt 0 2pt; color: {{ $brand['ink'] }}; page-break-after: avoid; }
    .doc p { margin: 0 0 3pt; line-height: 1.22; text-align: justify; }
    .doc ul { margin: 0 0 3pt; padding-left: 12pt; }
    .doc li { margin-bottom: 1.5pt; line-height: 1.22; text-align: justify; }
    .decision td { padding: 3pt 0; line-height: 1.3; }
    .sigs td { width: 50%; vertical-align: bottom; padding-right: 18pt; }
    .sigimg { height: 56pt; }
    .sigline { border-top: 1pt solid {{ $brand['ink'] }}; padding-top: 4pt; line-height: 1.3; font-size: 8.5pt; }
    .note { font-size: 7.5pt; color: #6B5A70; line-height: 1.3; padding-top: 8pt; }
</style>
</head>
<body>
@include('pdf._frame')

<table>
    <tr>
        <td style="width:40%;vertical-align:top">
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ $clinic->name }}">@else<div class="title" style="text-align:left;font-size:22pt">{{ $clinic->doctor_name }}</div>@endif
        </td>
        <td style="vertical-align:top">
            <div class="title">Consentimiento informado</div>
            <div class="code">{{ mb_strtoupper($consent->title) }}</div>
            @if (! empty($fd['subtitle']))<div class="sub">{{ $fd['subtitle'] }}</div>@endif
        </td>
    </tr>
</table>

@php
    $datos = function () use ($fd) {
        if (empty($fd['rows'])) return '';
        // Datos cortos en dos columnas; los largos (diagnóstico, listas) ocupan la fila completa.
        $cell = fn ($k, $v, $span = false) => '<td class="k">'.e($k).'</td><td class="v b"'.($span ? ' colspan="3"' : '').'>'.nl2br(e($v)).'</td>';
        $html = '<div class="box" style="margin:8pt 0 6pt"><table class="kv">';
        $pending = null;
        foreach ($fd['rows'] as [$k, $v]) {
            $long = mb_strlen($v) > 38 || str_contains($v, "\n");
            if ($long) {
                if ($pending) { $html .= '<tr>'.$cell(...$pending).'<td></td><td></td></tr>'; $pending = null; }
                $html .= '<tr>'.$cell($k, $v, true).'</tr>';
            } elseif ($pending) {
                $html .= '<tr>'.$cell(...$pending).$cell($k, $v).'</tr>'; $pending = null;
            } else {
                $pending = [$k, $v];
            }
        }
        if ($pending) { $html .= '<tr>'.$cell(...$pending).'<td></td><td></td></tr>'; }
        return $html.'</table></div>';
    };
@endphp

@if ($fd && ! $hasMarker){!! $datos() !!}@endif

<div class="doc" style="margin-top:6pt">
    @foreach ($blocks as [$type, $content])
        @if ($type === 'datos'){!! $datos() !!}
        @elseif ($type === 'h')<h3>{{ $content }}</h3>
        @elseif ($type === 'ul')<ul>@foreach ($content as $li)<li>{{ $li }}</li>@endforeach</ul>
        @else<p>{{ $content }}</p>
        @endif
    @endforeach
</div>

<div>
    {{-- Decisión y firmas en un solo bloque: nunca quedan en páginas distintas. --}}
    <table class="sigs" style="margin-top:8pt;page-break-inside:avoid">
        @if (! empty($fd['decision']))
            @foreach ($fd['decision']['options'] as $i => $opt)
                @php $on = $fd['decision']['accepted'] === ($i === 0); @endphp
                <tr><td colspan="2" style="padding:2pt 0;line-height:1.3"><span class="check">{{ $on ? '☑' : '☐' }}</span> <span class="{{ $on ? 'b' : 'muted' }}">{{ $opt }}</span></td></tr>
            @endforeach
        @endif
        <tr>
            <td style="height:62pt"><img class="sigimg" src="{{ $consent->patient_signature }}" alt=""></td>
            <td style="height:62pt"><img class="sigimg" src="{{ $consent->doctor_signature }}" alt=""></td>
        </tr>
        <tr>
            <td class="sigline"><span class="b">{{ $signer['name'] }}</span><br>{{ $signer['detail'] }}</td>
            <td class="sigline"><span class="b">{{ $consent->doctor_name }}</span><br>{{ $clinic->doctor_title ?: 'Odontóloga' }}{{ $license ? ' · Registro profesional '.$license : '' }}</td>
        </tr>
        @if (! empty($fd['assent']))
            <tr><td colspan="2" style="padding-top:8pt;line-height:1.35">Asentimiento del menor ({{ $fd['assent']['name'] }}): “Después de que me explicaron qué van a hacer en mis dientes…” <span class="b">{{ $fd['assent']['answer'] }}</span>.</td></tr>
        @endif
        @if (! empty($fd['witness']))
            <tr><td colspan="2" style="padding-top:5pt;line-height:1.35">Testigo: <span class="b">{{ $fd['witness']['name'] }}</span>{{ $fd['witness']['doc'] ? ' · '.$fd['witness']['doc'] : '' }}</td></tr>
        @endif
        <tr><td colspan="2" class="note">Firmado electrónicamente el {{ $consent->signed_at->translatedFormat('j \d\e F \d\e Y, g:i a') }}.@if (! empty($fd['note'])) {{ $fd['note'] }}@endif</td></tr>
    </table>
</div>
</body>
</html>
