@extends('layouts.app')
@section('title', 'Nueva cita')

@section('content')
<div class="head">
    <div>
        <h1>Nueva cita</h1>
        <p class="sub">Solo se muestran las horas en que la doctora está libre.</p>
    </div>
</div>

<form method="POST" action="{{ route('appointments.store') }}" class="card stack" id="appt-form" style="max-width:720px">
    @csrf
    <div class="fgrid">
        <label class="field full" for="patient_id"><span>Paciente</span>
            <select id="patient_id" name="patient_id" class="input" required>
                <option value="">Elige un paciente</option>
                @foreach ($patients as $p)
                    <option value="{{ $p->id }}" @selected(old('patient_id', $selected['patient_id']) == $p->id)>{{ $p->name }} · {{ $p->phoneLabel() }}{{ $p->isComplete() ? '' : ' · faltan datos' }}</option>
                @endforeach
            </select>
        </label>
        <label class="field full" for="service_id"><span>Servicio</span>
            <select id="service_id" name="service_id" class="input" required>
                @foreach ($services as $s)
                    <option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>{{ $s->name }} · {{ $s->duration_min }} min</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="date"><span>Fecha</span>
            <input id="date" name="date" type="date" class="input" value="{{ old('date', $selected['date']) }}" required>
        </label>
        <label class="field" for="sede_id"><span>Sede</span>
            <select id="sede_id" name="sede_id" class="input" required>
                @foreach ($sedes as $s)
                    <option value="{{ $s->id }}" @selected(old('sede_id', $selected['sede_id']) == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field full" for="start_time"><span>Hora</span>
            <select id="start_time" name="start_time" class="input" data-selected="{{ old('start_time', $selected['time']) }}">
                <option value="">Cargando horas libres…</option>
            </select>
        </label>
        <label class="field full" for="notes"><span>Nota para la cita (opcional)</span>
            <input id="notes" name="notes" class="input" value="{{ old('notes') }}" placeholder="Ej: trae radiografía panorámica" maxlength="255">
        </label>
    </div>
    <p id="sede-hint" class="infobar small" hidden></p>
    <p class="small muted">Si dice “faltan datos”, al agendar te pedirá el documento y la autorización. ¿No está creada? <a href="{{ route('patients.create') }}">Crear paciente</a></p>
    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ url()->previous() }}">Cancelar</a>
        <button class="btn pri" id="appt-submit">Agendar</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var date = document.getElementById('date'), service = document.getElementById('service_id'),
        time = document.getElementById('start_time'), sede = document.getElementById('sede_id'),
        hint = document.getElementById('sede-hint'), submit = document.getElementById('appt-submit'),
        wanted = time.dataset.selected, firstLoad = true;
    var sedeNames = {};
    Array.prototype.forEach.call(sede.options, function (o) { sedeNames[o.value] = o.textContent; });

    function load() {
        if (!date.value || !service.value) return;
        time.innerHTML = '<option value="">Cargando horas libres…</option>';
        fetch('{{ route('appointments.slots') }}?fecha=' + encodeURIComponent(date.value) + '&servicio=' + encodeURIComponent(service.value), {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            time.innerHTML = '';
            if (!data.slots.length) {
                time.innerHTML = '<option value="">No hay espacio ese día</option>';
                submit.disabled = true;
            } else {
                submit.disabled = false;
                data.slots.forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = s.value; o.textContent = s.label;
                    if (s.value === wanted) o.selected = true;
                    time.appendChild(o);
                });
            }
            if (data.doctor_sede_id && !firstLoad) sede.value = String(data.doctor_sede_id);
            firstLoad = false;
            showHint(data.doctor_sede_id);
        }).catch(function () {
            time.innerHTML = '<option value="">No se pudieron cargar las horas. Recarga la página.</option>';
        });
    }
    function showHint(docSede) {
        if (docSede && String(docSede) !== sede.value) {
            hint.textContent = 'Ese día la doctora atiende en ' + sedeNames[docSede] + '.';
            hint.hidden = false;
        } else { hint.hidden = true; }
        sede.onchange = function () { showHint(docSede); };
    }
    date.addEventListener('change', load);
    service.addEventListener('change', load);
    load();
})();
</script>
@endpush
