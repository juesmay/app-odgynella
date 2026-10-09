@extends('layouts.app')
@section('title', 'Caja')
@use('App\Support\Time')

@section('content')
<div class="head">
    <div>
        <h1>Caja de hoy · {{ $sede?->name }}</h1>
        <p class="sub">
            @if ($open)
                Abierta desde las {{ $open->opened_at->format('g:i a') }} con base de {{ Time::money($open->opening_amount) }}.
            @else
                Cerrada. Ábrela para empezar a recibir pagos en esta sede.
            @endif
        </p>
    </div>
    @if ($open)
        <a class="btn pri" href="{{ route('payments.create') }}">Registrar pago</a>
    @endif
</div>

<div class="stats">
    @foreach (array_slice(\App\Models\Payment::METHODS, 0, 4) as $m)
        <div class="stat"><div class="label">{{ $m }}</div><div class="v">{{ Time::money($byMethod[$m] ?? 0) }}</div></div>
    @endforeach
</div>

<div class="grid-side">
    <div class="card">
        <h2>Pagos de hoy</h2>
        @if ($payments->isEmpty())
            <div class="empty">Todavía no hay pagos hoy en esta sede.</div>
        @else
            <div class="tblwrap"><table class="tbl">
                <thead><tr><th>Recibo</th><th>Paciente</th><th>Concepto</th><th>Medio</th><th class="r">Valor</th></tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr class="{{ $p->isVoided() ? 'voided' : '' }}">
                        <td><a class="mono" href="{{ route('payments.receipt', $p) }}">{{ $p->receiptCode() }}</a></td>
                        <td>{{ $p->patient->name }}</td>
                        <td>{{ $p->concept }}{{ $p->isVoided() ? ' (anulado)' : '' }}</td>
                        <td>{{ $p->method }}</td>
                        <td class="r">{{ Time::money($p->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="stack">
        @if ($open)
            <form method="POST" action="{{ route('cash.close') }}" class="card stack">
                @csrf
                <h2>Cerrar caja</h2>
                <div class="receipt">
                    <div class="kv"><span>Base</span><b>{{ Time::money($open->opening_amount) }}</b></div>
                    <div class="kv"><span>Efectivo recibido</span><b>{{ Time::money($open->cashReceived()) }}</b></div>
                    @if ($spentCash = $open->cashSpent())
                        <div class="kv"><span>Gastos pagados con la caja</span><b>- {{ Time::money($spentCash) }}</b></div>
                    @endif
                    <hr class="sep">
                    <div class="kv" style="font-size:17px"><span>Debería haber</span><b>{{ Time::money($open->expectedNow()) }}</b></div>
                </div>
                <label class="field" for="counted_cash"><span>Efectivo contado</span>
                    <input id="counted_cash" name="counted_cash" class="input" inputmode="numeric" required placeholder="Cuenta el efectivo y escríbelo">
                </label>
                <button class="btn pri">Cerrar caja</button>
            </form>
        @else
            <form method="POST" action="{{ route('cash.open') }}" class="card stack">
                @csrf
                <h2>Abrir caja</h2>
                <label class="field" for="opening_amount"><span>Base en efectivo</span>
                    <input id="opening_amount" name="opening_amount" class="input" inputmode="numeric" value="{{ old('opening_amount', '200000') }}" required>
                </label>
                <button class="btn pri">Abrir caja</button>
            </form>
        @endif

        <div class="card">
            <h2>Cierres anteriores</h2>
            <div class="list">
                @forelse ($history as $h)
                    @php $d = $h->difference(); @endphp
                    <div class="li">
                        <span class="grow">{{ $h->closed_at->translatedFormat('j M') }} · {{ $h->sede->name }}</span>
                        <span class="chip {{ $d === 0 ? 'c-done' : 'c-cancel' }}">{{ $d === 0 ? 'Cuadró' : ($d > 0 ? 'Sobró ' : 'Faltó ').Time::money(abs($d)) }}</span>
                    </div>
                @empty
                    <p class="muted">Aún no hay cierres.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
