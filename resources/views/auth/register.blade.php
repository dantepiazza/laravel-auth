@extends('laravel-auth::auth.layout')

@section('title', 'Crear cuenta')

@section('content')
    <form method="POST" action="{{ route('laravel-auth.web.register', ['type' => $type]) }}">
        @csrf

        <div class="auth-field">
            <label for="identity">Email</label>
            <input type="text" id="identity" name="identity" value="{{ old('identity') }}" autofocus required>
        </div>

        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Crear cuenta</button>
        </div>

        <div class="auth-links">
            <a href="{{ route('laravel-auth.web.login.show', ['type' => $type]) }}">Ya tengo una cuenta</a>
        </div>
    </form>
@endsection
