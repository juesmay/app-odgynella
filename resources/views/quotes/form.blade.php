@extends('layouts.app')
@section('title', $quote->exists ? 'Editar '.$quote->code() : 'Nueva cotización')
@use('App\Support\Time')

@section('content')
@php
    $rows = old('items', $items);
    if (empty($rows)) { $rows = [['service_id' => null, 'description' => '', 'teeth' => '', 'quantity' => 1, 'unit_price' => '']]; }
@endphp
<div class="head">
    <div>
        <h1>{{ $quote->exists ? 'Editar cotización '.$quote->code() : 'Nueva cotización' }}</h1>
        <p class="sub">Ve agregando lo que la persona te cuenta durante la asesoría. Al guardar puedes descargar el PDF.</p>
    </div>
</div>

<form method="POST" action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}" class="stack" id="quote-form">
    @csrf
    @if ($quote->exists) @method('PUT') @endif

    {{-- 1. Quién --}}
    <section class="card stack">
        <h2>Persona</h2>
        @if ($patient)
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <div class="row">
                <strong>{{ $patient->name }}</strong>
                <span class="muted">{{ $patient->phone ? 'WhatsApp '.$patient->phone : 'Sin WhatsApp' }}</span>
                <span class="chip {{ $patient->stageClass() }}">{{ $patient->stageLabel() }}</span>
            </div>
        @else
            <p class="small muted">Basta con el nombre y el WhatsApp. Si ese WhatsApp ya está registrado, la cotización se le suma a esa persona.</p>
            <div class="fgrid">
                <label class="field" for="name"><span>Nombre</span>
                    <input id="name" name="name" class="input" value="{{ old('name') }}" required maxlength="150" autocomplete="off">
                </label>
                <label class="field" for="phone"><span>WhatsApp</span>
                    <input id="phone" name="phone" class="input" inputmode="tel" value="{{ old('phone') }}" required maxlength="40" placeholder="Ej: 300 123 4567">
                </label>
                <label class="field" for="city"><span>Ciudad y país</span>
                    <input id="city" name="city" class="input" value="{{ old('city') }}" maxlength="120">
                </label>
                <label class="field" for="source"><span>Cómo llegó</span>
                    <select id="source" name="source" class="input">
                        <option value="">Sin dato</option>
                        @foreach (\App\Models\Patient::SOURCES as $s)
                            <option @selected(old('source') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endif
    </section>

    {{-- 2. Qué necesita --}}
    <section class="card stack">
        <div class="card-h"><h2>Tratamientos</h2><span class="small muted">Elige del catálogo o escribe uno a mano</span></div>
        <div class="tblwrap">
            <table class="tbl" id="items">
                <thead><tr><th style="min-width:220px">Tratamiento</th><th style="width:120px">Dientes</th><th class="r" style="width:80px">Cant.</th><th class="r" style="width:150px">Valor unitario</th><th class="r" style="width:130px">Total</th><th style="width:60px"></th></tr></thead>
                <tbody>
                @foreach ($rows as $i => $r)
                    @include('quotes._row', ['i' => $i, 'r' => $r])
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="row">
            <label class="field" for="add-service" style="flex:1;min-width:220px"><span>Agregar del catálogo</span>
                <select id="add-service" class="input">
                    <option value="">Elige un servicio o paquete…</option>
                    @foreach ($services as $s)
                        <option value="{{ $s['id'] }}" data-name="{{ $s['name'] }}" data-price="{{ $s['price'] }}">{{ $s['name'] }}{{ $s['price'] ? ' · '.Time::money($s['price']) : '' }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="btn" id="add-blank" style="align-self:flex-end">+ Fila en blanco</button>
        </div>

        <div class="fgrid">
            <label class="field" for="discount_label"><span>Descuento o promoción (opcional)</span>
                <input id="discount_label" name="discount_label" class="input" value="{{ old('discount_label', $quote->discount_label) }}" placeholder="Ej: Promoción 2x1 de octubre" maxlength="120">
            </label>
            <label class="field" for="discount_amount"><span>Valor del descuento</span>
                <input id="discount_amount" name="discount_amount" class="input" inputmode="numeric" value="{{ old('discount_amount', $quote->discount_amount ?: '') }}" placeholder="0">
            </label>
        </div>

        <div class="receipt" style="max-width:420px;margin-left:auto">
            <div class="kv"><span>Subtotal</span><b id="t-sub">$0</b></div>
            <div class="kv"><span>Descuento</span><b id="t-disc">$0</b></div>
            <hr class="sep">
            <div class="kv" style="font-size:20px"><span>Total</span><b id="t-total">$0</b></div>
        </div>
    </section>

    {{-- 3. Notas --}}
    <section class="card stack">
        <h2>Notas</h2>
        <div class="fgrid">
            <label class="field" for="internal_notes"><span>Lo que contó la persona (solo para el consultorio)</span>
                <textarea id="internal_notes" name="internal_notes" class="input" placeholder="Qué quiere mejorar, qué le preocupa, fechas en que puede venir…">{{ old('internal_notes', $quote->internal_notes) }}</textarea>
            </label>
            <label class="field" for="patient_notes"><span>Observaciones para la paciente (salen en el PDF)</span>
                <textarea id="patient_notes" name="patient_notes" class="input" placeholder="Ej: incluye diseño digital de sonrisa antes de empezar.">{{ old('patient_notes', $quote->patient_notes) }}</textarea>
            </label>
        </div>
    </section>

    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ $quote->exists ? route('quotes.show', $quote) : url()->previous() }}">Cancelar</a>
        <button class="btn pri">{{ $quote->exists ? 'Guardar cambios' : 'Guardar cotización' }}</button>
    </div>
