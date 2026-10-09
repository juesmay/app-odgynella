@extends('layouts.app')
@section('title', 'Fotos · '.$patient->name)
@use('App\Support\Clinical')

@section('content')
@include('patients._header')

<div class="grid-side">
    <div class="card stack">
        <div class="card-h">
            <h2>Fotos antes y después</h2>
            <span class="small {{ $patient->photo_consent ? 'muted' : '' }}" style="{{ $patient->photo_consent ? '' : 'color:var(--warn);font-weight:700' }}">
                {{ $patient->photo_consent ? 'Autorizó usar sus fotos en redes' : 'No ha autorizado usar sus fotos en redes' }}
            </span>
        </div>

        @if ($compare)
            <div>
                <div class="card-h" style="margin-bottom:8px"><span class="label">Comparación</span><a class="small" href="{{ route('patients.photos', $patient) }}">Cerrar</a></div>
                <div class="cmp">
                    @foreach ($compare as $p)
                        <figure>
                            <img src="{{ route('patients.photos.file', [$patient, $p]) }}" alt="{{ $p->stageName() }} {{ $p->taken_on->translatedFormat('j M Y') }}">
                            <figcaption>{{ $p->stageName() }} · {{ $p->taken_on->translatedFormat('j M Y') }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($photos->isEmpty())
            <div class="empty">Sin fotos todavía. Sube la foto de “antes” en la primera cita.</div>
        @else
            <form method="GET" action="{{ route('patients.photos', $patient) }}" class="stack" id="cmp-form">
                <p class="small muted">Marca dos fotos y toca “Comparar” para verlas lado a lado.</p>
                <div class="photos">
                    @foreach ($photos as $p)
                        <div class="ph">
                            <a href="{{ route('patients.photos.file', [$patient, $p]) }}" target="_blank" rel="noopener"><img src="{{ route('patients.photos.file', [$patient, $p]) }}" alt="{{ $p->stageName() }} {{ $p->taken_on->translatedFormat('j M Y') }}" loading="lazy"></a>
                            <div class="cap"><strong>{{ $p->stageName() }}</strong> · {{ $p->taken_on->translatedFormat('j M Y') }}@if ($p->note)<br><span class="muted">{{ $p->note }}</span>@endif</div>
                            <label><input type="checkbox" value="{{ $p->id }}" data-cmp> Comparar</label>
                        </div>
                    @endforeach
                </div>
                <input type="hidden" name="a" id="cmp-a"><input type="hidden" name="b" id="cmp-b">
                <button class="btn" id="cmp-btn" disabled style="align-self:flex-start">Comparar</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('patients.photos.store', $patient) }}" enctype="multipart/form-data" class="card stack">
        @csrf
        <h2>Subir fotos</h2>
        <label class="field" for="photos"><span>Fotos (puedes elegir varias)</span>
            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="input">
        </label>
        <label class="field" for="stage"><span>Momento</span>
            <select id="stage" name="stage" class="input">
                @foreach (Clinical::PHOTO_STAGES as $k => $v)
                    <option value="{{ $k }}" @selected(old('stage') === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="taken_on"><span>Fecha</span>
            <input id="taken_on" name="taken_on" type="date" class="input" value="{{ old('taken_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
        </label>
        <label class="field" for="note"><span>Nota (opcional)</span>
            <input id="note" name="note" class="input" maxlength="200" placeholder="Ej: sonrisa frontal, luz natural">
        </label>
        <button class="btn pri">Subir</button>
        <p class="small muted">Las fotos quedan en una carpeta privada del sistema; solo se ven con sesión iniciada.</p>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var boxes = document.querySelectorAll('[data-cmp]'), btn = document.getElementById('cmp-btn');
    if (!btn) return;
    function sync() {
        var on = Array.prototype.filter.call(boxes, function (b) { return b.checked; });
        if (on.length > 2) { on[0].checked = false; on.shift(); }
        btn.disabled = on.length !== 2;
        document.getElementById('cmp-a').value = on[0] ? on[0].value : '';
        document.getElementById('cmp-b').value = on[1] ? on[1].value : '';
    }
    boxes.forEach(function (b) { b.addEventListener('change', sync); });
})();
</script>
@endpush
