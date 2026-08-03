<?php

use DantePiazza\LaravelAuth\Tests\TestCase;

uses(TestCase::class);

describe('sesiones', function () {

    it('lista las sesiones activas del usuario autenticado', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        // Genera un refresh token adicional simulando otro dispositivo
        $access = $user->createToken('access_token')->accessToken;
        $user->createRefreshToken($access->id);

        $response = $this->withToken($token)->getJson('/v1/users/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        ['id', 'access_token_id', 'expires_at'],
                    ],
                ],
            ]);

        expect(count($response->json('data.results')))->toBe(1);
    });

    it('revoca una sesión puntual', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $access       = $user->createToken('access_token')->accessToken;
        $refreshToken = $user->refreshTokens()->create([
            'token'           => hash('sha256', 'plain-refresh'),
            'access_token_id' => $access->id,
            'expires_at'      => now()->addDay(),
        ]);

        $response = $this->withToken($token)->deleteJson("/v1/users/auth/sessions/{$refreshToken->id}");

        $response->assertStatus(200);
        expect($user->refreshTokens()->whereKey($refreshToken->id)->exists())->toBeFalse();
    });

    it('falla al revocar una sesión que no existe', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->deleteJson('/v1/users/auth/sessions/999999');

        $response->assertStatus(404);
    });

    it('revoca todas las sesiones del usuario', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $accessA = $user->createToken('a')->accessToken;
        $accessB = $user->createToken('b')->accessToken;
        $user->createRefreshToken($accessA->id);
        $user->createRefreshToken($accessB->id);

        $response = $this->withToken($token)->deleteJson('/v1/users/auth/sessions');

        $response->assertStatus(200);
        expect($user->refreshTokens()->count())->toBe(0);
    });

    it('el logout limpia la cookie de refresh token con el dominio configurado', function () {
        config(['session.domain' => '.example.test']);

        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/v1/users/auth/logout');

        $response->assertStatus(200);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'user_refresh_token');

        expect($cookie)->not->toBeNull();
        expect($cookie->getDomain())->toBe('.example.test');
        expect($cookie->isCleared())->toBeTrue();
    });
});
