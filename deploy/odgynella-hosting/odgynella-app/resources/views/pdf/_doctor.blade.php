{{-- Bloque de la doctora (derecha del encabezado). --}}
<div class="label">{{ $clinic->doctor_title ?: 'Odontóloga' }}:</div>
<div class="b" style="font-size:11pt;margin-top:3pt">{{ preg_replace('/^Dra?\.\s*/u', '', $clinic->doctor_name) }}</div>
@if ($clinic->doctor_license)<div class="muted">Registro profesional {{ $clinic->doctor_license }}</div>@endif
@if ($clinic->phone)<div class="muted">Contacto: {{ $clinic->phone }}</div>@endif
@if ($clinic->email)<div class="muted">Correo: {{ $clinic->email }}</div>@endif
