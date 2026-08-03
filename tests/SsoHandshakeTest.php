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
