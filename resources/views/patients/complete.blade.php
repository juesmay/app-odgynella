@extends('layouts.app')
@section('title', 'Completar datos')

@section('content')
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.show', $patient) }}">← {{ $patient->name }}</a>
        <h1 style="margin-top:6px">Completar datos de {{ $patient->firstName() }}</h1>
        <p class="sub">Para atenderse en persona se necesitan el documento y la autorización de datos. Luego eliges el día de la valoración.</p>
    </div>
</div>

<form method="POST" action="{{ route('patients.complete.store', $patient) }}" class="card stack" style="max-width:640px">
    @csrf @method('PUT')
    <div class="row"><strong>{{ $patient->name }}</strong><span class="muted">{{ $patient->phone ? 'WhatsApp '.$patient->phone : 'Sin WhatsApp: escríbelo abajo' }}</span></div>
    <div class="fgrid">
        <label class="field" for="doc_type"><span>Tipo de documento</span>
            <select id="doc_type" name="doc_type" class="input">
                @foreach (\App\Models\Patient::DOC_TYPES as $t)
                    <option @selected(old('doc_type', $patient->doc_type ?? 'C.C.') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="doc_number"><span>Número</span>
            <input id="doc_number" name="doc_number" class="input" value="{{ old('doc_number', $patient->doc_number) }}" required maxlength="40" autofocus>
        </label>
        <label class="field" for="birth_date"><span>Fecha de nacimiento (opcional)</span>
            <input id="birth_date" name="birth_date" type="date" class="input" value="{{ old('birth_date', $patient->birth_date?->toDateString()) }}">
        </label>
    </div>
    <label class="check"><input type="checkbox" name="data_consent" value="1" @checked(old('data_consent') || $patient->data_consent_at) required>
        <span>La paciente autoriza el tratamiento de sus datos personales y de salud (Ley 1581 de 2012).</span></label>
    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ route('patients.show', $patient) }}">Cancelar</a>
        <button class="btn pri">Guardar y elegir el día</button>
    </div>
</form>
@endsection
