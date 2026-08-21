<?php

use Illuminate\Support\Facades\Route;
use DantePiazza\LaravelAuth\Http\Controllers\AuthWebController;

// Pantallas Blade equivalentes a la API, bajo {type}/auth/... (sin "v1":
// esto es HTML servido al navegador, no un endpoint versionado).
Route::prefix('{type}')->group(function () {
    $prefix = config('laravel-auth.prefix', 'auth');

    // Nota: no se usan los middlewares 'guest'/'auth' de Laravel porque
    // resuelven contra el guard "web" fijo, y acá el guard de sesión puede
    // variar por {type} (account_types.{type}.web_guard). El chequeo de
    // "hay sesión activa" para las rutas protegidas se hace a mano en
    // AuthWebController (ver changePassword()).
    Route::prefix($prefix)->name('laravel-auth.web.')->group(function () {
        Route::get('login',  [AuthWebController::class, 'showLogin'])->name('login.show');
        Route::post('login', [AuthWebController::class, 'login'])->name('login');

        Route::get('register',  [AuthWebController::class, 'showRegister'])->name('register.show');
        Route::post('register', [AuthWebController::class, 'register'])->name('register');

        Route::get('password/recover',  [AuthWebController::class, 'showRecoverPassword'])->name('password.recover.show');
        Route::post('password/recover', [AuthWebController::class, 'recoverPassword'])->name('password.recover');

        Route::get('password/restore',  [AuthWebController::class, 'showRestorePassword'])->name('password.restore.show');
        Route::post('password/restore', [AuthWebController::class, 'restorePassword'])->name('password.restore');

        Route::get('email/verify',   [AuthWebController::class, 'showVerifyEmail'])->name('email.verify.show');
        Route::post('email/verify',  [AuthWebController::class, 'verifyEmail'])->name('email.verify');
        Route::post('email/resend',  [AuthWebController::class, 'resendVerificationCode'])->name('email.resend');

        Route::post('logout', [AuthWebController::class, 'logout'])->name('logout');

        Route::post('password/change', [AuthWebController::class, 'changePassword'])->name('password.change');
    });
});
