@extends('layouts.app')
@section('title', 'Configuración')

@section('content')
<div class="head">
    <div>
        <h1>Configuración</h1>
        <p class="sub">Tus datos para los documentos, servicios, precios y en qué sede atiendes cada día.</p>
    </div>
</div>

<div class="card">
    <div class="card-h"><h2>Servicios y paquetes</h2><span class="small muted">Cada fila se guarda con su botón</span></div>
    <div class="tblwrap"><table class="tbl">
        <thead><tr><th>Nombre</th><th class="r">Minutos</th><th class="r">Precio</th><th>Paquete</th><th>Activo</th><th></th></tr></thead>
        <tbody>
        @foreach ($services as $s)
            <tr>
                <td><input form="svc-{{ $s->id }}" name="name" class="input" value="{{ $s->name }}" aria-label="Nombre" required></td>
                <td class="r"><input form="svc-{{ $s->id }}" name="duration_min" class="input" style="max-width:90px;text-align:right" inputmode="numeric" value="{{ $s->duration_min }}" aria-label="Minutos de {{ $s->name }}"></td>
                <td class="r"><input form="svc-{{ $s->id }}" name="price" class="input" style="max-width:140px;text-align:right" inputmode="numeric" value="{{ $s->price }}" aria-label="Precio de {{ $s->name }}"></td>
                <td><input form="svc-{{ $s->id }}" type="checkbox" name="is_package" value="1" @checked($s->is_package) aria-label="Es paquete"></td>
                <td><input form="svc-{{ $s->id }}" type="checkbox" name="active" value="1" @checked($s->active) aria-label="Activo"></td>
                <td>
                    <form id="svc-{{ $s->id }}" method="POST" action="{{ route('settings.services.update', $s) }}">@csrf @method('PUT')<button class="btn sm">Guardar</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>

    <form method="POST" action="{{ route('settings.services.store') }}" class="row" style="margin-top:14px">
        @csrf
        <input class="input" name="name" placeholder="Nuevo servicio" style="flex:2;min-width:160px" required aria-label="Nombre del servicio">
        <input class="input" name="duration_min" placeholder="Minutos" inputmode="numeric" style="flex:1;min-width:90px" required aria-label="Minutos">
        <input class="input" name="price" placeholder="Precio" inputmode="numeric" style="flex:1;min-width:110px" required aria-label="Precio">
        <label class="check" style="align-items:center"><input type="checkbox" name="is_package" value="1"><span>Paquete</span></label>
        <button class="btn">Agregar</button>
    </form>
</div>

