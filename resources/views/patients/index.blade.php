@extends('layouts.app')
@section('title', 'Pacientes')

@section('content')
<div class="head">
    <div>
        <h1>Pacientes</h1>
        <p class="sub">{{ $patients->total() }} {{ $q !== '' ? 'coinciden con “'.$q.'”' : 'personas' }}{{ $stage ? ' · '.mb_strtolower(\App\Models\Patient::STAGES[$stage][0]) : '' }}</p>
    </div>
    <div class="row">
        <a class="btn" href="{{ route('quotes.create') }}">Nueva cotización</a>
        <a class="btn pri" href="{{ route('patients.create') }}">Nuevo paciente</a>
    </div>
</div>

<div class="tabs" role="tablist">
    <a class="tab" role="tab" aria-selected="{{ $stage ? 'false' : 'true' }}" href="{{ route('patients.index', array_filter(['q' => $q])) }}" style="text-decoration:none">Todos · {{ $counts->sum() }}</a>
    @foreach (\App\Models\Patient::STAGES as $key => [$label])
        <a class="tab" role="tab" aria-selected="{{ $stage === $key ? 'true' : 'false' }}" href="{{ route('patients.index', array_filter(['etapa' => $key, 'q' => $q])) }}" style="text-decoration:none">{{ $label }} · {{ $counts[$key] ?? 0 }}</a>
    @endforeach
</div>

<div class="card">
    <form method="GET" action="{{ route('patients.index') }}" class="row">
        @if ($stage)<input type="hidden" name="etapa" value="{{ $stage }}">@endif
        <label class="field" for="q" style="flex:1"><span>Buscar por nombre, documento o WhatsApp</span>
            <input id="q" name="q" type="search" class="input" value="{{ $q }}" placeholder="Ej: Laura o 300…" autocomplete="off">
        </label>
        <button class="btn" style="align-self:flex-end">Buscar</button>
    </form>

    <div class="list" style="margin-top:12px">
        @forelse ($patients as $p)
            <a class="rowbtn" href="{{ route('patients.show', $p) }}">
                <div style="flex:1;min-width:180px">
                    <strong>{{ $p->name }}</strong>
                    <div class="muted small">{{ $p->documentLabel() }}{{ $p->city ? ' · '.$p->city : '' }}</div>
                </div>
                @if ($p->alerts())<span class="chip c-alert">Alerta médica</span>@endif
                <span class="chip {{ $p->stageClass() }}">{{ $p->stageLabel() }}</span>
                <span class="small muted">{{ $p->phoneLabel() }}{{ $p->historical ? ' · histórico' : '' }}</span>
            </a>
        @empty
            <div class="empty">{{ $q !== '' ? 'Nadie coincide con “'.$q.'”.' : 'No hay personas en esta etapa.' }} <a href="{{ route('quotes.create') }}">Hacer una cotización</a></div>
        @endforelse
    </div>
    <div style="margin-top:12px">{{ $patients->links('partials.pager') }}</div>
</div>
@endsection
