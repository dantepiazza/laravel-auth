@extends('laravel-auth::auth.layout')

@section('title', 'Iniciar sesión')

@section('content')
    <form method="POST" action="{{ route('laravel-auth.web.login', ['type' => $type]) }}">
        @csrf

        <div class="auth-field">
            <label for="identity">Usuario</label>
            <input type="text" id="identity" name="identity" value="{{ old('identity') }}" autofocus required>
        </div>

        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Ingresar</button>
        </div>

        <div class="auth-links">
            <a href="{{ route('laravel-auth.web.password.recover.show', ['type' => $type]) }}">Olvidé mi contraseña</a>
            <a href="{{ route('laravel-auth.web.register.show', ['type' => $type]) }}">Crear cuenta</a>
        </div>
    </form>
@endsection
