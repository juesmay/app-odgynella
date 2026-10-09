@extends('layouts.app')
@section('title', 'Nuevo paciente')

@section('content')
<div class="head">
    <div>
        <h1>Nuevo paciente</h1>
        <p class="sub">El WhatsApp es la llave del paciente: no se puede repetir y servirá para enlazarlo con GoHighLevel.</p>
    </div>
</div>

<form method="POST" action="{{ route('patients.store') }}" class="card stack" style="max-width:720px">
    @csrf
    @include('patients._fields')
    <label class="check"><input type="checkbox" name="data_consent" value="1" @checked(old('data_consent')) required>
        <span>La paciente autoriza el tratamiento de sus datos personales y de salud (Ley 1581 de 2012).</span></label>
    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ route('patients.index') }}">Cancelar</a>
        <button class="btn pri">Crear paciente</button>
    </div>
</form>
@endsection
