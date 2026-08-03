<?php

namespace DantePiazza\LaravelAuth\Tests;

/**
 * Variante de TestCase que bootea con el modo SSO activado, necesario porque
 * las rutas de handshake (routes/auth.php) se registran una sola vez durante
 * el boot de la app, según el valor de laravel-auth.sso.mode en ese momento.
 */
class SsoTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laravel-auth.sso.mode', 'consumer');
        $app['config']->set('laravel-auth.sso.default_type', 'users');
        $app['config']->set('laravel-auth.sso.secret', 'test-secret-not-app-key');
        $app['config']->set('laravel-auth.sso.provider_url', 'https://provider.example.test');
        $app['config']->set('laravel-auth.sso.allowed_consumers', ['consumer.example.test']);
    }
}
