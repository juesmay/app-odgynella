@extends('layouts.app')
@section('title', 'Evoluciones · '.$patient->name)
@use('App\Support\Clinical')

@section('content')
@include('patients._header')
@php $isDoc = auth()->user()->isDoctor(); $type = old('type', 'valoracion'); @endphp

<div class="grid2">
    <div class="card stack">
        <h2>Nota de hoy</h2>
        @if (! $isDoc)
            <p class="muted">Solo la doctora escribe y firma las notas clínicas.</p>
        @else
            @if ($todayAppointment)
                <p class="small muted">Al firmar, la cita de hoy ({{ $todayAppointment->service->name ?? '' }}, {{ \App\Support\Time::human($todayAppointment->start_time) }}) queda como atendida.</p>
            @endif
            <form method="POST" action="{{ route('patients.evolutions.store', $patient) }}" class="stack" id="evo-form">
                @csrf
                <input type="hidden" name="type" id="evo-type" value="{{ $type }}">
                <div class="typebar" role="group" aria-label="Tipo de nota">
                    @foreach (Clinical::EVOLUTION_TYPES as $key => $t)
                        <button type="button" class="btn sm" data-type="{{ $key }}" aria-pressed="{{ $type === $key ? 'true' : 'false' }}">{{ $t['name'] }}</button>
                    @endforeach
                </div>
                <p class="small muted">Al cambiar de tipo no se pierde lo escrito: los campos que se repiten se conservan y el borrador se guarda solo.</p>

                @foreach (Clinical::EVOLUTION_FIELDS as $key => $label)
                    <div class="field" data-field="{{ $key }}">
                        <label for="f-{{ $key }}" style="font-size:13px;font-weight:700;color:var(--ink-2)">{{ $label }}</label>
                        @if (in_array($key, ['anestesia', 'proxima'], true))
                            <input id="f-{{ $key }}" name="fields[{{ $key }}]" class="input" value="{{ old('fields.'.$key) }}" maxlength="3000">
                        @else
                            <textarea id="f-{{ $key }}" name="fields[{{ $key }}]" class="input" style="min-height:{{ $key === 'diagnostico' ? 70 : 84 }}px" maxlength="3000">{{ old('fields.'.$key) }}</textarea>
                        @endif

                        @if ($key === 'diagnostico')
                            <div class="row" style="margin-top:6px">
                                <input id="dx-search" class="input" list="cie10" placeholder="Buscar CIE-10: caries, K02…" autocomplete="off" style="flex:1;min-width:200px">
                                <button type="button" class="btn sm" id="dx-add">Agregar diagnóstico</button>
                            </div>
                            <datalist id="cie10">
                                @foreach (Clinical::CIE10 as $code => $name)
                                    <option value="{{ $code }} · {{ $name }}"></option>
                                @endforeach
                            </datalist>
                            <div class="dxchips" id="dx-chips"></div>
                            <span class="small muted">Lo que agregues aquí también queda en la lista de diagnósticos de la historia clínica.</span>
                        @endif
                    </div>
                @endforeach

                @if ($pending->isNotEmpty())
                    <fieldset class="stack-sm" data-field="procedures" style="border:1px solid var(--line);border-radius:12px;padding:12px">
                        <legend class="label" style="padding:0 6px">Procedimientos de la cotización realizados hoy</legend>
                        @foreach ($pending as $item)
                            <label class="check"><input type="checkbox" name="procedures[]" value="{{ $item->id }}"><span>{{ $item->description }}{{ $item->teeth ? ' · '.$item->teeth : '' }} <span class="muted small">({{ $item->quote->code() }})</span></span></label>
                        @endforeach
                    </fieldset>
                @endif

                <button class="btn pri block">Guardar y firmar</button>
                <p class="small muted">La nota firmada no se puede editar ni borrar, como exige la historia clínica.</p>
            </form>
        @endif
    </div>

    <div class="card">
        <h2>Historial</h2>
        @forelse ($evolutions as $e)
            <article class="evo">
                <div class="row" style="justify-content:space-between">
                    <strong>{{ $e->typeName() }}</strong>
                    <span class="small muted">{{ $e->signed_at->translatedFormat('j M Y, g:i a') }}</span>
                </div>
                @foreach ($e->lines() as [$label, $text])
                    <p><strong>{{ $label }}:</strong> {{ $text }}</p>
                @endforeach
                @if ($e->procedures)
                    <p class="small" style="color:var(--ok)">Realizado: {{ implode(', ', $e->procedures) }}</p>
                @endif
                <p class="small muted" style="margin-top:6px">Firmada por {{ $e->signed_name }}.</p>
            </article>
        @empty
            <p class="muted" style="margin-top:8px">Primera visita.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('evo-form');
    if (!form) return;
    var types = @json(collect(Clinical::EVOLUTION_TYPES)->map(fn ($t) => $t['fields'])),
        typeInput = document.getElementById('evo-type'),
        key = 'evo-draft-{{ auth()->user()->clinic_id }}-{{ $patient->id }}',
        chips = document.getElementById('dx-chips');

    function store(fn) { try { return fn(); } catch (e) { return null; } }

    function show(type) {
        typeInput.value = type;
        form.querySelectorAll('[data-type]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.type === type ? 'true' : 'false'); });
        form.querySelectorAll('[data-field]').forEach(function (el) {
            var f = el.dataset.field;
            el.hidden = f === 'procedures' ? type !== 'sesion' : types[type].indexOf(f) < 0;
        });
        save();
    }
    function save() {
        var d = { type: typeInput.value, fields: {}, dx: [] };
        form.querySelectorAll('[name^="fields["]').forEach(function (el) { d.fields[el.name.slice(7, -1)] = el.value; });
        chips.querySelectorAll('input').forEach(function (i) { d.dx.push(i.value); });
        store(function () { localStorage.setItem(key, JSON.stringify(d)); });
    }
    function addChip(value) {
        var parts = value.split('|'), label = parts[1] ? parts[0] + ' · ' + parts[1] : parts[0];
        var span = document.createElement('span');
        span.className = 'chip c-conf';
        span.innerHTML = '<input type="hidden" name="dx[]"> <button type="button" class="linkbtn" aria-label="Quitar">×</button>';
        span.querySelector('input').value = value;
        span.insertBefore(document.createTextNode(label), span.firstChild);
        span.querySelector('button').addEventListener('click', function () { span.remove(); save(); });
        chips.appendChild(span);
    }

    @if (session('clear_draft'))
        store(function () { localStorage.removeItem(key); });
    @elseif (! old('type'))
        var draft = store(function () { return JSON.parse(localStorage.getItem(key) || 'null'); });
        if (draft) {
            Object.keys(draft.fields || {}).forEach(function (f) {
                var el = form.querySelector('[name="fields[' + f + ']"]');
                if (el && !el.value) el.value = draft.fields[f];
            });
            (draft.dx || []).forEach(addChip);
            if (draft.type && types[draft.type]) typeInput.value = draft.type;
        }
    @endif
    @foreach (old('dx', []) as $dx) addChip(@json($dx)); @endforeach

    form.querySelectorAll('[data-type]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.type); }); });
    form.addEventListener('input', save);

    document.getElementById('dx-add').addEventListener('click', function () {
        var input = document.getElementById('dx-search'), v = input.value.trim();
        if (!v) { input.focus(); return; }
        var m = v.match(/^([A-Z]\d{2}(?:\.\d)?)\s*·\s*(.+)$/), value = m ? m[1] + '|' + m[2] : '|' + v;
        addChip(value);
        var ta = document.getElementById('f-diagnostico'), line = m ? m[1] + ' ' + m[2] : v;
        ta.value = ta.value ? ta.value.replace(/\s*$/, '') + '\n' + line : line;
        input.value = '';
        save();
    });
    form.addEventListener('submit', function () { save(); });

    show(typeInput.value);
})();
</script>
@endpush
