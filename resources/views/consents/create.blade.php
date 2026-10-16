@extends('layouts.app')
@section('title', 'Firmar consentimiento')

@section('content')
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.consents', $patient) }}">← {{ $patient->name }}</a>
        <h1 style="margin-top:6px">{{ $selected ? $selected->name : 'Firmar consentimiento' }}</h1>
        <p class="sub">{{ $patient->name }} · {{ $patient->documentLabel() }}{{ $patient->age() !== null ? ' · '.$patient->age().' años' : '' }}</p>
    </div>
    @if ($selected)<a class="btn" href="{{ route('consents.create', $patient) }}">Cambiar consentimiento</a>@endif
</div>

@if ($templates->isEmpty())
    <div class="card empty">No hay consentimientos activos. La doctora los activa en Configuración.</div>
@elseif (! $selected)
    {{-- Paso 1: elegir el consentimiento --}}
    <div class="consent-pick">
        @foreach ($templates as $t)
            <a class="card" href="{{ route('consents.create', [$patient, 'plantilla' => $t->id]) }}">
                <strong>{{ $t->name }}</strong>
                <span class="small muted">{{ $t->form ? 'Con campos para llenar' : 'Texto libre' }}</span>
            </a>
        @endforeach
    </div>
@else
    @php
        $f = old('f', []);
        $repRequired = ! empty($form['minor']) || $minor;
        $hasRep = $repRequired || old('has_rep');
    @endphp
    <form method="POST" action="{{ route('consents.store', $patient) }}" class="stack" id="consent-form">
        @csrf
        <input type="hidden" name="template_id" value="{{ $selected->id }}">
        <input type="hidden" name="patient_signature" id="sig-patient-data">
        <input type="hidden" name="doctor_signature" id="sig-doctor-data">

        {{-- 1. Datos --}}
        <section class="card stack">
            <h2>1. Datos</h2>
            <div class="fgrid">
                <div class="field"><span>Paciente</span><div class="input ro">{{ $patient->name }}</div></div>
                <div class="field"><span>Documento</span><div class="input ro">{{ $patient->documentLabel() }}</div></div>
                <label class="field" for="f-hc"><span>Historia clínica</span>
                    <input id="f-hc" name="f[hc]" class="input" value="{{ $f['hc'] ?? $defaults['hc'] }}" maxlength="60"></label>
                <label class="field" for="f-fecha"><span>Fecha y lugar</span>
                    <input id="f-fecha" name="f[fecha_lugar]" class="input" value="{{ $f['fecha_lugar'] ?? $defaults['fecha_lugar'] }}" required maxlength="120"></label>
                <div class="field"><span>Profesional tratante</span><div class="input ro">{{ $doctor }}</div></div>
                <label class="field" for="f-registro"><span>Registro profesional</span>
                    <input id="f-registro" name="f[registro]" class="input" value="{{ $f['registro'] ?? $defaults['registro'] }}" maxlength="60" placeholder="Agrégalo en Configuración para que salga siempre"></label>
            </div>

            @foreach ($form['fields'] ?? [] as $field)
                @php $k = $field['key']; $val = $f[$k] ?? null; $req = ! empty($field['required']); @endphp
                @if (in_array($field['type'], ['text', 'number', 'textarea'], true))
                    <label class="field" for="f-{{ $k }}"><span>{{ $field['label'] }}{!! $req ? '' : ' <span class="muted">(opcional)</span>' !!}</span>
                        @if ($field['type'] === 'textarea')
                            <textarea id="f-{{ $k }}" name="f[{{ $k }}]" class="input" style="min-height:70px" maxlength="1000" @required($req) placeholder="{{ $field['placeholder'] ?? '' }}">{{ $val }}</textarea>
                        @else
                            <input id="f-{{ $k }}" name="f[{{ $k }}]" class="input" value="{{ $val }}" @required($req) placeholder="{{ $field['placeholder'] ?? '' }}"
                                @if ($field['type'] === 'number') type="number" min="1" max="40" style="max-width:140px" @else maxlength="200" @endif>
                        @endif
                    </label>
                @else
                    <fieldset class="choice">
                        <legend>{{ $field['label'] }}{!! $req ? '' : ' <span class="muted">(opcional)</span>' !!}</legend>
                        <div class="opts">
                            @foreach ($field['options'] as $opt)
                                @if ($field['type'] === 'checks')
                                    <label class="opt"><input type="checkbox" name="f[{{ $k }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) $val, true))><span>{{ $opt }}</span></label>
                                @else
                                    <label class="opt"><input type="radio" name="f[{{ $k }}]" value="{{ $opt }}" @checked($val === $opt) @required($req)><span>{{ $opt }}</span></label>
                                @endif
                            @endforeach
                            @if (! empty($field['other']))
                                <label class="opt"><input type="radio" name="f[{{ $k }}]" value="__otro" @checked($val === '__otro') data-other="{{ $k }}"><span>Otro</span></label>
                                <input name="otro[{{ $k }}]" class="input" value="{{ old('otro.'.$k) }}" maxlength="100" placeholder="¿Cuál?" aria-label="Otro: {{ $field['label'] }}" data-other-input="{{ $k }}" style="max-width:240px">
                            @endif
                        </div>
                    </fieldset>
                @endif
            @endforeach

            {{-- Representante --}}
            <div class="rep stack-sm">
                @if ($repRequired)
                    <div class="label">{{ ! empty($form['minor']) ? 'Madre, padre o representante (firma por el menor)' : 'Representante (el paciente es menor de edad)' }}</div>
                @else
                    <label class="check"><input type="checkbox" name="has_rep" value="1" id="has-rep" @checked($hasRep)><span>Firma un representante en lugar del paciente</span></label>
                @endif
                <div class="fgrid" id="rep-fields" @unless ($hasRep) hidden @endunless>
                    <label class="field" for="rep-nombre"><span>Nombre del representante</span><input id="rep-nombre" name="rep[nombre]" class="input" value="{{ old('rep.nombre') }}" maxlength="150" @required($repRequired)></label>
                    <label class="field" for="rep-doc"><span>Documento</span><input id="rep-doc" name="rep[documento]" class="input" value="{{ old('rep.documento') }}" maxlength="60" placeholder="Ej: C.C. 43.123.456" @required($repRequired)></label>
                    <label class="field" for="rep-vinculo"><span>Vínculo</span>
                        <input id="rep-vinculo" name="rep[vinculo]" class="input" list="vinculos" value="{{ old('rep.vinculo') }}" maxlength="60" @required($repRequired)>
                        <datalist id="vinculos"><option value="Madre"><option value="Padre"><option value="Representante legal"><option value="Acudiente"></datalist>
                    </label>
                </div>
            </div>
        </section>

        {{-- 2. Texto --}}
        <section class="card stack">
            <div class="card-h"><h2>2. Lo que se le explica</h2><span class="small muted">Léanlo juntos antes de firmar</span></div>
            @if (! empty($form['subtitle']))<p class="small muted">{{ $form['subtitle'] }}</p>@endif
            <div class="consent-doc">
                @foreach ($blocks as [$type, $content])
                    @if ($type === 'h')<h3>{{ $content }}</h3>
                    @elseif ($type === 'ul')<ul>@foreach ($content as $li)<li>{{ $li }}</li>@endforeach</ul>
                    @elseif ($type === 'p')<p>{{ $content }}</p>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- 3. Decisión y firmas --}}
        <section class="card stack">
            <h2>3. Decisión y firmas</h2>
            @if ($form)
                <fieldset class="choice decision">
                    <legend class="sr-only">Decisión</legend>
                    <div class="opts">
                        <label class="opt big ok"><input type="radio" name="decision" value="1" @checked(old('decision') === '1') required><span>{{ $form['decision'][0] }}</span></label>
                        <label class="opt big no"><input type="radio" name="decision" value="0" @checked(old('decision') === '0')><span>{{ $form['decision'][1] }}</span></label>
                    </div>
                </fieldset>
            @endif

            @if (! empty($form['assent']))
                <fieldset class="choice">
                    <legend>Asentimiento del niño, niña o adolescente: “Después de que me explicaron qué van a hacer en mis dientes…”</legend>
                    <div class="opts">
                        @foreach ($form['assent'] as $a)
                            <label class="opt"><input type="radio" name="assent" value="{{ $a }}" @checked(old('assent') === $a)><span>{{ $a }}</span></label>
                        @endforeach
                    </div>
                    <label class="field" for="menor" style="max-width:420px;margin-top:8px"><span>Nombre del menor</span><input id="menor" name="menor" class="input" value="{{ old('menor', $defaults['menor']) }}" maxlength="150"></label>
                </fieldset>
            @endif

            <div class="grid2">
                <div class="sig stack-sm">
                    <div class="card-h"><span class="label" id="sig-patient-label">{{ $hasRep ? 'Firma del representante' : 'Firma del paciente' }}</span><button type="button" class="btn sm ghost" data-clear="sig-patient">Borrar firma</button></div>
                    <canvas id="sig-patient" aria-label="Recuadro para la firma del paciente o su representante"></canvas>
                    <span class="small muted" id="sig-patient-name">{{ $hasRep ? 'Representante de '.$patient->name : $patient->name }}</span>
                </div>
                <div class="sig stack-sm">
                    <div class="card-h"><span class="label">Firma de la doctora</span>
                        <button type="button" class="btn sm ghost" data-clear="sig-doctor" id="sig-doctor-clear" @if ($savedSignature) hidden @endif>Borrar firma</button></div>
                    @if ($savedSignature)
                        <div class="saved-sig" id="saved-sig"><img src="{{ $savedSignature }}" alt="Firma guardada de la doctora"></div>
                        <label class="check small"><input type="checkbox" name="doctor_draws" value="1" id="doctor-draws"><span>Firmar a mano en su lugar</span></label>
                    @endif
                    <canvas id="sig-doctor" aria-label="Recuadro para la firma de la doctora" @if ($savedSignature) hidden @endif></canvas>
                    <span class="small muted">{{ $doctor }}{{ ($defaults['registro'] ?? '') ? ' · Registro '.$defaults['registro'] : '' }}</span>
                </div>
            </div>

            @if (! empty($form['witness']))
                <details>
                    <summary class="small" style="cursor:pointer;font-weight:700">Testigo (si interviene)</summary>
                    <div class="fgrid" style="margin-top:8px">
                        <label class="field" for="t-nombre"><span>Nombre</span><input id="t-nombre" name="testigo[nombre]" class="input" value="{{ old('testigo.nombre') }}" maxlength="150"></label>
                        <label class="field" for="t-doc"><span>Documento</span><input id="t-doc" name="testigo[documento]" class="input" value="{{ old('testigo.documento') }}" maxlength="60"></label>
                    </div>
                </details>
            @endif

            <p class="small muted">Firmen con el dedo, un lápiz táctil o el mouse dentro de cada recuadro.</p>
            <div class="err" id="sig-err" hidden></div>
            <div class="row" style="justify-content:flex-end">
                <a class="btn" href="{{ route('patients.consents', $patient) }}">Cancelar</a>
                <button class="btn pri">Guardar consentimiento firmado</button>
            </div>
        </section>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var pads = {};
    function setup(id) {
        var cv = document.getElementById(id); if (!cv || cv.hidden || pads[id]) return;
        var r = cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
        cv.width = r.width * dpr; cv.height = r.height * dpr;
        var ctx = cv.getContext('2d'); ctx.scale(dpr, dpr);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#14232A';
        var drawing = false, last = null, pad = { cv: cv, drawn: false };
        var pt = function (e) { var b = cv.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
        cv.addEventListener('pointerdown', function (e) { drawing = true; last = pt(e); try { cv.setPointerCapture(e.pointerId); } catch (_) {} });
        cv.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            var p = pt(e); ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke(); last = p; pad.drawn = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) { cv.addEventListener(ev, function () { drawing = false; }); });
        pads[id] = pad;
    }
    function data(pad) {
        var src = pad.cv, scale = Math.min(1, 500 / src.width), out = document.createElement('canvas');
        out.width = Math.round(src.width * scale); out.height = Math.round(src.height * scale);
        out.getContext('2d').drawImage(src, 0, 0, out.width, out.height);
        return out.toDataURL('image/png');
    }
    setup('sig-patient'); setup('sig-doctor');
    document.querySelectorAll('[data-clear]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pad = pads[b.dataset.clear]; if (!pad) return;
            var c = pad.cv.getContext('2d');
            c.save(); c.setTransform(1, 0, 0, 1, 0, 0); c.clearRect(0, 0, pad.cv.width, pad.cv.height); c.restore(); pad.drawn = false;
        });
    });

    // Firma guardada de la doctora o a mano.
    var draws = document.getElementById('doctor-draws');
    if (draws) draws.addEventListener('change', function () {
        document.getElementById('sig-doctor').hidden = !draws.checked;
        document.getElementById('saved-sig').hidden = draws.checked;
        document.getElementById('sig-doctor-clear').hidden = !draws.checked;
        if (draws.checked) setup('sig-doctor');
    });

    // Representante.
    var hasRep = document.getElementById('has-rep');
    if (hasRep) hasRep.addEventListener('change', function () {
        document.getElementById('rep-fields').hidden = !hasRep.checked;
        document.getElementById('sig-patient-label').textContent = hasRep.checked ? 'Firma del representante' : 'Firma del paciente';
        ['rep-nombre', 'rep-doc', 'rep-vinculo'].forEach(function (id) { document.getElementById(id).required = hasRep.checked; });
    });

    // "Otro": escribir cuál.
    document.querySelectorAll('[data-other-input]').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var r = document.querySelector('[data-other="' + inp.dataset.otherInput + '"]'); if (r && inp.value) r.checked = true;
        });
    });

    var form = document.getElementById('consent-form');
    if (form) form.addEventListener('submit', function (e) {
        var p = pads['sig-patient'], d = pads['sig-doctor'], err = document.getElementById('sig-err');
        var needDoctor = !document.getElementById('saved-sig') || (draws && draws.checked);
        var missing = [];
        if (!p || !p.drawn) missing.push(hasRep && hasRep.checked ? 'la firma del representante' : 'la firma del paciente');
        if (needDoctor && (!d || !d.drawn)) missing.push('la firma de la doctora');
        if (missing.length) {
            e.preventDefault(); err.textContent = 'Falta ' + missing.join(' y ') + '.'; err.hidden = false;
            err.scrollIntoView({ behavior: 'smooth', block: 'center' }); return;
        }
        document.getElementById('sig-patient-data').value = data(p);
        if (needDoctor) document.getElementById('sig-doctor-data').value = data(d);
    });
})();
</script>
@endpush
