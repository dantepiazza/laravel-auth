@extends('laravel-auth::auth.layout')

@section('title', 'Restablecer contraseña')

@section('content')
    <form method="POST" action="{{ route('laravel-auth.web.password.restore', ['type' => $type]) }}">
        @csrf

        <div class="auth-field">
            <label for="identity">Email</label>
            <input type="text" id="identity" name="identity" value="{{ old('identity', $identity) }}" autofocus required>
        </div>

        <div class="auth-field">
            <label for="code">Código</label>
            <input type="text" id="code" name="code" value="{{ old('code', $code) }}" required>
        </div>

        <div class="auth-field">
            <label for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Restablecer contraseña</button>
        </div>
    </form>
@endsection
