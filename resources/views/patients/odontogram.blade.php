@extends('layouts.app')
@section('title', 'Odontograma · '.$patient->name)
@use('App\Support\Clinical')

@section('content')
@include('patients._header')
@php
    $isDoc = auth()->user()->isDoctor();
    $data = $teeth->map(fn ($t) => ['findings' => $t->findings ?? [], 'plan' => $t->plan ?? [], 'note' => $t->note])->all();
    $customColor = collect($findings)->filter(fn ($f) => ! empty($f['custom']))->map(fn ($f) => $f['color'])->all();
    $faceFill = fn (?string $type) => $type && isset($customColor[$type]) ? 'fill:'.$customColor[$type] : null;
    $faceClass = fn (?string $type) => $type && ! isset($customColor[$type]) ? 'f-'.$type : '';
    // Polígonos de cada cara en un diente de 44 x 44.
    $poly = [
        'top' => '4,4 40,4 30,14 14,14',
        'bottom' => '14,30 30,30 40,40 4,40',
        'left' => '4,4 14,14 14,30 4,40',
        'right' => '40,4 40,40 30,30 30,14',
    ];
@endphp

<div class="grid-side odo-layout">
    <div class="card stack">
        <div class="card-h">
            <h2>Odontograma</h2>
            <span class="small muted">{{ $isDoc ? 'Toca un diente para marcar hallazgos y el tratamiento a realizar' : 'Toca un diente para ver el detalle' }}</span>
        </div>

        @php $suggested = Clinical::dentitionForAge($patient->age()); @endphp
        <div class="row" style="justify-content:space-between;gap:10px">
            <div class="dseg" role="group" aria-label="Dentición">
                @foreach (Clinical::DENTITIONS as $key => $label)
                    @if ($isDoc)
                        <form method="POST" action="{{ route('patients.odontogram.dentition', $patient) }}">@csrf @method('PUT')
                            <button name="dentition" value="{{ $key }}" aria-pressed="{{ $dentition === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        </form>
                    @else
                        <a href="{{ route('patients.odontogram', [$patient, 'denticion' => $key]) }}" aria-pressed="{{ $dentition === $key ? 'true' : 'false' }}">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
            <span class="small muted">
                @if ($patient->age() !== null)Sugerida para {{ $patient->age() }} años: {{ mb_strtolower(Clinical::DENTITIONS[$suggested]) }}.@endif
                @if ($dentition !== 'permanente') Temporales del 51 al 85.@endif
            </span>
        </div>

        <div class="odo2 {{ $dentition !== 'permanente' ? 'kids' : '' }}" role="group" aria-label="Dientes">
            @foreach ($chart as $row)
                @if ($row['label'] && ($loop->first || $chart[$loop->index - 1]['label'] !== $row['label']) && $row['upper'])
                    <div class="arch-label">{{ $row['label'] }}</div>
                @endif
                <div class="arch2 {{ $row['primary'] ? 'primary' : '' }}">
                    @foreach ($row['quads'] as $quadrant)
                        <div class="quad2">
                            @foreach ($quadrant as $n)
                                @php
                                    $t = $teeth->get($n);
                                    $colors = $t ? $t->faceColors() : [];
                                    $upper = Clinical::isUpper($n);
                                    $mRight = Clinical::mesialOnRight($n);
                                    $faceAt = ['top' => $upper ? 'V' : 'L', 'bottom' => $upper ? 'L' : 'V', 'left' => $mRight ? 'D' : 'M', 'right' => $mRight ? 'M' : 'D'];
                                @endphp
                                <button type="button" class="tooth2 {{ $t ? 'has' : '' }} {{ $t?->plan ? 'planned' : '' }}" data-tooth="{{ $n }}" aria-label="Diente {{ $n }}{{ $t ? ': '.collect($t->findings)->map(fn ($f) => Clinical::describeFinding($f))->join(', ') : '' }}{{ $t?->plan ? '. Tratamiento: '.implode(', ', $t->planNames()) : '' }}">
                                    @if ($row['upper'])<span class="tn">{{ $n }}</span>@endif
                                    <svg viewBox="0 0 44 44" width="40" height="40" aria-hidden="true">
                                        @foreach ($poly as $pos => $points)
                                            <polygon points="{{ $points }}" class="face {{ $faceClass($colors[$faceAt[$pos]] ?? null) }}" @if ($faceFill($colors[$faceAt[$pos]] ?? null)) style="{{ $faceFill($colors[$faceAt[$pos]]) }}" @endif/>
                                        @endforeach
                                        <rect x="14" y="14" width="16" height="16" class="face {{ $faceClass($colors['O'] ?? null) }}" @if ($faceFill($colors['O'] ?? null)) style="{{ $faceFill($colors['O']) }}" @endif/>
                                        @if ($t?->has('corona'))<circle cx="22" cy="22" r="20" class="mk-corona"/>@endif
                                        @if ($t?->has('ausente'))<path d="M6 6L38 38M38 6L6 38" class="mk-x"/>@endif
                                        @if ($t?->has('extraccion'))<path d="M6 6L38 38M38 6L6 38" class="mk-ex"/>@endif
                                    </svg>
                                    <span class="ab">{{ $t ? implode(' ', array_diff($t->abbreviations(), ['C', 'O', 'F', 'S', '×', 'Mb'])) : '' }}</span>
                                    @if (! $row['upper'])<span class="tn">{{ $n }}</span>@endif
                                    @if ($t?->note)<span class="dot" title="Tiene observación"></span>@endif
                                    @if ($t?->plan)<span class="tx" title="Tratamiento indicado: {{ implode(', ', $t->planNames()) }}">T</span>@endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if ($row['label'] && ! $row['upper'] && ($loop->last || $chart[$loop->index + 1]['label'] !== $row['label']))
                    <div class="arch-label">{{ $row['label'] }}</div>
                @endif
            @endforeach
        </div>

        <div class="row small" aria-label="Convenciones">
            <span class="legend"><i class="sw f-caries"></i>Caries</span>
            <span class="legend"><i class="sw f-obturacion"></i>Obturación</span>
            <span class="legend"><i class="sw f-fractura"></i>Fractura</span>
            <span class="legend"><i class="sw f-sellante"></i>Sellante</span>
            @foreach (collect($findings)->filter(fn ($f) => ! empty($f['custom']) && ($f['active'] ?? true)) as $f)
                <span class="legend">@if ($f['faces'])<i class="sw" style="background:{{ $f['color'] }}"></i>@else<b style="color:var(--accent)">{{ $f['abbr'] }}</b>@endif{{ $f['name'] }}</span>
            @endforeach
            <span class="legend"><i class="tx-sw"></i>Con tratamiento indicado</span>
            @if ($dentition !== 'permanente')
                <span class="legend"><i class="sw f-mancha_blanca"></i>Mancha blanca</span>
                <span class="muted">E endodoncia · Pt pulpotomía · CA corona de acero · Me mantenedor · Mv movilidad · Er en erupción · NE sin erupcionar · Ex extracción indicada · × ausente</span>
            @else
                <span class="muted">E endodoncia · Co corona · Ca carilla · I implante · P prótesis · Ex extracción indicada · × ausente</span>
            @endif
        </div>

        <div class="idx" aria-label="Índices de caries">
            @if ($dentition !== 'temporal')
                <div><span class="label">COP-D</span> <b>{{ $index['cop']['total'] }}</b>
                    <span class="small muted">cariados {{ $index['cop']['c'] }} · obturados {{ $index['cop']['o'] }} · perdidos {{ $index['cop']['p'] }}</span></div>
            @endif
            @if ($dentition !== 'permanente')
                <div><span class="label" style="text-transform:none">ceo-d</span> <b>{{ $index['ceo']['total'] }}</b>
                    <span class="small muted">cariados {{ $index['ceo']['c'] }} · extracción indicada {{ $index['ceo']['e'] }} · obturados {{ $index['ceo']['o'] }}</span></div>
            @endif
        </div>

        <div>
            <div class="row" style="justify-content:space-between;margin-bottom:8px">
                <div class="label">Hallazgos y tratamiento por diente</div>
                @if ($planned)
                    <a class="btn sm pri" href="{{ route('quotes.create', ['paciente' => $patient->id, 'desde' => 'odontograma']) }}">Pasar a cotización ({{ $planned === 1 ? '1 diente' : $planned.' dientes' }})</a>
                @endif
            </div>
            @if ($teeth->isEmpty())
                <p class="muted">Todavía no hay hallazgos.</p>
            @else
                <div class="tblwrap"><table class="tbl">
                    <thead><tr><th>Diente</th><th>Hallazgos</th><th>Tratamiento indicado</th><th>Observación</th></tr></thead>
                    <tbody>
                    @foreach ($teeth->sortKeys() as $n => $t)
                        <tr>
                            <td><button type="button" class="linkbtn mono" data-tooth="{{ $n }}">{{ $n }}</button></td>
                            <td>{{ collect($t->findings)->map(fn ($f) => Clinical::describeFinding($f))->join(' · ') ?: '—' }}</td>
                            <td>@if ($t->plan)<strong>{{ implode(' · ', $t->planNames()) }}</strong>@else — @endif</td>
                            <td style="white-space:pre-line">{{ $t->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>

    <aside class="card stack" id="tooth-panel" aria-live="polite">
        <div id="panel-empty">
            <h2>Elige un diente</h2>
            <p class="muted" style="margin-top:6px">Toca cualquier diente del dibujo para ver o marcar sus hallazgos y escribir una observación.</p>
            <p class="small muted" style="margin-top:10px">Caras: O oclusal o incisal · M mesial · D distal · V vestibular · L lingual o palatino.</p>
        </div>
        <form method="POST" id="panel-form" hidden class="stack" data-action="{{ route('patients.odontogram.update', [$patient, 0]) }}">
            @csrf @method('PUT')
            @if (request()->has('denticion'))<input type="hidden" name="denticion" value="{{ $dentition }}">@endif
            <h2 id="panel-title">Diente</h2>
            <div class="stack-sm">
                @foreach ($findings as $key => $f)
                    <div class="finding" data-scope="{{ ! ($f['active'] ?? true) ? 'off' : (! empty($f['kids']) ? 'kids' : (in_array($key, Clinical::ADULT_ONLY, true) ? 'adult' : 'all')) }}">
                        <label class="check" style="align-items:center">
                            <input type="checkbox" name="findings[]" value="{{ $key }}" data-finding="{{ $key }}" @disabled(! $isDoc)>
                            <span>@if (! empty($f['custom']))<i class="sw" style="background:{{ $f['color'] }};margin-right:6px"></i>@endif{{ $f['name'] }}</span>
                        </label>
                        @if ($f['faces'])
                            <div class="faces" data-faces="{{ $key }}" hidden>
                                @foreach (Clinical::SURFACES as $s => $label)
                                    <label class="facebtn" title="{{ $label }}"><input type="checkbox" name="surfaces[{{ $key }}][]" value="{{ $s }}" @disabled(! $isDoc)><span>{{ $s }}</span></label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($isDoc)
                <p class="small muted" style="margin:0">¿Falta un hallazgo? Agrégalo en <a href="{{ route('settings') }}#hallazgos">Configuración</a>.</p>
            @endif

            <hr class="sep">
            <div class="stack-sm">
                <div class="label">Tratamiento a realizar</div>
                <div id="plan-list" class="plan-list"></div>
                @if ($isDoc)
                    <div class="row" style="gap:6px">
                        <select id="plan-service" class="input" style="flex:1;min-width:0" aria-label="Tratamiento del catálogo">
                            <option value="">Del catálogo…</option>
                            @foreach ($services as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                        </select>
                        <button type="button" class="btn sm" id="plan-add-service">Agregar</button>
                    </div>
                    <div class="row" style="gap:6px">
                        <input id="plan-other" class="input" style="flex:1;min-width:0" maxlength="120" placeholder="Otro: escribe el tratamiento" aria-label="Otro tratamiento">
                        <button type="button" class="btn sm" id="plan-add-other">Agregar</button>
                    </div>
                @endif
            </div>

            <label class="field" for="tooth-note"><span>Observación del diente</span>
                <textarea id="tooth-note" name="note" class="input" style="min-height:90px" placeholder="Ej: caries ocluso distal profunda, valorar endodoncia" @disabled(! $isDoc)></textarea>
            </label>
            @if ($isDoc)
                <button class="btn pri">Guardar diente</button>
            @else
                <p class="small muted">Solo la doctora marca el odontograma.</p>
            @endif
        </form>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var data = @json($data), form = document.getElementById('panel-form'), empty = document.getElementById('panel-empty'),
        kidsView = @json($dentition !== 'permanente');
    var base = form.dataset.action.replace(/\/0$/, '/'), isDoc = @json($isDoc),
        planList = document.getElementById('plan-list'), planSeq = 0;

    // Tratamiento a realizar: chips con los campos ocultos que se envían con el diente.
    function addPlan(serviceId, name) {
        name = (name || '').trim();
        if (!name) return;
        var exists = Array.prototype.some.call(planList.querySelectorAll('[data-plan-name]'), function (i) { return i.value.toLowerCase() === name.toLowerCase(); });
        if (exists) return;
        var i = planSeq++, chip = document.createElement('span');
        chip.className = 'plan-chip';
        chip.appendChild(document.createTextNode(name));
        [['service_id', serviceId || ''], ['name', name]].forEach(function (kv) {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = 'plan[' + i + '][' + kv[0] + ']'; h.value = kv[1];
            if (kv[0] === 'name') h.setAttribute('data-plan-name', '');
            chip.appendChild(h);
        });
        if (isDoc) {
            var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', 'Quitar ' + name);
            x.addEventListener('click', function () { chip.remove(); emptyPlan(); });
            chip.appendChild(x);
        }
        planList.appendChild(chip);
        emptyPlan();
    }
    function emptyPlan() {
        var none = planList.querySelector('.plan-none');
        if (!planList.querySelector('.plan-chip')) { if (!none) planList.insertAdjacentHTML('beforeend', '<span class="plan-none small muted">Sin tratamiento indicado.</span>'); }
        else if (none) none.remove();
    }
    if (isDoc) {
        var sel = document.getElementById('plan-service'), other = document.getElementById('plan-other');
        document.getElementById('plan-add-service').addEventListener('click', function () {
            if (sel.value) { addPlan(sel.value, sel.options[sel.selectedIndex].text); sel.value = ''; }
        });
        sel.addEventListener('change', function () { if (sel.value) { addPlan(sel.value, sel.options[sel.selectedIndex].text); sel.value = ''; } });
        function addOther() { addPlan('', other.value); other.value = ''; }
        document.getElementById('plan-add-other').addEventListener('click', addOther);
        other.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addOther(); } });
    }

    function syncFaces() {
        form.querySelectorAll('[data-finding]').forEach(function (cb) {
            var faces = form.querySelector('[data-faces="' + cb.dataset.finding + '"]');
            if (faces) faces.hidden = !cb.checked;
        });
    }
    function open(n) {
        var t = data[n] || { findings: [], note: '' };
        form.action = base + n;
        document.getElementById('panel-title').textContent = 'Diente ' + n;
        form.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
        t.findings.forEach(function (f) {
            var cb = form.querySelector('[data-finding="' + f.type + '"]');
            if (cb) cb.checked = true;
            (f.surfaces || []).forEach(function (s) {
                var fc = form.querySelector('input[name="surfaces[' + f.type + '][]"][value="' + s + '"]');
                if (fc) fc.checked = true;
            });
        });
        document.getElementById('tooth-note').value = t.note || '';
        planList.innerHTML = '';
        (t.plan || []).forEach(function (p) { addPlan(p.service_id, p.name); });
        // Temporales: sin implante, prótesis ni carilla. Hallazgos de niños solo en temporal o mixta.
        var primary = Number(n) >= 51;
        form.querySelectorAll('.finding').forEach(function (el) {
            var checked = el.querySelector('[data-finding]').checked;
            el.hidden = !checked && (el.dataset.scope === 'off' || (el.dataset.scope === 'kids' && !kidsView && !primary) || (el.dataset.scope === 'adult' && primary));
        });
        document.getElementById('panel-title').textContent = 'Diente ' + n + (primary ? ' (temporal)' : '');
        syncFaces();
        empty.hidden = true; form.hidden = false;
        document.querySelectorAll('.tooth2').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.tooth === String(n) ? 'true' : 'false'); });
        if (window.innerWidth < 1000) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('[data-tooth]').forEach(function (b) {
        b.addEventListener('click', function () { open(b.dataset.tooth); });
    });
    form.addEventListener('change', function (e) {
        if (e.target.dataset.finding) {
            syncFaces();
            // Ausente no convive con otros hallazgos del diente.
            if (e.target.dataset.finding === 'ausente' && e.target.checked) {
                form.querySelectorAll('[data-finding]').forEach(function (cb) { if (cb !== e.target && cb.dataset.finding !== 'implante') cb.checked = false; });
                syncFaces();
            }
        }
    });
    var params = new URLSearchParams(location.search);
    if (params.get('diente')) open(params.get('diente'));
})();
</script>
@endpush
