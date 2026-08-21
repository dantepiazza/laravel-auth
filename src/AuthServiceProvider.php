<?php

namespace DantePiazza\LaravelAuth;

use Illuminate\Support\ServiceProvider;
use DantePiazza\LaravelAuth\Http\Middleware\VerifyEmail;
use DantePiazza\LaravelAuth\Http\Middleware\EnsureSsoPermission;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../stubs/database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-auth');
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-auth.php', 'laravel-auth');

        if (empty(config('laravel-auth.account_types'))) {
            throw new \RuntimeException(
                '[laravel-auth] Debés publicar y configurar la config: php artisan vendor:publish --tag=laravel-auth-config'
            );
        }

        // Registrar alias del middleware para que el usuario pueda usarlo manualmente si lo necesita
        $this->app['router']->aliasMiddleware('auth.verify-email', VerifyEmail::class);

        // Middleware opt-in para RBAC de SSO (Fase 2) — el consumidor lo aplica donde lo necesite
        $this->app['router']->aliasMiddleware('sso.permission', EnsureSsoPermission::class);

        // Las rutas se cargan después de registrar el alias
        $this->loadRoutesFrom(__DIR__ . '/../routes/auth.php');

        // Pantallas Blade (login/registro/verificación/recuperar contraseña),
        // opt-in vía laravel-auth.web.enabled (default false) — no afecta a
        // quien no las active.
        if (config('laravel-auth.web.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../stubs/database/migrations/' => database_path('migrations'),
            ], 'laravel-auth-migrations');

            $this->publishes([
                __DIR__ . '/../config/laravel-auth.php' => config_path('laravel-auth.php'),
            ], 'laravel-auth-config');

            $this->publishes([
                __DIR__ . '/../routes/auth.php' => base_path('/routes/auth.php'),
            ], 'laravel-auth-routes');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-auth'),
            ], 'laravel-auth-views');

            // Tag granular: solo las pantallas Blade de auth (layout + vistas),
            // para quien quiera personalizarlas sin tocar las vistas de emails.
            $this->publishes([
                __DIR__ . '/../resources/views/auth' => resource_path('views/vendor/laravel-auth/auth'),
            ], 'laravel-auth-web-views');

            $this->publishes([
                __DIR__ . '/../routes/web.php' => base_path('/routes/auth-web.php'),
            ], 'laravel-auth-web-routes');
        }
    }
}