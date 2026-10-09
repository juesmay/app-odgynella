@extends('layouts.app')
@section('title', 'Historia clínica · '.$patient->name)
@use('App\Support\Clinical')

@section('content')
@include('patients._header')
@php
    $isDoc = auth()->user()->isDoctor();
    $answers = $patient->anamnesis ?? [];
    $infectious = $patient->infectious ?? [];
    $infOther = implode(', ', array_diff($infectious, Clinical::INFECTIOUS));
    $onco = $patient->oncology ?? ['status' => 'no'];
    $family = $patient->family_history ?? [];
    $exam = $patient->exam ?? [];
@endphp

<div class="grid2">
    {{-- Antecedentes --}}
    <form method="POST" action="{{ route('patients.history', $patient) }}" class="card stack" id="history-form">
        @csrf @method('PUT')
        <div class="card-h"><h2>Antecedentes médicos</h2><span class="small muted">Lo que sea “Sí” aparece en rojo arriba</span></div>

        <div>
            @foreach (\App\Models\Patient::ANAMNESIS as $key => [$question])
                <fieldset class="yn" style="border:0;margin:0;padding:10px 0">
                    <legend style="float:left;padding:0">{{ $question }}</legend>
                    <span style="flex:1"></span>
                    <label class="check" style="align-items:center"><input type="radio" name="anamnesis[{{ $key }}]" value="1" @checked(! empty($answers[$key]))><span>Sí</span></label>
                    <label class="check" style="align-items:center"><input type="radio" name="anamnesis[{{ $key }}]" value="0" @checked(empty($answers[$key]))><span>No</span></label>
                </fieldset>
            @endforeach
        </div>
        <label class="field" for="alert_text"><span>Detalle importante (alergia, medicamento…)</span>
            <input id="alert_text" name="alert_text" class="input" value="{{ old('alert_text', $patient->alert_text) }}" placeholder="Ej: alergia a la penicilina" maxlength="255">
        </label>

        <hr class="sep">
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0">
            <legend class="label" style="margin-bottom:8px">Enfermedades infecciosas</legend>
            @if ($isDoc)
                <div class="row">
                    @foreach (Clinical::INFECTIOUS as $d)
                        <label class="check" style="align-items:center"><input type="checkbox" name="infectious[]" value="{{ $d }}" @checked(in_array($d, $infectious, true))><span>{{ $d }}</span></label>
                    @endforeach
                </div>
                <label class="field" for="infectious_other"><span>¿Otra? ¿Cuál?</span>
                    <input id="infectious_other" name="infectious_other" class="input" value="{{ old('infectious_other', $infOther) }}" maxlength="120">
                </label>
                <p class="small muted">Dato reservado: la asistente solo ve “Enfermedad infecciosa: ver antecedentes”.</p>
            @else
                <p class="muted">{{ $infectious ? 'Tiene un antecedente registrado. Solo la doctora puede verlo.' : 'Solo la doctora registra y ve este dato.' }}</p>
            @endif
        </fieldset>

        <hr class="sep">
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0">
            <legend class="label" style="margin-bottom:8px">¿Es o fue paciente oncológico?</legend>
            @foreach (Clinical::ONCOLOGY_STATUS as $val => $label)
                <label class="check" style="align-items:center"><input type="radio" name="oncology_status" value="{{ $val }}" data-onco @checked(($onco['status'] ?? 'no') === $val)><span>{{ $label }}</span></label>
            @endforeach
            <div class="stack-sm" id="onco-detail" style="margin-top:8px" @if (($onco['status'] ?? 'no') === 'no') hidden @endif>
                <label class="field" for="oncology_ended_on" id="onco-ended" @if (($onco['status'] ?? '') !== 'previo') hidden @endif><span>¿Cuándo terminó el tratamiento?</span>
                    <input id="oncology_ended_on" name="oncology_ended_on" type="date" class="input" value="{{ old('oncology_ended_on', $onco['ended_on'] ?? '') }}" style="max-width:220px">
                </label>
                <span class="small" style="font-weight:700;color:var(--ink-2)">¿Qué tratamiento recibió?</span>
                @foreach (Clinical::ONCOLOGY_TREATMENTS as $val => $label)
                    <label class="check"><input type="checkbox" name="oncology_treatments[]" value="{{ $val }}" @checked(in_array($val, $onco['treatments'] ?? [], true))><span>{{ $label }}</span></label>
                @endforeach
                <p class="small muted">La radioterapia en cabeza o cuello y los bifosfonatos cambian el riesgo de extracciones e implantes.</p>
            </div>
        </fieldset>

        <hr class="sep">
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0">
            <legend class="label" style="margin-bottom:8px">Antecedentes familiares</legend>
            <div class="row">
                @foreach (Clinical::FAMILY_CONDITIONS as $c)
                    <label class="check" style="align-items:center"><input type="checkbox" name="family_conditions[]" value="{{ $c }}" @checked(in_array($c, $family['conditions'] ?? [], true))><span>{{ $c }}</span></label>
                @endforeach
            </div>
            <label class="field" for="family_detail"><span>¿Cuáles? (quién y qué enfermedad)</span>
                <textarea id="family_detail" name="family_detail" class="input" style="min-height:70px" placeholder="Ej: madre con diabetes, abuelo con cáncer de próstata">{{ old('family_detail', $family['detail'] ?? '') }}</textarea>
            </label>
        </fieldset>

        <label class="field" for="hereditary_history"><span>Antecedentes hereditarios (enfermedades heredadas por la paciente)</span>
            <textarea id="hereditary_history" name="hereditary_history" class="input" style="min-height:70px" placeholder="Ej: hemofilia, defectos del esmalte (amelogénesis imperfecta)">{{ old('hereditary_history', $patient->hereditary_history) }}</textarea>
        </label>

        <button class="btn pri">Guardar antecedentes</button>
    </form>

    <div class="stack">
        {{-- Diagnósticos --}}
        <div class="card stack">
            <h2>Diagnósticos (CIE-10)</h2>
            <div class="list">
                @forelse ($patient->diagnoses as $d)
                    <div class="li">
                        <span class="mono">{{ $d->code ?: '—' }}</span>
                        <span class="grow">{{ $d->name }}{{ $d->tooth ? ' · diente '.$d->tooth : '' }}<br><span class="small muted">{{ $d->created_at->translatedFormat('j M Y') }}</span></span>
                        @if ($isDoc)
                            <form method="POST" action="{{ route('patients.diagnoses.destroy', [$patient, $d]) }}">@csrf @method('DELETE')<button class="btn sm ghost danger">Descartar</button></form>
                        @endif
                    </div>
                @empty
                    <p class="muted">Sin diagnósticos.</p>
                @endforelse
            </div>
            @if ($isDoc)
                <form method="POST" action="{{ route('patients.diagnoses.store', $patient) }}" class="stack-sm" id="dx-form">
                    @csrf
                    <label class="field" for="dx-search"><span>Buscar: escribe una palabra o el código</span>
                        <input id="dx-search" class="input" list="cie10" placeholder="Ej: caries, gingivitis, K02" autocomplete="off">
                    </label>
                    <datalist id="cie10">
                        @foreach (Clinical::CIE10 as $code => $name)
                            <option value="{{ $code }} · {{ $name }}"></option>
                        @endforeach
                    </datalist>
                    <input type="hidden" name="code" id="dx-code">
                    <input type="hidden" name="name" id="dx-name">
                    <div class="row">
                        <label class="field" for="dx-tooth" style="flex:1"><span>Diente (opcional)</span><input id="dx-tooth" name="tooth" class="input" maxlength="20" placeholder="Ej: 36"></label>
                        <button class="btn" style="align-self:flex-end">Agregar</button>
                    </div>
                    <p class="small muted">Si no está en la lista, escríbelo tal cual y se guarda sin código.</p>
                </form>
            @endif
        </div>

        {{-- Examen clínico --}}
        <form method="POST" action="{{ route('patients.exam', $patient) }}" class="card stack">
            @csrf @method('PUT')
            <h2>Examen clínico</h2>
            @foreach (Clinical::EXAM_FIELDS as $key => $label)
                <label class="field" for="ex-{{ $key }}"><span>{{ $label }}</span>
                    <input id="ex-{{ $key }}" name="{{ $key }}" class="input" value="{{ old($key, $exam[$key] ?? '') }}" maxlength="1000" @disabled(! $isDoc)>
                </label>
            @endforeach
            @if ($isDoc)
                <button class="btn pri" style="align-self:flex-start">Guardar examen</button>
            @else
                <p class="small muted">Solo la doctora edita el examen.</p>
            @endif
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var radios = document.querySelectorAll('[data-onco]'), detail = document.getElementById('onco-detail'), ended = document.getElementById('onco-ended');
    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            detail.hidden = r.value === 'no';
            ended.hidden = r.value !== 'previo';
        });
    });
    var form = document.getElementById('dx-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var v = document.getElementById('dx-search').value.trim(), m = v.match(/^([A-Z]\d{2}(?:\.\d)?)\s*·\s*(.+)$/);
            document.getElementById('dx-code').value = m ? m[1] : '';
            document.getElementById('dx-name').value = m ? m[2] : v;
            if (!v) { e.preventDefault(); document.getElementById('dx-search').focus(); }
        });
    }
})();
</script>
@endpush
