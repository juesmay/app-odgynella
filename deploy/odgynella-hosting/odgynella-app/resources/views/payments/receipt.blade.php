@extends('layouts.app')
@section('title', 'Recibo '.$payment->receiptCode())
@use('App\Support\Time')

@section('content')
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.show', $payment->patient) }}">← {{ $payment->patient->name }}</a>
        <h1 style="margin-top:6px">Recibo de caja</h1>
    </div>
    <a class="btn" href="{{ route('payments.create') }}">Otro pago</a>
</div>

<div class="grid2">
    <div class="receipt" style="background:var(--surface)">
        <div class="row" style="justify-content:space-between"><strong>{{ $clinic->name }} · {{ $clinic->doctor_name }}</strong><span class="mono">{{ $payment->receiptCode() }}</span></div>
        @if ($payment->isVoided())
            <div class="alertbar">Anulado el {{ $payment->voided_at->translatedFormat('j M Y, g:i a') }}: {{ $payment->void_reason }}</div>
        @endif
        <hr class="sep">
        <div class="kv"><span>Fecha</span><b>{{ $payment->paid_on->translatedFormat('j \d\e F \d\e Y') }}</b></div>
        <div class="kv"><span>Paciente</span><b>{{ $payment->patient->name }}</b></div>
        <div class="kv"><span>Documento</span><b>{{ $payment->patient->doc_type }} {{ $payment->patient->doc_number }}</b></div>
        <div class="kv"><span>Concepto</span><b>{{ $payment->concept }}</b></div>
        <div class="kv"><span>Medio de pago</span><b>{{ $payment->method }}</b></div>
        <div class="kv"><span>Sede</span><b>{{ $payment->sede->name }}</b></div>
        <div class="kv"><span>Recibió</span><b>{{ $payment->author->name }}</b></div>
        <hr class="sep">
        <div class="kv" style="font-size:20px"><span>Valor recibido</span><b>{{ Time::money($payment->amount) }}</b></div>
        @if ($payment->quote && ! $payment->isVoided())
            <div class="kv"><span>Saldo del tratamiento {{ $payment->quote->code() }}</span><b>{{ Time::money($balance) }}</b></div>
        @endif
    </div>

    <div class="stack">
        <div class="card stack">
            <h2>Enviar por WhatsApp</h2>
            <textarea id="wa-text" class="input" readonly style="min-height:120px">{{ $whatsapp }}</textarea>
            <button type="button" class="btn pri" id="copy-btn">Copiar mensaje</button>
        </div>
        @if (auth()->user()->isDoctor() && ! $payment->isVoided())
            <details class="card">
                <summary style="cursor:pointer;font-weight:700">Anular este pago</summary>
                <form method="POST" action="{{ route('payments.void', $payment) }}" class="stack" style="margin-top:12px">
                    @csrf
                    <p class="small muted">El pago no se borra: queda tachado en el historial con el motivo. Solo se puede anular mientras la caja de ese día siga abierta.</p>
                    <label class="field" for="void_reason"><span>Motivo</span>
                        <input id="void_reason" name="void_reason" class="input" required minlength="5" maxlength="200" placeholder="Ej: se registró dos veces">
                    </label>
                    <button class="btn danger">Anular pago</button>
                </form>
            </details>
        @endif
    </div>
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
