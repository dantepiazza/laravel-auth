<?php

use Illuminate\Support\Facades\Route;
use DantePiazza\LaravelAuth\Http\Controllers\AuthController;
use DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController;

Route::prefix('v1/{type}')->group(function () {
    $prefix = config('laravel-auth.prefix', 'auth');

    Route::prefix($prefix)->group(function () {

        // Sesión
        Route::post('login',    [AuthController::class, 'login'])->name('laravel-auth.login');
        Route::post('refresh',  [AuthController::class, 'refresh'])->name('laravel-auth.refresh');

        // Registro
        Route::post('register',                  [AuthController::class, 'register'])->name('laravel-auth.register');
        Route::post('register/check-identity',   [AuthController::class, 'checkIdentity'])->name('laravel-auth.register.check-identity');

        // Verificación de email
        Route::post('email/verify',              [AuthController::class, 'verifyEmail'])->name('laravel-auth.email.verify');
        Route::post('email/resend',              [AuthController::class, 'resendVerificationCode'])->name('laravel-auth.email.resend');

        // Contraseña
        Route::post('password/recover',          [AuthController::class, 'recoverPassword'])->name('laravel-auth.password.recover');
        Route::post('password/restore',          [AuthController::class, 'restorePassword'])->name('laravel-auth.password.restore');

        // Rutas protegidas. auth.verify-email siempre se registra, pero el propio
        // middleware es un no-op si email_verification.blocking está desactivado
        // (así el toggle funciona en runtime y no solo al boot de la app).
        Route::middleware(['auth:sanctum', 'auth.verify-email'])->group(function () {
            Route::get('current',          [AuthController::class, 'current'])->name('laravel-auth.current');
            Route::post('logout',          [AuthController::class, 'logout'])->name('laravel-auth.logout');
            Route::post('password/change', [AuthController::class, 'changePassword'])->name('laravel-auth.password.change');

            // Gestión de sesiones (dispositivos) — Fase 3 de SSO, útil también sin SSO activado
            Route::get('sessions',          [AuthController::class, 'listSessions'])->name('laravel-auth.sessions.index');
            Route::delete('sessions/{id}',  [AuthController::class, 'revokeSession'])->name('laravel-auth.sessions.revoke');
            Route::delete('sessions',       [AuthController::class, 'revokeAllSessions'])->name('laravel-auth.sessions.revoke-all');
        });
    });
});

// Handshake cross-domain (Fase 4) — solo se registra si el modo SSO está activo.
if (in_array(config('laravel-auth.sso.mode'), ['provider', 'consumer'], true)) {
    Route::prefix('v1/sso')->group(function () {
        Route::get('redirect', [SsoHandshakeController::class, 'redirectToProvider'])->name('laravel-auth.sso.redirect');
        Route::get('handshake', [SsoHandshakeController::class, 'completeHandshake'])->middleware('auth:sanctum')->name('laravel-auth.sso.handshake');
        Route::get('callback', [SsoHandshakeController::class, 'receiveHandshake'])->name('laravel-auth.sso.callback');
    });
}
