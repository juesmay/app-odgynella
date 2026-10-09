@extends('layouts.app')
@section('title', 'Cartera')
@use('App\Support\Time')
@use('App\Support\Accounts')

@section('content')
<div class="head">
    <div>
        <h1>Cartera</h1>
        <p class="sub">Lo que los pacientes deben de procedimientos ya realizados. Los días cuentan desde el último abono.</p>
    </div>
    <div class="stat" style="padding:10px 16px;min-width:200px">
        <div class="label">Total por cobrar</div>
        <div class="v">{{ Time::money($total) }}</div>
    </div>
</div>

<div class="stats">
    @foreach (Accounts::BUCKETS as $key => [$label, $range, $class])
        <div class="stat {{ $key === '90' && $buckets[$key]['amount'] > 0 ? 'neg' : '' }}">
            <div class="label">{{ $label }}</div>
            <div class="v">{{ Time::money($buckets[$key]['amount']) }}</div>
            <div class="d">{{ $buckets[$key]['count'] === 1 ? '1 paciente' : $buckets[$key]['count'].' pacientes' }} · {{ $range }}</div>
        </div>
    @endforeach
</div>

<div class="card" style="margin-top:20px">
    @if ($rows->isEmpty())
        <div class="empty">Nadie debe nada de lo ya realizado. ✓</div>
    @else
        <div class="list">
            @foreach ($rows as $i => $r)
                @php [$bl, , $bc] = Accounts::BUCKETS[$r['bucket']]; @endphp
                <div class="li">
                    <span class="grow">
                        <a href="{{ route('patients.account', $r['patient']) }}"><strong>{{ $r['patient']->name }}</strong></a>
                        <span class="small muted">· {{ $r['quote']->code() }}</span><br>
                        <span class="small muted">Realizado {{ Time::money($r['account']['done']) }} · pagado {{ Time::money($r['account']['paid']) }} · desde el {{ $r['since']->translatedFormat('j M') }}</span>
                    </span>
                    <span class="chip {{ $bc }}">{{ $r['days'] === 0 ? 'Hoy' : $r['days'].' días' }}</span>
                    <span class="amt" style="min-width:110px">{{ Time::money($r['account']['owed']) }}</span>
                    <span class="row" style="gap:6px">
                        <button type="button" class="btn sm" data-copy="msg-{{ $i }}">Copiar recordatorio</button>
                        @if ($r['link'])<a class="btn sm" href="{{ $r['link'] }}" target="_blank" rel="noopener">Abrir WhatsApp</a>@else<a class="btn sm" href="{{ route('patients.show', $r['patient']) }}" title="Agrega el WhatsApp en la ficha">Sin WhatsApp</a>@endif
                        <a class="btn sm pri" href="{{ route('payments.create', ['paciente' => $r['patient']->id, 'cotizacion' => $r['quote']->id]) }}">Registrar abono</a>
                    </span>
                    <textarea id="msg-{{ $i }}" hidden>{{ $r['message'] }}</textarea>
                </div>
            @endforeach
        </div>
    @endif
</div>
<p class="small muted" style="margin-top:10px">Lo que falta por hacer del tratamiento no es deuda: solo entra a cartera lo realizado y no pagado.</p>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = document.getElementById(btn.dataset.copy).value, label = btn.textContent;
        var done = function () { btn.textContent = 'Copiado'; setTimeout(function () { btn.textContent = label; }, 2000); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
        else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
    });
});
</script>
@endpush
