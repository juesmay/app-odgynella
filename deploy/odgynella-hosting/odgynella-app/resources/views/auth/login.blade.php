<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<main class="login">
    <form method="POST" action="{{ url('/login') }}" class="card stack">
        @csrf
        <div>
            <h1>Odgynella</h1>
            <p class="muted" style="margin-top:4px">Entra con tu correo y contraseña.</p>
        </div>
        @if ($errors->any())
            <div class="errors" role="alert">{{ $errors->first() }}</div>
        @endif
        <label class="field" for="email"><span>Correo</span>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        <label class="field" for="password"><span>Contraseña</span>
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1"><span>Mantener la sesión abierta en este equipo</span></label>
        <button class="btn pri block">Entrar</button>
    </form>
</main>
</body>
</html>