<form method="POST" action="{{ route('settings.schedule') }}" class="card stack">
    @csrf @method('PUT')
    <h2>¿En qué sede atiendes cada día?</h2>
    @php $days = ['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '0' => 'Domingo']; @endphp
    <div class="fgrid">
        @foreach ($days as $num => $label)
            <label class="field" for="day-{{ $num }}"><span>{{ $label }}</span>
                <select id="day-{{ $num }}" name="day[{{ $num }}]" class="input">
                    <option value="">No atiende</option>
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" @selected($clinic->sedeIdForWeekday((int) $num) === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
    </div>
    <button class="btn pri" style="align-self:flex-start">Guardar horario</button>
</form>

<form method="POST" action="{{ route('settings.brand') }}" enctype="multipart/form-data" class="card stack" id="marca">
    @csrf
    <div class="card-h"><h2>Datos de la doctora</h2><span class="small muted">Salen en la cotización, la orden de radiografía y los consentimientos</span></div>
    <div class="fgrid">
        <label class="field" for="doctor_name"><span>Nombre como sale en la firma</span>
            <input id="doctor_name" name="doctor_name" class="input" value="{{ old('doctor_name', $clinic->doctor_name) }}" required maxlength="120">
        </label>
        <label class="field" for="doctor_title"><span>Título</span>
            <input id="doctor_title" name="doctor_title" class="input" value="{{ old('doctor_title', $clinic->doctor_title) }}" maxlength="60" placeholder="Odontóloga">
        </label>
        <label class="field" for="doctor_license"><span>Registro profesional (tarjeta profesional)</span>
            <input id="doctor_license" name="doctor_license" class="input" value="{{ old('doctor_license', $clinic->doctor_license) }}" maxlength="60" placeholder="Sale junto a la firma">
        </label>
        <label class="field" for="phone"><span>Teléfono de contacto</span>
            <input id="phone" name="phone" class="input" value="{{ old('phone', $clinic->phone) }}" maxlength="40">
        </label>
        <label class="field" for="email"><span>Correo</span>
            <input id="email" name="email" type="email" class="input" value="{{ old('email', $clinic->email) }}" maxlength="120">
        </label>
        <label class="field" for="instagram"><span>Instagram</span>
            <input id="instagram" name="instagram" class="input" value="{{ old('instagram', $clinic->instagram) }}" maxlength="60" placeholder="@usuario">
        </label>
        <label class="field full" for="payment_note"><span>Texto de facilidad de pago (cotización)</span>
            <textarea id="payment_note" name="payment_note" class="input" maxlength="400" style="min-height:70px">{{ old('payment_note', $clinic->payment_note) }}</textarea>
        </label>
        <label class="field full" for="radiology_centers"><span>Centros radiológicos (uno por línea)</span>
            <textarea id="radiology_centers" name="radiology_centers" class="input" style="min-height:80px" placeholder="Ej: Cero70 Centro Radiológico Oral · 448 60 70">{{ old('radiology_centers', implode("\n", $clinic->radiology_centers ?? [])) }}</textarea>
        </label>
        <div class="field">
            <span>Logo</span>
            @if ($clinic->logo_path)<img src="{{ route('settings.logo') }}" alt="Logo actual" style="max-width:100%;max-height:48px;background:#fff;padding:6px;border-radius:8px;border:1px solid var(--line)">@endif
            <input id="logo" name="logo" type="file" class="input" accept="image/png,image/jpeg" aria-label="Cambiar logo">
            <span class="small muted">PNG con fondo transparente, horizontal.</span>
        </div>
        <label class="field" for="brand_color"><span>Color de la marca</span>
            <input id="brand_color" name="brand_color" type="color" class="input" style="height:44px;padding:4px" value="{{ old('brand_color', $clinic->brand_color ?: \App\Support\Brand::DEFAULT_COLOR) }}">
            <span class="small muted">Con este color se hacen las curvas y la tabla de los PDF.</span>
        </label>
    </div>
    <div class="sig stack-sm">
        <div class="card-h"><span class="label">Tu firma</span><button type="button" class="btn sm ghost" id="sig-clear">Borrar</button></div>
        @if ($clinic->signature)
            <div class="row" id="sig-current"><img src="{{ $clinic->signature }}" alt="Firma guardada" style="height:70px;background:#fff;border-radius:8px;border:1px solid var(--line);padding:4px">
                <label class="check"><input type="checkbox" name="remove_signature" value="1"><span>Quitar la firma guardada</span></label></div>
            <span class="small muted">Para cambiarla, sube una foto o dibuja una nueva abajo.</span>
        @endif
        <label class="field" for="signature_photo"><span>Subir foto de la firma</span>
            <input id="signature_photo" name="signature_photo" type="file" class="input" accept="image/jpeg,image/png,image/webp">
            <span class="small muted">Firma con esfero negro o azul en una hoja blanca y tómale una foto de cerca, con buena luz y sin sombras. El sistema quita el fondo del papel y recorta la firma. Luego toca “Guardar datos”.</span>
        </label>
        <span class="small muted">O dibújala aquí:</span>
        <canvas id="sig-doc" aria-label="Recuadro para dibujar tu firma"></canvas>
        <input type="hidden" name="signature" id="sig-data">
        <span class="small muted">Firma con el dedo, un lápiz táctil o el mouse. Sale en las órdenes y los consentimientos.</span>
    </div>
    <button class="btn pri" style="align-self:flex-start">Guardar datos</button>
</form>

<form method="POST" action="{{ route('settings.quotes') }}" class="card stack">
    @csrf @method('PUT')
    <h2>Cotizaciones</h2>
    <div class="fgrid">
        <label class="field" for="quote_validity_days"><span>Vigencia (días)</span>
            <input id="quote_validity_days" name="quote_validity_days" class="input" inputmode="numeric" value="{{ old('quote_validity_days', $clinic->quote_validity_days) }}" required>
        </label>
        <label class="field" for="payment_options"><span>Formas de pago que salen en el PDF</span>
            <input id="payment_options" name="payment_options" class="input" value="{{ old('payment_options', $clinic->payment_options) }}" required maxlength="255">
        </label>
    </div>
    <button class="btn pri" style="align-self:flex-start">Guardar</button>
</form>

<div class="card stack">
    <div>
        <h2>Consentimientos informados</h2>
        <p class="small muted" style="margin-top:4px">Pega aquí tus textos. Puedes usar {paciente}, {documento}, {doctora} y {fecha}: se llenan solos al firmar. Cambiar un texto no altera los consentimientos ya firmados.</p>
    </div>
    @foreach ($templates as $t)
        <details>
            <summary style="cursor:pointer;font-weight:700;padding:8px 0">{{ $t->name }}{{ $t->active ? '' : ' (inactivo)' }}</summary>
            <form method="POST" action="{{ route('settings.templates.update', $t) }}" class="stack" style="margin:8px 0 16px">
                @csrf @method('PUT')
                <label class="field" for="tn-{{ $t->id }}"><span>Nombre</span><input id="tn-{{ $t->id }}" name="name" class="input" value="{{ $t->name }}" required maxlength="120"></label>
                <label class="field" for="tb-{{ $t->id }}"><span>Texto</span><textarea id="tb-{{ $t->id }}" name="body" class="input" style="min-height:260px" required>{{ $t->body }}</textarea></label>
                <label class="check"><input type="checkbox" name="active" value="1" @checked($t->active)><span>Activo (aparece al firmar)</span></label>
                <button class="btn pri" style="align-self:flex-start">Guardar consentimiento</button>
            </form>
        </details>
    @endforeach
    <details>
        <summary style="cursor:pointer;font-weight:700;padding:8px 0">+ Nuevo consentimiento</summary>
        <form method="POST" action="{{ route('settings.templates.store') }}" class="stack" style="margin-top:8px">
            @csrf
            <label class="field" for="tn-new"><span>Nombre</span><input id="tn-new" name="name" class="input" required maxlength="120" placeholder="Ej: Endodoncia"></label>
            <label class="field" for="tb-new"><span>Texto</span><textarea id="tb-new" name="body" class="input" style="min-height:200px" required></textarea></label>
            <button class="btn" style="align-self:flex-start">Agregar</button>
        </form>
    </details>
</div>

<form method="POST" action="{{ route('settings.rates') }}" class="card stack" id="tarifas">
    @csrf @method('PUT')
    <div>
        <h2>Tarifa del consultorio por hora</h2>
        <p class="small muted" style="margin-top:4px">Al marcar una cita como “Atendida” se registra el tiempo de consultorio como costo de ese paciente (duración de la cita × tarifa). Cuando cambie la tarifa (cada semestre), actualízala aquí: las citas ya atendidas no cambian. En $0 no se registra nada.</p>
    </div>
    <div class="fgrid">
        @foreach ($sedes as $s)
            <label class="field" for="rate-{{ $s->id }}"><span>{{ $s->name }}</span>
                <input id="rate-{{ $s->id }}" name="rate[{{ $s->id }}]" class="input" inputmode="numeric" value="{{ $s->hourly_rate ?: '' }}" placeholder="$0 por hora">
            </label>
        @endforeach
    </div>
    <button class="btn pri" style="align-self:flex-start">Guardar tarifas</button>
</form>

<div class="card stack" id="categorias">
    <div>
        <h2>Categorías de gasto</h2>
        <p class="small muted" style="margin-top:4px">Tres tipos: <b>Consultorio</b> (lo que cuesta atender: arriendo, horas, insumos, laboratorio), <b>Negocio</b> (publicidad, software, impuestos) y <b>Personal</b> (lo que sacas para ti). Lo personal y las categorías privadas no las ve la asistente.</p>
    </div>
    <div class="grid3">
        @foreach (\App\Models\Expense::TYPES as $type => $label)
            <div class="stack-sm">
                <div class="label">{{ $label }}</div>
                @foreach ($expenseCategories[$type] ?? [] as $c)
                    <form method="POST" action="{{ route('settings.categories.update', $c) }}" class="catrow {{ $c->active ? '' : 'off' }}">
                        @csrf @method('PUT')
                        <input name="name" class="input" value="{{ $c->name }}" required maxlength="80" aria-label="Nombre" @readonly($c->name === \App\Models\ExpenseCategory::ROOM_TIME)>
                        <label class="check small" title="Activa"><input type="checkbox" name="active" value="1" @checked($c->active) @disabled($c->name === \App\Models\ExpenseCategory::ROOM_TIME)><span>Activa</span></label>
                        @if ($type !== 'personal')<label class="check small" title="La asistente no la ve"><input type="checkbox" name="private" value="1" @checked($c->private)><span>Privada</span></label>@endif
                        <button class="btn sm">Guardar</button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('settings.categories.store') }}" class="catrow">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input name="name" class="input" required maxlength="80" placeholder="Nueva categoría" aria-label="Nueva categoría de {{ $label }}">
                    <button class="btn sm">Agregar</button>
                </form>
            </div>
        @endforeach
    </div>
