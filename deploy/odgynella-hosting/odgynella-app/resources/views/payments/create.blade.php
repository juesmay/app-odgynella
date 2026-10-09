@extends('layouts.app')
@section('title', 'Registrar pago')

@section('content')
<div class="head">
    <div>
        <h1>Registrar pago</h1>
        <p class="sub">Sede {{ $sede?->name }}. El recibo sale con número consecutivo.</p>
    </div>
</div>

@if (! $cashOpen)
    <form method="POST" action="{{ route('cash.open') }}" class="card stack" style="max-width:560px">
        @csrf
        <div class="infobar">La caja de {{ $sede?->name }} está cerrada. Ábrela primero para que el pago entre al cuadre del día.</div>
        <input type="hidden" name="then" value="pago">
        <input type="hidden" name="paciente" value="{{ $selectedPatient }}">
        <label class="field" for="opening_amount"><span>Base en efectivo</span>
            <input id="opening_amount" name="opening_amount" class="input" inputmode="numeric" value="200000" required>
        </label>
        <button class="btn pri">Abrir caja y continuar</button>
    </form>
@else
    <form method="POST" action="{{ route('payments.store') }}" class="card stack" style="max-width:640px">
        @csrf
        <div class="fgrid">
            <label class="field full" for="patient_id"><span>Paciente</span>
                <select id="patient_id" name="patient_id" class="input" required>
                    <option value="">Elige un paciente</option>
                    @foreach ($patients as $p)
                        <option value="{{ $p->id }}" @selected(old('patient_id', $selectedPatient) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field full" for="quote_id" id="plan-field" hidden><span>Aplicar a</span>
                <select id="quote_id" name="quote_id" class="input"></select>
                <span class="small muted" id="plan-help"></span>
            </label>
            <label class="field full" for="concept" id="concept-field"><span>Concepto</span>
                <input id="concept" name="concept" class="input" value="{{ old('concept', 'Valoración') }}" maxlength="200" list="concepts">
                <datalist id="concepts">
                    <option value="Valoración"><option value="Abono a tratamiento"><option value="Limpieza"><option value="Aclaramiento"><option value="Control">
                </datalist>
            </label>
            <label class="field" for="amount"><span>Valor recibido</span>
                <input id="amount" name="amount" class="input" inputmode="numeric" value="{{ old('amount') }}" required placeholder="Ej: 30000">
            </label>
            <label class="field" for="method"><span>Medio de pago</span>
                <select id="method" name="method" class="input">
                    @foreach (\App\Models\Payment::METHODS as $m)
                        <option @selected(old('method') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="small muted">Si el paciente tiene un tratamiento aceptado, el abono se descuenta de su saldo. Valoraciones, controles y otros cobros sueltos van como “otro concepto”.</p>
        <div class="row" style="justify-content:flex-end">
            <a class="btn" href="{{ url()->previous() }}">Cancelar</a>
            <button class="btn pri">Registrar y ver recibo</button>
        </div>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var plans = @json($plans), money = function (n) { return '$' + Number(n).toLocaleString('es-CO'); };
    var patient = document.getElementById('patient_id'), field = document.getElementById('plan-field'),
        sel = document.getElementById('quote_id'), help = document.getElementById('plan-help'),
        concept = document.getElementById('concept'), wanted = @json(old('patient_id') ? (string) old('quote_id', '') : ($selectedQuote ? (string) $selectedQuote : null));
    if (!patient || !sel) return;

    function fill() {
        var list = plans[patient.value] || [];
        sel.innerHTML = '';
        list.forEach(function (p) {
            var o = new Option(p.code + ' · saldo ' + money(p.balance), p.id);
            o.dataset.help = 'Plan de ' + money(p.total) + '. Realizado ' + money(p.done) + ', pagado ' + money(p.paid) + '.'
                + (p.owed > 0 ? ' Debe ' + money(p.owed) + ' de lo ya hecho.' : '');
            sel.add(o);
        });
        sel.add(new Option('Otro concepto (valoración, control, producto…)', ''));
        sel.selectedIndex = 0;
        if (wanted !== null) { sel.value = wanted; wanted = null; if (sel.selectedIndex < 0) sel.selectedIndex = 0; }
        field.hidden = list.length === 0;
        sync();
    }
    function sync() {
        var o = sel.options[sel.selectedIndex], isPlan = !field.hidden && sel.value !== '';
        help.textContent = isPlan ? o.dataset.help : '';
        document.getElementById('concept-field').hidden = isPlan;
        concept.required = !isPlan;
        if (isPlan) concept.value = '';
        else if (!concept.value) concept.value = 'Valoración';
    }
    patient.addEventListener('change', fill);
    sel.addEventListener('change', sync);
    fill();
})();
</script>
@endpush
