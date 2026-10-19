<?php

use DantePiazza\LaravelAuth\Tests\SsoTestCase;
use DantePiazza\LaravelAuth\Support\SsoHandshakeEncryptor;
use DantePiazza\LaravelAuth\Services\SsoHandshakeService;
use DantePiazza\LaravelApiResponse\Exceptions\ResponseException;

uses(SsoTestCase::class);

beforeEach(function () {
    config(['laravel-auth.sso.handshake_ttl' => 60]);
});

describe('SsoHandshakeEncryptor', function () {

    it('cifra y descifra un payload correctamente', function () {
        $encryptor = new SsoHandshakeEncryptor();

        $token   = $encryptor->encrypt(['foo' => 'bar']);
        $payload = $encryptor->decrypt($token);

        expect($payload['foo'])->toBe('bar');
    });

    it('falla si el secret no está configurado', function () {
        config(['laravel-auth.sso.secret' => null]);

        expect(fn () => new SsoHandshakeEncryptor())
            ->toThrow(ResponseException::class);
    });

    it('rechaza un token expirado según el TTL', function () {
        $encryptor = new SsoHandshakeEncryptor();

        $token = $encryptor->encrypt(['issued_at' => now()->subMinutes(5)->timestamp]);

        expect(fn () => $encryptor->decrypt($token, 60))
            ->toThrow(ResponseException::class);
    });

    it('rechaza un token cifrado con un secret distinto', function () {
        $encryptor = new SsoHandshakeEncryptor();
        $token     = $encryptor->encrypt(['foo' => 'bar']);

        config(['laravel-auth.sso.secret' => 'otro-secret-completamente-distinto']);
        $otherEncryptor = new SsoHandshakeEncryptor();

        expect(fn () => $otherEncryptor->decrypt($token))
            ->toThrow(ResponseException::class);
    });
});

describe('SsoHandshakeService', function () {

    it('arma la URL de redirección al provider con el token cifrado', function () {
        $service = app(SsoHandshakeService::class);

        $url = $service->buildProviderRedirectUrl('consumer.example.test', '/dashboard');

        expect($url)->toStartWith('https://provider.example.test/');
        expect($url)->toContain('sso_handshake=');
    });

    it('por defecto resuelve la ruta nombrada "login" (convención estándar de Laravel), no la ruta API del paquete', function () {
        $service = app(SsoHandshakeService::class);

        $expectedPath = route('login', ['type' => config('laravel-auth.sso.default_type')], false);

        $url = $service->buildProviderRedirectUrl('consumer.example.test', '/dashboard');

        expect($url)->toContain(ltrim($expectedPath, '/'));
        expect($url)->not->toContain('laravel-auth');
    });

    it('usa provider_login_route en vez de la ruta API cuando el host la configura', function () {
        config(['laravel-auth.sso.provider_login_route' => 'laravel-auth.sso.callback']);

        $service = app(SsoHandshakeService::class);

        $expectedPath = route('laravel-auth.sso.callback', ['type' => config('laravel-auth.sso.default_type')], false);

        $url = $service->buildProviderRedirectUrl('consumer.example.test', '/dashboard');

        expect($url)->toContain(ltrim($expectedPath, '/'));
    });

    it('valida el dominio consumidor contra la allowlist', function () {
        $encryptor = app(SsoHandshakeEncryptor::class);
        $service   = app(SsoHandshakeService::class);

        $token = $encryptor->encrypt([
            'consumer' => 'no-permitido.example.test',
            'redirect' => '/',
            'callback' => 'https://no-permitido.example.test/api/v1/sso/callback',
        ]);

        expect(fn () => $service->validateIncomingRequest($token))
            ->toThrow(ResponseException::class);
    });

    it('rechaza el token si el callback no pertenece al dominio consumidor', function () {
        $encryptor = app(SsoHandshakeEncryptor::class);
        $service   = app(SsoHandshakeService::class);

        $token = $encryptor->encrypt([
            'consumer' => 'consumer.example.test',
            'redirect' => '/',
            'callback' => 'https://atacante.example.test/callback',
        ]);

        expect(fn () => $service->validateIncomingRequest($token))
            ->toThrow(ResponseException::class);
    });

    it('acepta un token válido con callback correcto', function () {
        $encryptor = app(SsoHandshakeEncryptor::class);
        $service   = app(SsoHandshakeService::class);

        $token = $encryptor->encrypt([
            'consumer' => 'consumer.example.test',
            'redirect' => '/dashboard',
            'callback' => 'https://consumer.example.test/api/v1/sso/callback',
        ]);

        $payload = $service->validateIncomingRequest($token);

        expect($payload['consumer'])->toBe('consumer.example.test');
        expect($payload['redirect'])->toBe('/dashboard');
    });

    it('redondea tokens de acceso/refresco de ida y vuelta entre provider y consumer', function () {
        $service = app(SsoHandshakeService::class);

        $responseToken = $service->buildResponseToken('access-123', 'refresh-456');
        $tokens        = $service->acceptOnConsumer($responseToken);

        expect($tokens['access_token'])->toBe('access-123');
        expect($tokens['refresh_token'])->toBe('refresh-456');
    });
});