</div>

<div class="card stack" id="hallazgos">
    <div>
        <h2>Hallazgos del odontograma</h2>
        <p class="small muted" style="margin-top:4px">Lo que no esté en la lista del odontograma (por ejemplo, “Amalgama en mal estado” o “Diastema”). Aparecen al tocar un diente, junto a los que ya vienen.</p>
    </div>
    @if ($findings->isNotEmpty())
        <div class="tblwrap"><table class="tbl compact">
            <thead><tr><th>Hallazgo</th><th style="width:90px">Sigla</th><th style="width:80px">Color</th><th>Se marca</th><th>Activo</th><th></th></tr></thead>
            <tbody>
            @foreach ($findings as $f)
                <tr>
                    <td><input form="hf-{{ $f->id }}" name="name" class="input" value="{{ $f->name }}" required maxlength="80" aria-label="Nombre"></td>
                    <td><input form="hf-{{ $f->id }}" name="abbr" class="input" value="{{ $f->abbr }}" required maxlength="4" aria-label="Sigla"></td>
                    <td><input form="hf-{{ $f->id }}" name="color" type="color" class="input" style="height:40px;padding:3px" value="{{ $f->color }}" aria-label="Color"></td>
                    <td class="small">{{ $f->faces ? 'Por caras' : 'Diente completo' }}</td>
                    <td><label class="check"><input form="hf-{{ $f->id }}" type="checkbox" name="active" value="1" @checked($f->active)><span class="sr-only">Activo</span></label></td>
                    <td><form id="hf-{{ $f->id }}" method="POST" action="{{ route('settings.findings.update', $f) }}">@csrf @method('PUT')<button class="btn sm">Guardar</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="small muted">Desactivar uno lo quita de la lista, pero los dientes que ya lo tienen lo siguen mostrando.</p>
    @endif
    <form method="POST" action="{{ route('settings.findings.store') }}" class="fgrid" style="align-items:end">
        @csrf
        <label class="field" for="hf-name"><span>Nuevo hallazgo</span><input id="hf-name" name="name" class="input" required maxlength="80" placeholder="Ej: Amalgama en mal estado" value="{{ old('name') }}"></label>
        <label class="field" for="hf-abbr"><span>Sigla (sale bajo el diente)</span><input id="hf-abbr" name="abbr" class="input" required maxlength="4" placeholder="Ej: AM" value="{{ old('abbr') }}"></label>
        <label class="field" for="hf-color"><span>Color</span><input id="hf-color" name="color" type="color" class="input" style="height:44px;padding:4px" value="{{ old('color', '#B5651D') }}"></label>
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0">
            <legend class="label" style="margin-bottom:6px">¿Cómo se marca?</legend>
            <label class="check"><input type="radio" name="faces" value="1" @checked(old('faces') === '1')><span>Por caras (pinta la cara del diente)</span></label>
            <label class="check"><input type="radio" name="faces" value="0" @checked(old('faces', '0') === '0')><span>Diente completo (sale la sigla)</span></label>
        </fieldset>
        <button class="btn" style="justify-self:start">Agregar hallazgo</button>
    </form>
