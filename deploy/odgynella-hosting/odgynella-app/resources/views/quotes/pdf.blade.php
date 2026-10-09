@use('App\Support\Time')
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $quote->code() }}</title>
<style>
@include('pdf._styles')
.totals td { padding: 4pt 9pt; }
.totals .grand td { font-size: 14pt; font-weight: bold; color: {{ $brand['main'] }}; border-top: 2pt solid {{ $brand['soft'] }}; padding-top: 7pt; }
.opts { font-size: 8pt; line-height: 1.3; }
</style>
</head>
<body>
@include('pdf._frame')

<table>
    <tr>
        <td style="width:55%;vertical-align:top">
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ $clinic->name }}">@else<div class="title" style="text-align:left;font-size:28pt">{{ $clinic->doctor_name }}</div>@endif
        </td>
        <td style="vertical-align:top">
            <div class="title">Cotización</div>
            <div class="code">{{ $quote->code() }}</div>
        </td>
    </tr>
</table>

<table style="margin-top:14pt">
    <tr>
        <td style="width:55%;vertical-align:top">
            <div class="label">Paciente:</div>
            <div class="big" style="margin-top:5pt">{{ $quote->patient->name }}</div>
            <div style="font-size:11pt;margin-top:2pt">{{ $quote->patient->phone }}</div>
            @if ($quote->patient->doc_number)<div class="muted">{{ $quote->patient->documentLabel() }}</div>@endif
            @if ($quote->patient->city)<div class="muted">{{ $quote->patient->city }}</div>@endif
        </td>
        <td style="vertical-align:top" class="r">
            @include('pdf._doctor')
        </td>
    </tr>
</table>

<table style="margin-top:8pt">
    <tr>
        <td class="muted" style="letter-spacing:1.5pt;font-size:8.5pt">FECHA: {{ $quote->issued_on->format('d/m/Y') }}</td>
        <td class="r" style="letter-spacing:1.5pt;font-size:8.5pt">VÁLIDA HASTA: <span class="accent b">{{ $quote->valid_until->format('d/m/Y') }}</span></td>
    </tr>
</table>

@php $hasOptions = $quote->items->contains(fn ($it) => $it->price_options && ! $it->line_total); @endphp
<table class="grid" style="margin-top:6pt">
    <thead>
        <tr>
            <th>Descripción</th>
            <th class="c" style="width:70pt">Dientes</th>
            <th class="c" style="width:52pt">Cantidad</th>
            <th class="r" style="width:130pt">Valor total</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($quote->items as $it)
        <tr>
            <td>{{ $it->description }}</td>
            <td class="c">{{ $it->teeth ?: '—' }}</td>
            <td class="c" style="font-size:12pt">{{ $it->quantity }}</td>
            <td class="val">
                @if ($it->line_total)
                    <div class="semi">{{ Time::money($it->line_total) }}</div>
                    @if ($it->quantity > 1)<div class="muted" style="font-size:7.5pt">{{ Time::money($it->unit_price) }} c/u</div>@endif
                @endif
                @if ($it->price_options)
                    <div class="opts">@foreach ($it->priceOptionLines() as $line){{ mb_strtoupper($line) }}<br>@endforeach</div>
                    @unless ($it->line_total)<div class="muted" style="font-size:7pt">Según el caso*</div>@endunless
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top:6pt">
    @if ($quote->discount_amount)
        <tr><td style="width:55%"></td><td>Subtotal</td><td class="r">{{ Time::money($quote->subtotal) }}</td></tr>
        <tr><td></td><td>{{ $quote->discount_label }}</td><td class="r accent">- {{ Time::money($quote->discount_amount) }}</td></tr>
    @endif
    <tr class="grand"><td style="width:55%;border:0"></td><td>Total</td><td class="r">{{ Time::money($quote->total) }}</td></tr>
</table>
@if ($hasOptions)
    <p class="muted" style="font-size:7.5pt;margin:4pt 0 0">* Los ítems con valor según el caso no están sumados en el total: el valor se confirma en la valoración presencial.</p>
@endif

<table style="margin-top:12pt">
    <tr>
        <td style="width:58%;vertical-align:top;padding-right:16pt">
            <div class="label">Facilidad de pago</div>
            <div style="margin-top:5pt">{{ $clinic->payment_note ?: 'Esta cotización incluye facilidades de pago.' }}</div>
            @if ($clinic->payment_options)<div class="muted" style="margin-top:3pt">Formas de pago: {{ $clinic->payment_options }}.</div>@endif
        </td>
        <td style="vertical-align:top">
            @if ($quote->patient_notes)
                <div class="label">Observaciones</div>
                <div style="margin:5pt 0 8pt;white-space:pre-line">{{ $quote->patient_notes }}</div>
            @endif
            <div class="muted" style="font-size:7pt">
                Valores estimados con base en la asesoría; no constituyen un diagnóstico. El plan y su valor definitivo se
                confirman en la valoración presencial con radiografía. Válida por {{ (int) $quote->issued_on->diffInDays($quote->valid_until) }} días.
            </div>
        </td>
    </tr>
</table>
</body>
</html>
