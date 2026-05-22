<?php

use Illuminate\Support\Facades\Route;
use DantePiazza\LaravelAuth\Http\Controllers\AuthController;

Route::prefix('v1/{type}')->group(function () {
    $prefix = config('laravel-auth.prefix', 'auth');

    Route::prefix($prefix)->group(function () {

        // Sesión
        Route::post('login',    [AuthController::class, 'login']);
        Route::post('refresh',  [AuthController::class, 'refresh']);

        // Registro
        Route::post('register',                  [AuthController::class, 'register']);
        Route::post('register/check-identity',   [AuthController::class, 'checkIdentity']);

        // Verificación de email
        Route::post('email/verify',              [AuthController::class, 'verifyEmail']);
        Route::post('email/resend',              [AuthController::class, 'resendVerificationCode']);

        // Contraseña
        Route::post('password/recover',          [AuthController::class, 'recoverPassword']);
        Route::post('password/restore',          [AuthController::class, 'restorePassword']);

        // Rutas protegidas — se agrega verify.email automáticamente si blocking está activo
        $protectedMiddleware = array_filter([
            'auth:sanctum',
            config('laravel-auth.email_verification.blocking') ? 'auth.verify-email' : null,
        ]);
 
        Route::middleware($protectedMiddleware)->group(function () {
            Route::get('current',          [AuthController::class, 'current']);
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('password/change', [AuthController::class, 'changePassword']);
        });
    });
});
