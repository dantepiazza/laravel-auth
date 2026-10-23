<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;
use Illuminate\Support\Facades\Mail;

uses(TestCase::class);

describe('email verification', function () {

    it('verifica el email con código válido', function () {
        $user = $this->createUser();
        $code = $user->generateVerificationCode('email.verify');

        $response = $this->postJson('/v1/users/auth/email/verify', [
            'identity' => 'test@example.com',
            'code'     => (string) $code,
        ]);

        $response->assertStatus(200);
        expect($user->fresh()->email_verified_at)->not->toBeNull();
    });

    it('falla con código incorrecto', function () {
        $this->createUser();

        $response = $this->postJson('/v1/users/auth/email/verify', [
            'identity' => 'test@example.com',
            'code'     => '000000',
        ]);

        $response->assertStatus(422);
    });

    it('falla si el identity no existe', function () {
        $response = $this->postJson('/v1/users/auth/email/verify', [
            'identity' => 'noexiste@example.com',
            'code'     => '123456',
        ]);

        $response->assertStatus(404);
    });

    it('reenvía el código de verificación', function () {
        Mail::fake();

        $this->createUser();

        $response = $this->postJson('/v1/users/auth/email/resend', [
            'identity' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\DantePiazza\LaravelAuth\Mails\VerificationCodeEmail::class);
    });

    it('falla al reenviar si el email ya está verificado', function () {
        $this->createUser(['email_verified_at' => now()]);

        $response = $this->postJson('/v1/users/auth/email/resend', [
            'identity' => 'test@example.com',
        ]);

        $response->assertStatus(422);
    });

    it('bloquea rutas protegidas si blocking está activo y el email no está verificado', function () {
        config(['laravel-auth.email_verification.blocking' => true]);

        $user  = $this->createUser(['email_verified_at' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/v1/users/auth/current');

        $response->assertStatus(403);
    });

    it('permite acceso si email está verificado y blocking está activo', function () {
        config(['laravel-auth.email_verification.blocking' => true]);

        $user  = $this->createUser(['email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/v1/users/auth/current');

        $response->assertStatus(200);
    });

    it('rechaza con 401 si no hay usuario autenticado y blocking está activo', function () {
        config(['laravel-auth.email_verification.blocking' => true]);
        \Illuminate\Support\Facades\Auth::forgetUser();

        $response = (new \DantePiazza\LaravelAuth\Http\Middleware\VerifyEmail())
            ->handle(\Illuminate\Http\Request::create('/x'), fn () => response('ok'));

        expect($response->getStatusCode())->toBe(401)
            ->and(json_decode($response->getContent(), true)['data']['reason'] ?? null)->toBe('unauthenticated');
    });

    it('deja pasar sin usuario si blocking está desactivado', function () {
        config(['laravel-auth.email_verification.blocking' => false]);
        \Illuminate\Support\Facades\Auth::forgetUser();

        $response = (new \DantePiazza\LaravelAuth\Http\Middleware\VerifyEmail())
            ->handle(\Illuminate\Http\Request::create('/x'), fn () => response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('responde 403 con reason email_unverified para un usuario sin verificar', function () {
        config(['laravel-auth.email_verification.blocking' => true]);

        $user  = $this->createUser(['email_verified_at' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/v1/users/auth/current')
            ->assertStatus(403)
            ->assertJsonPath('data.reason', 'email_unverified');
    });
});
