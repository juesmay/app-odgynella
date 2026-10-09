<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') · {{ auth()->user()->clinic->name ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=6">
</head>
<body>
@php
    $user = auth()->user();
    $nav = [
        ['today', 'Hoy', 'hoy', ['today']],
        ['agenda', 'Agenda', 'agenda', ['agenda', 'appointments.*']],
        ['quotes.index', 'Cotizaciones', 'cotizacion', ['quotes.*']],
        ['patients.index', 'Pacientes', 'pacientes', ['patients.*']],
        ['cash.index', 'Caja', 'caja', ['cash.*', 'payments.*']],
        ['receivables.index', 'Cartera', 'cartera', ['receivables.*']],
        ['expenses.index', 'Gastos', 'gastos', ['expenses.*']],
    ];
    if ($user->isDoctor()) {
        $nav[] = ['reports.index', 'Reportes', 'reportes', ['reports.*']];
        $nav[] = ['settings', 'Configuración', 'config', ['settings*']];
    }
@endphp
<div class="shell">
    <nav class="side" aria-label="Menú principal">
        <div class="brand">{{ mb_strtoupper($user->clinic->name) }}</div>
        <div class="brand-sub">{{ $user->clinic->doctor_name }}</div>
        @foreach ($nav as [$route, $label, $icon, $patterns])
            <a class="nav" href="{{ route($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                @include('partials.icon', ['name' => $icon])
                <span>{{ $label }}</span>
            </a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}" style="margin-top:auto">
            @csrf
            <button class="nav" type="submit">@include('partials.icon', ['name' => 'salir'])<span>Salir</span></button>
        </form>
    </nav>

    <div class="main">
        <header class="top">
            <div class="when">
                <small>{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</small>
                <strong>{{ $user->isDoctor() ? 'Hola, '.$user->firstName() : $user->name.' · Recepción' }}</strong>
                <a class="small muted" href="{{ route('account') }}" style="margin-left:8px">Mi cuenta</a>
            </div>
            @if (($allSedes ?? collect())->count() > 1)
                <form method="POST" action="{{ route('sede.switch') }}" class="ctl">
                    @csrf
                    <label for="sede-sel">Sede</label>
                    <select id="sede-sel" name="sede_id" class="input" onchange="this.form.submit()">
                        @foreach ($allSedes as $s)
                            <option value="{{ $s->id }}" @selected($currentSede?->id === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn sm">Cambiar</button></noscript>
                </form>
            @endif
        </header>

        <main class="view" id="view">
            <div class="wrap">
                @if ($errors->any())
                    <div class="errors" role="alert">
                        {{ $errors->count() === 1 ? $errors->first() : 'Revisa estos datos:' }}
                        @if ($errors->count() > 1)
                            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        @endif
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</div>

@if (session('ok'))
    <div class="flash" role="status" data-flash>{{ session('ok') }}</div>
@elseif (session('warn'))
    <div class="flash error" role="status" data-flash>{{ session('warn') }}</div>
@endif
<script>
    setTimeout(function () { var f = document.querySelector('[data-flash]'); if (f) f.hidden = true; }, 4000);
</script>
@stack('scripts')
</body>
</html>