describe('app, consumer_domain y desvío por email sin verificar', function () {

    it('manda ?app= al login del provider y lo guarda en el token', function () {
        config(['laravel-auth.sso.app' => 'hostbi']);

        $url = app(SsoHandshakeService::class)->buildProviderRedirectUrl('consumer.example.test', '/');

        expect($url)->toContain('&app=hostbi');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $payload = app(SsoHandshakeEncryptor::class)->decrypt($query['sso_handshake']);

        expect($payload['app'])->toBe('hostbi');
    });

    it('sin app configurada no agrega ?app=', function () {
        config(['laravel-auth.sso.app' => null]);

        expect(app(SsoHandshakeService::class)->buildProviderRedirectUrl('consumer.example.test', '/'))
            ->not->toContain('app=');
    });

    it('usa consumer_domain (host o URL) en vez del host de APP_URL', function () {
        config([
            'app.url' => 'https://hostbi.clousis.net',
            'laravel-auth.sso.consumer_domain' => 'https://panel.hostbi.net',
        ]);

        $response = app(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class)
            ->redirectToProvider(\Illuminate\Http\Request::create('/v1/sso/redirect'));

        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

        expect(app(SsoHandshakeEncryptor::class)->decrypt($query['sso_handshake'])['consumer'])->toBe('panel.hostbi.net');
    });

    it('sin consumer_domain sigue usando el host de APP_URL', function () {
        config(['app.url' => 'https://hostbi.clousis.net', 'laravel-auth.sso.consumer_domain' => null]);

        $response = app(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class)
            ->redirectToProvider(\Illuminate\Http\Request::create('/v1/sso/redirect'));

        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

        expect(app(SsoHandshakeEncryptor::class)->decrypt($query['sso_handshake'])['consumer'])->toBe('hostbi.clousis.net');
    });

    it('un usuario sin verificar va a la pantalla de verificación con el handshake', function () {
        config([
            'laravel-auth.sso.unverified_route' => 'gw.verify',
            'laravel-auth.email_verification.enabled' => true,
            'laravel-auth.email_verification.blocking' => true,
        ]);
        \Illuminate\Support\Facades\Route::get('/verify', fn () => 'verify')->name('gw.verify');
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $request = \Illuminate\Http\Request::create('/v1/sso/handshake', 'GET', ['sso_handshake' => 'tok']);
        $request->setUserResolver(fn () => (object) ['email' => 'a@b.test', 'email_verified_at' => null]);

        $method = new ReflectionMethod(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class, 'redirectIfUnverified');
        $response = $method->invoke(app(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class), $request, ['app' => 'hostbi']);

        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

        expect(parse_url($response->getTargetUrl(), PHP_URL_PATH))->toBe('/verify');
        expect($query)->toMatchArray(['identity' => 'a@b.test', 'type' => 'users', 'sso_handshake' => 'tok', 'app' => 'hostbi']);
    });

    it('verificado, sin ruta configurada o con verificación no bloqueante no desvía', function () {
        \Illuminate\Support\Facades\Route::get('/verify', fn () => 'verify')->name('gw.verify');
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
        $method = new ReflectionMethod(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class, 'redirectIfUnverified');
        $controller = app(\DantePiazza\LaravelAuth\Http\Controllers\SsoHandshakeController::class);

        $call = function (?string $verifiedAt) use ($method, $controller) {
            $request = \Illuminate\Http\Request::create('/v1/sso/handshake');
            $request->setUserResolver(fn () => (object) ['email' => 'a@b.test', 'email_verified_at' => $verifiedAt]);

            return $method->invoke($controller, $request, []);
        };

        config(['laravel-auth.sso.unverified_route' => 'gw.verify', 'laravel-auth.email_verification.enabled' => true, 'laravel-auth.email_verification.blocking' => true]);
        expect($call('2026-01-01'))->toBeNull();

        config(['laravel-auth.sso.unverified_route' => null]);
        expect($call(null))->toBeNull();

        config(['laravel-auth.sso.unverified_route' => 'gw.verify', 'laravel-auth.email_verification.blocking' => false]);
        expect($call(null))->toBeNull();
    });
});
