@extends('layouts.app')
@section('title', 'Nueva orden · '.$patient->name)
@use('App\Models\RadiologyOrder')

@section('content')
@include('patients._header')

<form method="POST" action="{{ route('orders.store', $patient) }}" class="grid-side">
    @csrf
    <div class="card stack">
        <h2>Estudios a realizar</h2>
        @php $old = collect(old('studies', []))->keyBy('name'); $i = 0; @endphp
        <div class="tblwrap"><table class="tbl compact" id="studies">
            <thead><tr><th>Estudio</th><th style="width:170px">Diente o zona</th><th class="r" style="width:80px">Cantidad</th></tr></thead>
            <tbody>
            @foreach (RadiologyOrder::STUDIES as $name => $zone)
                @php $o = $old->get($name); @endphp
                <tr>
                    <td><label class="check"><input type="checkbox" name="studies[{{ $i }}][name]" value="{{ $name }}" @checked($o)><span>{{ $name }}</span></label></td>
                    <td>@if ($zone)<input name="studies[{{ $i }}][zone]" class="input" value="{{ $o['zone'] ?? '' }}" maxlength="80" placeholder="Ej: 36, 46" aria-label="Diente o zona de {{ $name }}">@endif</td>
                    <td class="r"><input name="studies[{{ $i }}][qty]" class="input" style="text-align:right;width:64px" inputmode="numeric" value="{{ $o['qty'] ?? 1 }}" aria-label="Cantidad de {{ $name }}"></td>
                </tr>
                @php $i++; @endphp
            @endforeach
            @php $others = collect(old('studies', []))->reject(fn ($s) => array_key_exists($s['name'] ?? '', RadiologyOrder::STUDIES))->values(); @endphp
            @for ($k = 0; $k < 2; $k++)
                <tr>
                    <td><input name="studies[{{ $i }}][name]" class="input" value="{{ $others[$k]['name'] ?? '' }}" maxlength="120" placeholder="Otro estudio" aria-label="Otro estudio"></td>
                    <td><input name="studies[{{ $i }}][zone]" class="input" value="{{ $others[$k]['zone'] ?? '' }}" maxlength="80" aria-label="Diente o zona"></td>
                    <td class="r"><input name="studies[{{ $i }}][qty]" class="input" style="text-align:right;width:64px" inputmode="numeric" value="{{ $others[$k]['qty'] ?? 1 }}" aria-label="Cantidad"></td>
                </tr>
                @php $i++; @endphp
            @endfor
            </tbody>
        </table></div>

        <label class="field" for="indication"><span>Indicación clínica <span class="muted">(opcional)</span></span>
            <textarea id="indication" name="indication" class="input" maxlength="500" placeholder="Ej: valoración para diseño de sonrisa; descartar lesión periapical en 36">{{ old('indication') }}</textarea>
        </label>
    </div>

    <aside class="stack">
        <div class="card stack">
            <label class="field" for="center"><span>Centro radiológico</span>
                <input id="center" name="center" class="input" list="centers" value="{{ old('center', $centers[0] ?? '') }}" maxlength="150" placeholder="El de preferencia del paciente">
                <datalist id="centers">@foreach ($centers as $c)<option value="{{ $c }}">@endforeach</datalist>
            </label>
            <fieldset class="stack-sm" style="border:0;padding:0;margin:0">
                <legend class="label" style="margin-bottom:6px">Entrega de resultados</legend>
                @php $clinic = auth()->user()->clinic; $def = old('delivery', $clinic->email ? 'correo_doctora' : 'paciente_recoge'); @endphp
                @foreach (RadiologyOrder::DELIVERY as $key => $label)
                    <label class="check"><input type="radio" name="delivery" value="{{ $key }}" @checked($def === $key)>
                        <span>{{ $label }}@if ($key === 'correo_doctora' && $clinic->email) <span class="small muted">({{ $clinic->email }})</span>@elseif ($key === 'virtual_paciente') <span class="small muted">({{ $patient->email ?: 'sin correo en la ficha' }})</span>@endif</span></label>
                @endforeach
            </fieldset>
            <label class="field" for="notes"><span>Observaciones <span class="muted">(opcional)</span></span>
                <input id="notes" name="notes" class="input" value="{{ old('notes') }}" maxlength="500">
            </label>
            @unless ($hasSignature)
                <p class="small" style="color:var(--warn)">Tu firma todavía no está guardada. Dibújala una vez en <a href="{{ route('settings') }}#marca">Configuración</a> para que salga en las órdenes.</p>
            @endunless
            <button class="btn pri block">Crear orden</button>
        </div>
    </aside>
</form>
@endsection
