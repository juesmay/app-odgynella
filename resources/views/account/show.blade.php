@extends('layouts.app')
@section('title', 'Mi cuenta')

@section('content')
<div class="head">
    <div>
        <h1>Mi cuenta</h1>
        <p class="sub">{{ $user->name }} · {{ $user->email }} · {{ $user->isDoctor() ? 'Doctora' : 'Asistente' }}</p>
    </div>
</div>

@if (session('temp_password'))
    @php [$tmpEmail, $tmpPass] = session('temp_password'); @endphp
    <div class="infobar" style="flex-wrap:wrap">
        <span>Contraseña temporal para <strong>{{ $tmpEmail }}</strong>: <strong class="mono" style="font-size:18px">{{ $tmpPass }}</strong>. Cópiala ahora: no se vuelve a mostrar. Al entrar, el sistema le pedirá cambiarla.</span>
    </div>
@endif

<div class="grid2">
    <form method="POST" action="{{ route('account.password') }}" class="card stack">
        @csrf @method('PUT')
        <h2>Cambiar contraseña</h2>
        @if ($user->must_change_password)
            <p class="small" style="color:var(--warn)">Estás usando una contraseña temporal. Cámbiala para poder seguir.</p>
        @endif
        <label class="field" for="current_password"><span>Contraseña actual</span>
            <input id="current_password" name="current_password" type="password" class="input" required autocomplete="current-password">
        </label>
        <label class="field" for="password"><span>Nueva contraseña</span>
            <input id="password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">
            <span class="small muted">Mínimo 8 caracteres, con letras y números.</span>
        </label>
        <label class="field" for="password_confirmation"><span>Repite la nueva contraseña</span>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required minlength="8" autocomplete="new-password">
        </label>
        <button class="btn pri" style="align-self:flex-start">Guardar contraseña</button>
    </form>

    <div class="card stack">
        <h2>Apariencia</h2>
        <p class="small muted">Se guarda en este equipo. Cada persona elige la suya.</p>
        <div class="dseg" role="group" aria-label="Apariencia">
            <button type="button" data-tema="claro">Claro</button>
            <button type="button" data-tema="oscuro">Oscuro</button>
        </div>
    </div>

    @if ($user->isDoctor())
        <div class="card stack">
            <h2>Equipo</h2>
            <div class="list">
                @foreach ($team as $u)
                    <div class="li">
                        <span class="grow"><strong>{{ $u->name }}</strong>{{ $u->id === $user->id ? ' (tú)' : '' }}<br>
                            <span class="small muted">{{ $u->email }} · {{ $u->isDoctor() ? 'Doctora' : 'Asistente' }}</span></span>
                        @unless ($u->active)<span class="chip c-cancel">Desactivado</span>@endunless
                        @if ($u->id !== $user->id)
                            <form method="POST" action="{{ route('team.reset', $u) }}">@csrf<button class="btn sm">Nueva contraseña</button></form>
                            <form method="POST" action="{{ route('team.toggle', $u) }}">@csrf<button class="btn sm {{ $u->active ? 'danger' : '' }}">{{ $u->active ? 'Desactivar' : 'Activar' }}</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('team.store') }}" class="stack" style="border-top:1px solid var(--line);padding-top:14px">
                @csrf
                <div class="label">Agregar a alguien</div>
                <div class="fgrid">
                    <label class="field" for="u-name"><span>Nombre</span><input id="u-name" name="name" class="input" required maxlength="120" value="{{ old('name') }}"></label>
                    <label class="field" for="u-email"><span>Correo</span><input id="u-email" name="email" type="email" class="input" required maxlength="150" value="{{ old('email') }}"></label>
                    <label class="field" for="u-role"><span>Rol</span>
                        <select id="u-role" name="role" class="input"><option value="asistente">Asistente</option><option value="doctora">Doctora</option></select>
                    </label>
                </div>
                <button class="btn" style="align-self:flex-start">Crear usuario</button>
                <p class="small muted">El sistema le crea una contraseña temporal que verás aquí una sola vez.</p>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btns = document.querySelectorAll('[data-tema]');
    function paint() {
        var dark = document.documentElement.dataset.theme === 'dark';
        btns.forEach(function (b) { b.setAttribute('aria-pressed', (b.dataset.tema === 'oscuro') === dark ? 'true' : 'false'); });
    }
    btns.forEach(function (b) {
        b.addEventListener('click', function () {
            var dark = b.dataset.tema === 'oscuro';
            if (dark) document.documentElement.dataset.theme = 'dark'; else delete document.documentElement.dataset.theme;
            try { localStorage.setItem('tema', b.dataset.tema); } catch (e) {}
            paint();
        });
    });
    paint();
})();
</script>
@endpush
