@extends('layouts.app')
@section('title', 'Firmar consentimiento')

@section('content')
<div class="head">
    <div>
        <a class="small muted" href="{{ route('patients.consents', $patient) }}">← {{ $patient->name }}</a>
        <h1 style="margin-top:6px">Firmar consentimiento</h1>
        <p class="sub">{{ $patient->name }} · {{ $patient->documentLabel() }}</p>
    </div>
</div>

@if ($templates->isEmpty())
    <div class="card empty">No hay plantillas de consentimiento activas. La doctora las crea en Configuración.</div>
@else
    <div class="row" role="group" aria-label="Plantillas">
        @foreach ($templates as $t)
            <a class="btn sm {{ $selected->id === $t->id ? 'pri' : '' }}" href="{{ route('consents.create', [$patient, 'plantilla' => $t->id]) }}">{{ $t->name }}</a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('consents.store', $patient) }}" class="stack" id="consent-form">
        @csrf
        <input type="hidden" name="template_id" value="{{ $selected->id }}">
        <input type="hidden" name="patient_signature" id="sig-patient-data">
        <input type="hidden" name="doctor_signature" id="sig-doctor-data">

        <div class="card">
            <h2 style="margin-bottom:12px">{{ $selected->name }}</h2>
            <div class="consent-text">{{ $text }}</div>
        </div>

        <div class="grid2">
            <div class="card sig">
                <div class="card-h"><span class="label">Firma de la paciente</span><button type="button" class="btn sm ghost" data-clear="sig-patient">Borrar firma</button></div>
                <canvas id="sig-patient" aria-label="Recuadro para la firma de la paciente"></canvas>
                <span class="small muted">{{ $patient->name }}</span>
            </div>
            <div class="card sig">
                <div class="card-h"><span class="label">Firma de la doctora</span><button type="button" class="btn sm ghost" data-clear="sig-doctor">Borrar firma</button></div>
                <canvas id="sig-doctor" aria-label="Recuadro para la firma de la doctora"></canvas>
                <span class="small muted">{{ $doctor }}</span>
            </div>
        </div>
        <p class="small muted">Firmen con el dedo, un lápiz táctil o el mouse dentro de cada recuadro.</p>
        <div class="err" id="sig-err" hidden></div>
        <div class="row" style="justify-content:flex-end">
            <a class="btn" href="{{ route('patients.consents', $patient) }}">Cancelar</a>
            <button class="btn pri">Guardar consentimiento firmado</button>
        </div>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var pads = {};
    function setup(id) {
        var cv = document.getElementById(id); if (!cv) return;
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
        // Reduce la firma a 500 px de ancho máximo para que pese poco.
        var src = pad.cv, scale = Math.min(1, 500 / src.width), out = document.createElement('canvas');
        out.width = Math.round(src.width * scale); out.height = Math.round(src.height * scale);
        out.getContext('2d').drawImage(src, 0, 0, out.width, out.height);
        return out.toDataURL('image/png');
    }
    setup('sig-patient'); setup('sig-doctor');
    document.querySelectorAll('[data-clear]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pad = pads[b.dataset.clear], c = pad.cv.getContext('2d');
            c.save(); c.setTransform(1, 0, 0, 1, 0, 0); c.clearRect(0, 0, pad.cv.width, pad.cv.height); c.restore(); pad.drawn = false;
        });
    });
    var form = document.getElementById('consent-form');
    if (form) form.addEventListener('submit', function (e) {
        var p = pads['sig-patient'], d = pads['sig-doctor'], err = document.getElementById('sig-err');
        if (!p.drawn || !d.drawn) {
            e.preventDefault();
            err.textContent = 'Faltan ' + (!p.drawn && !d.drawn ? 'las dos firmas' : !p.drawn ? 'la firma de la paciente' : 'la firma de la doctora') + '.';
            err.hidden = false; return;
        }
        document.getElementById('sig-patient-data').value = data(p);
        document.getElementById('sig-doctor-data').value = data(d);
    });
})();
</script>
@endpush
