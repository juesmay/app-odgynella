@extends('layouts.app')
@section('title', $quote->code())
@use('App\Support\Time')

@section('content')
@php $p = $quote->patient; @endphp
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.show', $p) }}">← {{ $p->name }}</a>
        <h1 style="margin-top:6px">Cotización {{ $quote->code() }}</h1>
        <p class="sub">
            @if ($quote->historical)
                Tratamiento histórico importado del libro de control ({{ $quote->issued_on->translatedFormat('j \d\e F \d\e Y') }}).
            @else
            Emitida el {{ $quote->issued_on->translatedFormat('j \d\e F') }} · válida hasta el {{ $quote->valid_until->translatedFormat('j \d\e F \d\e Y') }}
            @if ($quote->displayStatus() === 'vigente') ({{ $quote->daysLeft() === 0 ? 'vence hoy' : 'quedan '.$quote->daysLeft().' días' }})@endif
            @endif
        </p>
    </div>
    <div class="row">
        <span class="chip {{ $quote->statusClass() }}">{{ $quote->statusLabel() }}</span>
        <a class="btn pri" href="{{ route('quotes.pdf', $quote) }}">Descargar PDF</a>
    </div>
</div>

<div class="grid-side">
    <div class="stack">
        <div class="card">
            <div class="tblwrap"><table class="tbl">
                <thead><tr><th>Tratamiento</th><th>Dientes</th><th class="r">Cant.</th><th class="r">Valor unitario</th><th class="r">Total</th></tr></thead>
                <tbody>
                @foreach ($quote->items as $it)
                    <tr>
                        <td>{{ $it->description }}@if ($it->done_at)<br><span class="chip c-done" style="margin-top:4px">Realizado {{ $it->done_at->translatedFormat('j M') }}</span>@endif</td>
                        <td class="mono">{{ $it->teeth ?: '—' }}</td>
                        <td class="r">{{ $it->quantity }}</td>
                        <td class="r">{{ $it->line_total || ! $it->price_options ? Time::money($it->unit_price) : '—' }}</td>
                        <td class="r">@if ($it->line_total || ! $it->price_options){{ Time::money($it->line_total) }}@endif
                            @if ($it->price_options)<div class="small muted">{!! collect($it->priceOptionLines())->map(fn ($l) => e($l))->implode('<br>') !!}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="receipt" style="max-width:420px;margin:16px 0 0 auto">
                <div class="kv"><span>Subtotal</span><b>{{ Time::money($quote->subtotal) }}</b></div>
                @if ($quote->discount_amount)
                    <div class="kv"><span>{{ $quote->discount_label }}</span><b>- {{ Time::money($quote->discount_amount) }}</b></div>
                @endif
                <hr class="sep">
                <div class="kv" style="font-size:20px"><span>Total</span><b>{{ Time::money($quote->total) }}</b></div>
            </div>
            <p class="small muted" style="margin-top:12px">Formas de pago: {{ $clinic->payment_options }}.</p>
        </div>

        <div class="grid2">
            <div class="card">
                <div class="label" style="margin-bottom:8px">Lo que contó la persona</div>
                <p style="white-space:pre-line">{{ $quote->internal_notes ?: 'Sin notas.' }}</p>
                <p class="small muted" style="margin-top:8px">Solo para el consultorio. No sale en el PDF.</p>
            </div>
            <div class="card">
                <div class="label" style="margin-bottom:8px">Observaciones en el PDF</div>
                <p style="white-space:pre-line">{{ $quote->patient_notes ?: 'Ninguna.' }}</p>
            </div>
        </div>
    </div>

    <aside class="stack">
        @if ($quote->status === 'aceptada')
            @php $a = $quote->account(); @endphp
            <div class="card stack">
                <h2>Cuenta del tratamiento</h2>
                <div class="receipt">
                    <div class="kv"><span>Realizado</span><b>{{ Time::money($a['done']) }}</b></div>
                    <div class="kv"><span>Pagado</span><b>{{ Time::money($a['paid']) }}</b></div>
                    <hr class="sep">
                    <div class="kv" style="font-size:18px"><span>Saldo</span><b>{{ Time::money($a['balance']) }}</b></div>
                    @if ($a['owed'] > 0)<div class="kv" style="color:var(--danger)"><span>Debe de lo ya hecho</span><b>{{ Time::money($a['owed']) }}</b></div>@endif
                </div>
                @if ($a['balance'] > 0)
                    <a class="btn pri block" href="{{ route('payments.create', ['paciente' => $p->id, 'cotizacion' => $quote->id]) }}">Registrar abono</a>
                @endif
                <a class="btn ghost block" href="{{ route('patients.account', $p) }}">Ver cuenta completa</a>
            </div>
        @endif
        <div class="card stack">
            <h2>Siguiente paso</h2>
            <div class="row">
                <strong>{{ $p->name }}</strong>
                <span class="chip {{ $p->stageClass() }}">{{ $p->stageLabel() }}</span>
            </div>
            @if ($p->stage === 'prospecto' || $p->stage === 'no_continuo')
                <a class="btn pri block" href="{{ $p->isComplete() ? route('appointments.create', ['paciente' => $p->id]) : route('patients.complete', $p) }}">Agendar valoración presencial</a>
                @unless ($p->isComplete())<p class="small muted">Te pedirá el documento y la autorización de datos que faltan.</p>@endunless
            @endif
            @if ($quote->status === 'vigente')
                <form method="POST" action="{{ route('quotes.accept', $quote) }}">@csrf<button class="btn block">Aceptó esta cotización</button></form>
                <a class="btn ghost block" href="{{ route('quotes.edit', $quote) }}">Editar cotización</a>
            @endif
            <a class="btn ghost block" href="{{ route('quotes.create', ['paciente' => $p->id]) }}">Hacer otra cotización</a>
        </div>

        <div class="card stack">
            <h2>Enviar por WhatsApp</h2>
            <textarea id="wa-text" class="input" readonly style="min-height:130px">{{ $whatsapp }}</textarea>
            <button type="button" class="btn" id="copy-btn">Copiar mensaje</button>
            <p class="small muted">Descarga el PDF y adjúntalo en el mismo chat.</p>
        </div>

        @if ($p->stage === 'prospecto')
            @include('patients._lost', ['patient' => $p])
        @endif
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copy-btn').addEventListener('click', function () {
    var ta = document.getElementById('wa-text'), btn = this;
    var done = function () { btn.textContent = 'Copiado'; setTimeout(function () { btn.textContent = 'Copiar mensaje'; }, 2000); };
    if (navigator.clipboard) { navigator.clipboard.writeText(ta.value).then(done, function () { ta.select(); }); }
    else { ta.select(); document.execCommand('copy'); done(); }
});
</script>
@endpush