</div>

<div class="card stack" id="plantillas">
    <div>
        <h2>Plantillas de órdenes</h2>
        <p class="small muted" style="margin-top:4px">Textos que repites en las órdenes en blanco: recetas, indicaciones después de un procedimiento, remisiones. Al hacer una orden eliges la plantilla y la ajustas si hace falta.</p>
    </div>
    @foreach ($orderTemplates as $t)
        <details>
            <summary style="cursor:pointer;font-weight:700;padding:8px 0">{{ $t->title }}</summary>
            <form method="POST" action="{{ route('settings.order-templates.update', $t) }}" class="stack" style="margin:8px 0 6px">
                @csrf @method('PUT')
                <label class="field" for="ot-{{ $t->id }}"><span>Título</span><input id="ot-{{ $t->id }}" name="title" class="input" value="{{ $t->title }}" required maxlength="150"></label>
                <label class="field" for="ob-{{ $t->id }}"><span>Texto</span><textarea id="ob-{{ $t->id }}" name="body" class="input" style="min-height:180px" required maxlength="5000">{{ $t->body }}</textarea></label>
                <button class="btn pri" style="align-self:flex-start">Guardar plantilla</button>
            </form>
            <form method="POST" action="{{ route('settings.order-templates.destroy', $t) }}" style="margin-bottom:14px">@csrf @method('DELETE')<button class="btn sm ghost danger">Eliminar plantilla</button></form>
        </details>
    @endforeach
    <details @if ($orderTemplates->isEmpty()) open @endif>
        <summary style="cursor:pointer;font-weight:700;padding:8px 0">+ Nueva plantilla</summary>
        <form method="POST" action="{{ route('settings.order-templates.store') }}" class="stack" style="margin-top:8px">
            @csrf
            <label class="field" for="ot-new"><span>Título</span><input id="ot-new" name="title" class="input" required maxlength="150" placeholder="Ej: Fórmula médica después de extracción"></label>
            <label class="field" for="ob-new"><span>Texto</span><textarea id="ob-new" name="body" class="input" style="min-height:160px" required maxlength="5000" placeholder="Ej: Ibuprofeno 400 mg, 1 tableta cada 8 horas por 3 días…"></textarea></label>
            <button class="btn" style="align-self:flex-start">Agregar plantilla</button>
        </form>
    </details>
