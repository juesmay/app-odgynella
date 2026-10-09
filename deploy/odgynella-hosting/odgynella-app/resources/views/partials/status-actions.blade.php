{{-- Botones del siguiente paso de una cita. Requiere $a (Appointment). --}}
@php
    $btn = function (string $status, string $label, string $cls = '') use ($a) {
        return '<form method="POST" action="'.route('appointments.status', $a).'">'.csrf_field().method_field('PATCH')
            .'<input type="hidden" name="status" value="'.$status.'"><button class="btn sm '.$cls.'">'.e($label).'</button></form>';
    };
@endphp
<div class="acts">
    @if ($a->status === 'agendada')
        {!! $btn('confirmada', 'Confirmó') !!}
        {!! $btn('en_sala', 'Llegó', 'pri') !!}
    @elseif ($a->status === 'confirmada')
        {!! $btn('en_sala', 'Llegó', 'pri') !!}
    @elseif ($a->status === 'en_sala')
        {!! $btn('atendida', 'Atendida', 'pri') !!}
    @elseif ($a->status === 'atendida')
        <a class="btn sm" href="{{ route('payments.create', ['paciente' => $a->patient_id]) }}">Cobrar</a>
    @endif
    @if (in_array($a->status, ['agendada', 'confirmada'], true))
        {!! $btn('no_vino', 'No vino', 'ghost') !!}
        {!! $btn('cancelada', 'Cancelar', 'ghost danger') !!}
    @endif
    @if (in_array($a->status, ['no_vino', 'cancelada'], true))
        <a class="btn sm" href="{{ route('appointments.create', ['paciente' => $a->patient_id]) }}">Reagendar</a>
    @endif
</div>
