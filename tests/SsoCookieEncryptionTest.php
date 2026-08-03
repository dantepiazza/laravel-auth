<?php

use DantePiazza\LaravelAuth\Tests\SsoTestCase;
use DantePiazza\LaravelAuth\Support\SsoHandshakeEncryptor;

uses(SsoTestCase::class);

describe('cookie de refresh token en modo SSO', function () {

    it('cifra el valor de la cookie con AUTH_SSO_SECRET, no con APP_KEY', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertCookieNotExpired('user_refresh_token');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'user_refresh_token');

        // Si viajara sin cifrar o cifrada con APP_KEY (Crypt::decrypt), esto
        // tiraría una excepción — decodifica solo con el secret dedicado.
        $decrypted = (new SsoHandshakeEncryptor())->decrypt($cookie->getValue(), PHP_INT_MAX);

        expect($decrypted)->toHaveKey('refresh_token');
        expect($decrypted['refresh_token'])->toBeString()->not->toBeEmpty();
    });

    it('la cookie viaja raw (Laravel no le aplica su propio cifrado de EncryptCookies encima)', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'user_refresh_token');

        expect($cookie->isRaw())->toBeTrue();
    });
});

describe('cookie de refresh token con SSO desactivado (comportamiento por defecto)', function () {

    it('sigue viajando en texto plano, sin el cifrado dedicado del SSO', function () {
        // Esta describe corre bajo SsoTestCase (mode=consumer a nivel app,
        // necesario para que las rutas de handshake existan), así que se
        // fuerza acá el mismo escenario de una instalación sin SSO: sin
        // "mode" configurado, makeRefreshCookie() no debe cifrar nada.
        config(['laravel-auth.sso.mode' => null]);

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'user_refresh_token');

        expect($cookie->isRaw())->toBeFalse();
    });
});
