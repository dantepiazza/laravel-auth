@extends('laravel-auth::auth.layout')

@section('title', 'Verificar email')
@section('subtitle', 'Ingresá el código de 6 dígitos que te enviamos por email.')

@section('content')
    <form method="POST" action="{{ route('laravel-auth.web.email.verify', ['type' => $type]) }}">
        @csrf

        <div class="auth-field">
            <label for="identity">Email</label>
            <input type="text" id="identity" name="identity" value="{{ old('identity', $identity) }}" autofocus required>
        </div>

        <div class="auth-field">
            <label for="code">Código</label>
            <input type="text" id="code" name="code" maxlength="6" value="{{ old('code') }}" required>
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Verificar</button>
        </div>
    </form>

    <form method="POST" action="{{ route('laravel-auth.web.email.resend', ['type' => $type]) }}" style="margin-top: 12px;">
        @csrf
        <input type="hidden" name="identity" value="{{ $identity }}">
        <div class="auth-links">
            <button type="submit" class="auth-button" style="background: transparent; color: var(--auth-primary); padding: 0;">Reenviar código</button>
        </div>
    </form>
@endsection