</form>

<template id="row-tpl">
    @include('quotes._row', ['i' => '__I__', 'r' => ['service_id' => null, 'description' => '', 'teeth' => '', 'quantity' => 1, 'unit_price' => '', 'price_options' => '']])
</template>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.querySelector('#items tbody'), tpl = document.getElementById('row-tpl').innerHTML,
        next = body.rows.length, add = document.getElementById('add-service');
    var num = function (v) { return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0; };
    var money = function (n) { return '$' + Math.round(n).toLocaleString('es-CO'); };

    function recalc() {
        var sub = 0;
        Array.prototype.forEach.call(body.rows, function (tr) {
            var q = Math.max(1, num(tr.querySelector('[data-f=quantity]').value)), p = num(tr.querySelector('[data-f=unit_price]').value);
            var line = tr.querySelector('[data-f=description]').value.trim() ? q * p : 0,
                opts = tr.querySelector('[data-f=price_options]').value.trim();
            tr.querySelector('[data-line]').textContent = !line && opts ? 'Según el caso' : money(line);
            sub += line;
        });
        var d = Math.min(num(document.getElementById('discount_amount').value), sub);
        document.getElementById('t-sub').textContent = money(sub);
        document.getElementById('t-disc').textContent = d ? '- ' + money(d) : '$0';
        document.getElementById('t-total').textContent = money(sub - d);
    }
    function addRow(svc) {
        var holder = document.createElement('tbody');
        holder.innerHTML = tpl.replace(/__I__/g, next++);
        var tr = holder.firstElementChild;
        if (svc) {
            tr.querySelector('[data-f=service_id]').value = svc.id;
            tr.querySelector('[data-f=description]').value = svc.name;
            tr.querySelector('[data-f=unit_price]').value = svc.price;
        }
        // Si la única fila está vacía, la reemplaza en lugar de dejarla colgando.
        var only = body.rows.length === 1 && !body.rows[0].querySelector('[data-f=description]').value.trim();
        if (only && svc) body.innerHTML = '';
        body.appendChild(tr);
        recalc();
        if (!svc) tr.querySelector('[data-f=description]').focus();
    }
    add.addEventListener('change', function () {
        var o = add.options[add.selectedIndex];
        if (!o.value) return;
        addRow({ id: o.value, name: o.dataset.name, price: o.dataset.price });
        add.value = '';
    });
    document.getElementById('add-blank').addEventListener('click', function () { addRow(null); });
    body.addEventListener('click', function (e) {
        var b = e.target.closest('[data-remove]');
        if (!b) return;
        var tr = b.closest('tr');
        if (body.rows.length > 1) tr.remove();
        else tr.querySelectorAll('input').forEach(function (i) { i.value = i.dataset.f === 'quantity' ? '1' : ''; });
        recalc();
    });
    document.getElementById('quote-form').addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush
