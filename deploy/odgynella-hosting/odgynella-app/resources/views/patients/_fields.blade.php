{{-- Campos de datos del paciente. Requiere $patient; $requireDocument (por defecto true). --}}
@php $requireDocument = $requireDocument ?? true; @endphp
<div class="fgrid">
    <label class="field full" for="name"><span>Nombre completo</span>
        <input id="name" name="name" class="input" value="{{ old('name', $patient->name) }}" required maxlength="150">
    </label>
    <label class="field" for="doc_type"><span>Tipo de documento</span>
        <select id="doc_type" name="doc_type" class="input">
            @unless ($requireDocument)<option value="">Sin documento todavía</option>@endunless
            @foreach (\App\Models\Patient::DOC_TYPES as $t)
                <option @selected(old('doc_type', $patient->doc_type ?? ($requireDocument ? 'C.C.' : null)) === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="field" for="doc_number"><span>Número</span>
        <input id="doc_number" name="doc_number" class="input" value="{{ old('doc_number', $patient->doc_number) }}" @if ($requireDocument) required @endif maxlength="40">
    </label>
    <label class="field" for="phone"><span>WhatsApp</span>
        <input id="phone" name="phone" class="input" inputmode="tel" value="{{ old('phone', $patient->phone) }}" required placeholder="Ej: 300 123 4567" maxlength="40">
    </label>
    <label class="field" for="birth_date"><span>Fecha de nacimiento</span>
        <input id="birth_date" name="birth_date" type="date" class="input" value="{{ old('birth_date', $patient->birth_date?->toDateString()) }}">
    </label>
    <label class="field" for="email"><span>Correo <span class="muted">(opcional)</span></span>
        <input id="email" name="email" type="email" class="input" value="{{ old('email', $patient->email) }}" maxlength="150" placeholder="Para órdenes y resultados">
    </label>
    <label class="field" for="city"><span>Ciudad y país</span>
        <input id="city" name="city" class="input" value="{{ old('city', $patient->city) }}" maxlength="120">
    </label>
    <label class="field" for="source"><span>Cómo llegó</span>
        <select id="source" name="source" class="input">
            <option value="">Sin dato</option>
            @foreach (\App\Models\Patient::SOURCES as $s)
                <option @selected(old('source', $patient->source) === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </label>
</div>
