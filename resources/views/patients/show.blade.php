@extends('layouts.app')
@section('title', $patient->name)
@use('App\Support\Time')

@section('content')
@include('patients._header', ['tab' => 'resumen'])

<div class="grid2">
    <div class="stack">
        <div class="stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="stat">
                <div class="label">Próxima cita</div>
                <div class="v" style="font-size:18px">{{ $next ? ucfirst($next->date->translatedFormat('D j M')).' · '.Time::human($next->start_time) : 'Sin cita' }}</div>
                <div class="d">{{ $next?->sede?->name }}</div>
            </div>
            <div class="stat">
                <div class="label">Total pagado</div>
                <div class="v">{{ Time::money($paidTotal) }}</div>
                <div class="d">{{ $payments->whereNull('voided_at')->count() }} pagos</div>
            </div>
        </div>

        <div class="card">
            <div class="card-h"><h2>Cotizaciones y plan de tratamiento</h2><a class="btn sm" href="{{ route('quotes.create', ['paciente' => $patient->id]) }}">Nueva cotización</a></div>
            <div class="list">
                @forelse ($patient->quotes as $q)
                    <a class="rowbtn" href="{{ route('quotes.show', $q) }}">
                        <span class="mono">{{ $q->code() }}</span>
                        <span class="grow small muted" style="flex:1">{{ $q->issued_on->translatedFormat('j M Y') }} · {{ $q->historical ? 'histórico' : 'vence '.$q->valid_until->translatedFormat('j M') }}</span>
                        <span class="chip {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span>
                        <strong class="money">{{ Time::money($q->total) }}</strong>
                    </a>
                @empty
                    <p class="muted">Sin cotizaciones.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h2>Citas</h2>
            <div class="list">
                @forelse ($patient->appointments->take(8) as $a)
                    <div class="li">
                        <span class="grow">{{ ucfirst($a->date->translatedFormat('D j M Y')) }} · {{ Time::human($a->start_time) }}<br><span class="small muted">{{ $a->service->name }} · {{ $a->sede->name }}</span></span>
                        <span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
                    </div>
                @empty
                    <p class="muted">Sin citas todavía.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="stack">
        @php $last = $patient->evolutions()->first(); @endphp
        <div class="card">
            <div class="card-h"><h2>Última nota</h2><a class="small" href="{{ route('patients.evolutions', $patient) }}">Ver evoluciones</a></div>
            @if ($last)
                <p class="small muted">{{ $last->typeName() }} · {{ $last->signed_at->translatedFormat('j M Y, g:i a') }}</p>
                @foreach (array_slice($last->lines(), 0, 3) as [$label, $text])
                    <p style="margin-top:6px;white-space:pre-line"><strong>{{ $label }}:</strong> {{ $text }}</p>
                @endforeach
            @else
                <p class="muted">Primera visita.</p>
            @endif
        </div>

        <div class="card">
            <div class="card-h"><h2>Pagos</h2><a class="btn sm" href="{{ route('payments.create', ['paciente' => $patient->id]) }}">Registrar pago</a></div>
            @if ($payments->isEmpty())
                <p class="muted">Sin pagos.</p>
            @else
                <div class="tblwrap"><table class="tbl">
                    <thead><tr><th>Fecha</th><th>Recibo</th><th>Concepto</th><th class="r">Valor</th></tr></thead>
                    <tbody>
                    @foreach ($payments as $pay)
                        <tr class="{{ $pay->isVoided() ? 'voided' : '' }}">
                            <td>{{ $pay->paid_on->translatedFormat('j M Y') }}</td>
                            <td><a class="mono" href="{{ route('payments.receipt', $pay) }}">{{ $pay->receiptCode() }}</a></td>
                            <td>{{ $pay->concept }}{{ $pay->isVoided() ? ' (anulado)' : '' }}</td>
                            <td class="r">{{ Time::money($pay->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        @if ($patient->notes)
            <div class="card">
                <div class="label" style="margin-bottom:6px">{{ $patient->historical ? 'Notas del histórico' : 'Notas' }}</div>
                <p class="small" style="white-space:pre-line">{{ $patient->notes }}</p>
            </div>
        @endif

        <details class="card" @if (! $patient->phone) open @endif>
            <summary style="cursor:pointer;font-weight:700">Editar datos de la paciente</summary>
            @unless ($patient->phone)<p class="small" style="color:var(--warn);margin-top:8px">Falta el WhatsApp. Escríbelo aquí para poder agendar, cotizar y enviarle recordatorios.</p>@endunless
            <form method="POST" action="{{ route('patients.update', $patient) }}" class="stack" style="margin-top:14px">
                @csrf @method('PUT')
                @include('patients._fields', ['requireDocument' => $patient->isComplete()])
                <label class="check"><input type="checkbox" name="photo_consent" value="1" @checked($patient->photo_consent)><span>Autoriza usar sus fotos en redes sociales</span></label>
                <p class="small muted">{{ $patient->data_consent_at ? 'Autorizó el tratamiento de datos el '.$patient->data_consent_at->translatedFormat('j \d\e F \d\e Y').'.' : 'Todavía no ha autorizado el tratamiento de datos.' }}</p>
                <button class="btn">Guardar datos</button>
            </form>
        </details>

        @if ($patient->stage === 'prospecto')
            @include('patients._lost', ['patient' => $patient])
        @endif
    </div>
</div>
@endsection
