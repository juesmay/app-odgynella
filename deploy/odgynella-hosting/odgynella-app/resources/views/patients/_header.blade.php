{{-- Cabecera de la ficha: nombre, acciones, avisos, alertas y pestañas. Requiere $patient y $tab. --}}
@php $alerts = $patient->alerts(); @endphp
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.index') }}">← Pacientes</a>
        <h1 style="margin-top:6px">{{ $patient->name }}</h1>
        <p class="sub">
            {{ $patient->age() !== null ? $patient->age().' años · ' : '' }}{{ $patient->documentLabel() }}
            {{ $patient->city ? '· '.$patient->city : '' }} · @if ($patient->phone)WhatsApp {{ $patient->phone }}@else<span class="chip c-noshow" style="padding:2px 8px">Sin WhatsApp</span>@endif
        </p>
    </div>
    <div class="row">
        <span class="chip {{ $patient->stageClass() }}">{{ $patient->stageLabel() }}</span>
        <a class="btn" href="{{ route('quotes.create', ['paciente' => $patient->id]) }}">Nueva cotización</a>
        <a class="btn" href="{{ $patient->isComplete() ? route('appointments.create', ['paciente' => $patient->id]) : route('patients.complete', $patient) }}">Agendar</a>
        <a class="btn" href="{{ route('payments.create', ['paciente' => $patient->id]) }}">Registrar pago</a>
        <a class="btn pri" href="{{ route('consents.create', $patient) }}">Firmar consentimiento</a>
    </div>
</div>

@if ($patient->stage === 'prospecto' && ! $patient->isComplete())
    <div class="infobar">
        <span>Está en cotización. Para atenderla en persona faltan el documento y la autorización de datos.</span>
        <span class="sp"></span>
        <a class="btn sm" href="{{ route('patients.complete', $patient) }}">Completar y agendar valoración</a>
    </div>
@elseif ($patient->stage === 'no_continuo')
    <div class="infobar">
        <span>No continuó{{ $patient->lost_reason ? ': '.$patient->lost_reason : '' }}{{ $patient->stage_changed_at ? ' ('.$patient->stage_changed_at->translatedFormat('j M Y').')' : '' }}.</span>
        <span class="sp"></span>
        <form method="POST" action="{{ route('patients.reactivate', $patient) }}">@csrf<button class="btn sm">Volver a cotización</button></form>
    </div>
@endif

@if ($alerts)
    <div class="alertbar">@include('partials.icon', ['name' => 'alert', 'size' => 20])<span>{{ implode(' · ', $alerts) }}</span></div>
@endif

<nav class="tabs" aria-label="Secciones de la ficha">
    @foreach ([
        'resumen' => ['Resumen', route('patients.show', $patient)],
        'historia' => ['Historia clínica', route('patients.clinical', $patient)],
        'odontograma' => ['Odontograma', route('patients.odontogram', $patient)],
        'evoluciones' => ['Evoluciones', route('patients.evolutions', $patient)],
        'fotos' => ['Fotos', route('patients.photos', $patient)],
        'consentimientos' => ['Consentimientos', route('patients.consents', $patient)],
        'ordenes' => ['Órdenes', route('patients.orders', $patient)],
        'cuenta' => ['Cuenta', route('patients.account', $patient)],
    ] as $key => [$label, $url])
        <a class="tab" href="{{ $url }}" @if (($tab ?? 'resumen') === $key) aria-current="page" aria-selected="true" @else aria-selected="false" @endif>{{ $label }}</a>
    @endforeach
</nav>
