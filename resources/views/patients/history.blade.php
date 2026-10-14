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

<form method="POST" action="{{ route('patients.history', $patient) }}" id="history-form">
@csrf @method('PUT')
<div class="grid2">
    {{-- Antecedentes --}}
    <div class="card stack">
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

    </div>

    <div class="stack">
        {{-- Diagnósticos --}}
        <div class="card stack">
            <h2>Diagnósticos (CIE-10)</h2>
            <div class="list" id="dx-list">
                @forelse ($patient->diagnoses as $d)
                    <div class="li">
                        <span class="mono">{{ $d->code ?: '—' }}</span>
                        <span class="grow">{{ $d->name }}{{ $d->tooth ? ' · diente '.$d->tooth : '' }}<br><span class="small muted">{{ $d->created_at->translatedFormat('j M Y') }}</span></span>
                        @if ($isDoc)
                            <button type="button" class="btn sm ghost danger" data-dx-remove="{{ route('patients.diagnoses.destroy', [$patient, $d]) }}">Descartar</button>
                        @endif
                    </div>
                @empty
                    <p class="muted" id="dx-empty">Sin diagnósticos.</p>
                @endforelse
            </div>
            @if ($isDoc)
                <div class="stack-sm" id="dx-form" data-url="{{ route('patients.diagnoses.store', $patient) }}">
                    <label class="field" for="dx-search"><span>Buscar: escribe una palabra o el código</span>
                        <input id="dx-search" class="input" list="cie10" placeholder="Ej: caries, gingivitis, K02" autocomplete="off" data-nodraft>
                    </label>
                    <datalist id="cie10">
                        @foreach (Clinical::CIE10 as $code => $name)
                            <option value="{{ $code }} · {{ $name }}"></option>
                        @endforeach
                    </datalist>
                    <div class="row">
                        <label class="field" for="dx-tooth" style="flex:1"><span>Diente (opcional)</span><input id="dx-tooth" class="input" maxlength="20" placeholder="Ej: 36" data-nodraft></label>
                        <button type="button" class="btn" id="dx-add" style="align-self:flex-end">Agregar</button>
                    </div>
                    <p class="small muted" id="dx-msg">Si no está en la lista, escríbelo tal cual y se guarda sin código. Se agrega al instante, sin perder lo que escribiste en la historia.</p>
                </div>
            @endif
        </div>

        {{-- Examen clínico --}}
        <div class="card stack">
            <h2>Examen clínico</h2>
            @foreach (Clinical::EXAM_FIELDS as $key => $label)
                <label class="field" for="ex-{{ $key }}"><span>{{ $label }}</span>
                    <input id="ex-{{ $key }}" @if ($isDoc) name="exam[{{ $key }}]" @endif class="input" value="{{ old('exam.'.$key, $exam[$key] ?? '') }}" maxlength="1000" @disabled(! $isDoc)>
                </label>
            @endforeach
            @unless ($isDoc)
                <p class="small muted">Solo la doctora edita el examen.</p>
            @endunless
        </div>
    </div>
</div>

<div class="savebar" id="savebar">
    <span class="small" id="save-state">Todo guardado.</span>
    <span class="sp"></span>
    <button class="btn pri">Guardar historia clínica</button>
