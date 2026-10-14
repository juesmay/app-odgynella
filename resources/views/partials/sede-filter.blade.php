{{-- Aviso de qué sede se está viendo en una lista, con el cambio a "todas". Requiere $sedeFilter (id o null). --}}
@php $sedeNow = \App\Support\CurrentSede::get(); $multi = \App\Models\Sede::where('active', true)->count() > 1; @endphp
@if ($multi && $sedeNow)
    <div class="sedebar">
        @if ($sedeFilter)
            <span>Viendo <strong>{{ $sedeNow->name }}</strong></span>
            <a href="{{ request()->fullUrlWithQuery(['sedes' => 'todas', 'page' => null]) }}">Ver todas las sedes</a>
        @else
            <span>Viendo <strong>todas las sedes</strong></span>
            <a href="{{ request()->fullUrlWithQuery(['sedes' => null, 'page' => null]) }}">Solo {{ $sedeNow->name }}</a>
        @endif
    </div>
@endif