</div>

<div class="card">
    <h2>Conexión con GoHighLevel</h2>
    <p class="muted">Queda abierta. El WhatsApp de cada paciente es su llave única, para enlazar los dos sistemas cuando se active (fase 4).</p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var cv = document.getElementById('sig-doc'); if (!cv) return;
    var drawn = false, drawing = false, last = null, ctx;
    function size() {
        var r = cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
        cv.width = r.width * dpr; cv.height = r.height * dpr;
        ctx = cv.getContext('2d'); ctx.scale(dpr, dpr);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#14232A';
    }
    size();
    var pt = function (e) { var b = cv.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
    cv.addEventListener('pointerdown', function (e) { drawing = true; last = pt(e); try { cv.setPointerCapture(e.pointerId); } catch (_) {} });
    cv.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = pt(e); ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke(); last = p; drawn = true;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) { cv.addEventListener(ev, function () { drawing = false; }); });
    document.getElementById('sig-clear').addEventListener('click', function () { size(); drawn = false; });
    document.getElementById('marca').addEventListener('submit', function () {
        if (!drawn) return;
        var scale = Math.min(1, 500 / cv.width), out = document.createElement('canvas');
        out.width = Math.round(cv.width * scale); out.height = Math.round(cv.height * scale);
        out.getContext('2d').drawImage(cv, 0, 0, out.width, out.height);
        document.getElementById('sig-data').value = out.toDataURL('image/png');
    });
})();
</script>
@endpush