</div>
</form>
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

    // ---- Diagnósticos: se agregan y descartan sin recargar ----
    var token = document.querySelector('#history-form input[name=_token]').value;
    function send(url, method, body) {
        return fetch(url, {
            method: method,
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
            body: body ? JSON.stringify(body) : null,
        }).then(function (r) { if (!r.ok) throw r; return r.json(); });
    }
    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }
    var list = document.getElementById('dx-list');
    if (list) {
        list.addEventListener('click', function (e) {
            var b = e.target.closest('[data-dx-remove]');
            if (!b) return;
            b.disabled = true;
            send(b.getAttribute('data-dx-remove'), 'DELETE').then(function () {
                b.closest('.li').remove();
                if (!list.querySelector('.li')) list.innerHTML = '<p class="muted" id="dx-empty">Sin diagnósticos.</p>';
            }).catch(function () { b.disabled = false; alertMsg('No se pudo descartar. Revisa la conexión.'); });
        });
    }
    var dx = document.getElementById('dx-form'), msg = document.getElementById('dx-msg');
    function alertMsg(t) { if (msg) { msg.textContent = t; msg.style.color = 'var(--danger)'; } }
    function addDx() {
        var search = document.getElementById('dx-search'), tooth = document.getElementById('dx-tooth');
        var v = search.value.trim(), m = v.match(/^([A-Z]\d{2}(?:\.\d)?)\s*·\s*(.+)$/);
        if (!v) { search.focus(); return; }
        var btn = document.getElementById('dx-add'); btn.disabled = true;
        send(dx.getAttribute('data-url'), 'POST', {code: m ? m[1] : '', name: m ? m[2] : v, tooth: tooth.value.trim()})
            .then(function (d) {
                var empty = document.getElementById('dx-empty'); if (empty) empty.remove();
                var row = document.createElement('div'); row.className = 'li';
                row.innerHTML = '<span class="mono">' + esc(d.code || '—') + '</span>'
                    + '<span class="grow">' + esc(d.name) + (d.tooth ? ' · diente ' + esc(d.tooth) : '') + '<br><span class="small muted">' + esc(d.date) + '</span></span>'
                    + '<button type="button" class="btn sm ghost danger" data-dx-remove="' + esc(d.remove) + '">Descartar</button>';
                list.appendChild(row);
                search.value = ''; tooth.value = '';
                msg.style.color = ''; msg.textContent = 'Diagnóstico agregado.';
            })
            .catch(function () { alertMsg('No se pudo agregar. Revisa la conexión e intenta otra vez.'); })
            .finally(function () { btn.disabled = false; });
    }
    if (dx) {
        document.getElementById('dx-add').addEventListener('click', addDx);
        dx.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addDx(); } });
    }

    // ---- Borrador automático y aviso de cambios sin guardar ----
    var form = document.getElementById('history-form'), state = document.getElementById('save-state'),
        bar = document.getElementById('savebar'),
        key = 'hc-draft-{{ auth()->user()->clinic_id }}-{{ $patient->id }}', dirty = false, submitting = false;
    function store(fn) { try { return fn(); } catch (e) { return null; } }
    function fields() { return Array.prototype.filter.call(form.elements, function (el) { return el.name && el.name !== '_token' && el.name !== '_method' && !el.hasAttribute('data-nodraft'); }); }
    function snapshot() {
        var d = {};
        fields().forEach(function (el, i) {
            var k = el.name + '#' + (el.type === 'radio' || el.type === 'checkbox' ? el.value : '');
            d[k] = (el.type === 'radio' || el.type === 'checkbox') ? el.checked : el.value;
        });
        return d;
    }
    function apply(d) {
        fields().forEach(function (el) {
            var k = el.name + '#' + (el.type === 'radio' || el.type === 'checkbox' ? el.value : '');
            if (!(k in d)) return;
            if (el.type === 'radio' || el.type === 'checkbox') el.checked = d[k]; else el.value = d[k];
        });
        radios.forEach(function (r) { if (r.checked) r.dispatchEvent(new Event('change')); });
    }
    function markDirty() {
        dirty = true;
        bar.classList.add('dirty');
        state.textContent = 'Hay cambios sin guardar (quedan en borrador en este equipo).';
        store(function () { localStorage.setItem(key, JSON.stringify(snapshot())); });
    }
    @if (session('clear_draft'))
        store(function () { localStorage.removeItem(key); });
    @else
        var draft = store(function () { return JSON.parse(localStorage.getItem(key) || 'null'); });
        if (draft) { apply(draft); markDirty(); state.textContent = 'Recuperamos lo que no alcanzaste a guardar. Revísalo y guarda.'; }
    @endif
    form.addEventListener('input', function (e) { if (!e.target.hasAttribute('data-nodraft')) markDirty(); });
    form.addEventListener('change', function (e) { if (!e.target.hasAttribute('data-nodraft')) markDirty(); });
    form.addEventListener('submit', function () { submitting = true; });
    window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
