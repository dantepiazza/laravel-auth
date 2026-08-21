@extends('laravel-auth::auth.layout')

@section('title', 'Recuperar contraseña')
@section('subtitle', 'Te enviaremos un código de recuperación por email.')

@section('content')
    <form method="POST" action="{{ route('laravel-auth.web.password.recover', ['type' => $type]) }}">
        @csrf

        <div class="auth-field">
            <label for="identity">Email</label>
            <input type="text" id="identity" name="identity" value="{{ old('identity') }}" autofocus required>
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Enviar código</button>
        </div>

        <div class="auth-links">
            <a href="{{ route('laravel-auth.web.password.restore.show', ['type' => $type]) }}">Ya tengo un código</a>
            <a href="{{ route('laravel-auth.web.login.show', ['type' => $type]) }}">Volver a iniciar sesión</a>
        </div>
    </form>
@endsection
