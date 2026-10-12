<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;
use DantePiazza\LaravelAuth\Models\PersonalRefreshToken;

uses(TestCase::class);

describe('logout', function () {

    it('cierra sesión correctamente', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/v1/users/auth/logout');

        $response->assertStatus(200);

        // El access token debe haber sido eliminado
        expect($user->tokens()->count())->toBe(0);
    });

    it('también elimina el refresh token asociado, no solo el access token', function () {
        // Bug real encontrado 2026-09-13: currentRefreshToken() usaba un
        // hasOne mal armado que nunca matcheaba nada (comparaba el UUID/id
        // del usuario contra la columna access_token_id), así que
        // removeCurrentSession() nunca borraba de verdad la fila de
        // personal_refresh_tokens — solo el access token, en silencio.
        $user        = $this->createUser();
        $accessToken = $user->createToken('test');
        $user->createRefreshToken($accessToken->accessToken->id);

        expect(PersonalRefreshToken::where('access_token_id', $accessToken->accessToken->id)->exists())->toBeTrue();

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/v1/users/auth/logout')
            ->assertStatus(200);

        expect(PersonalRefreshToken::where('access_token_id', $accessToken->accessToken->id)->exists())->toBeFalse();
    });

    it('no rompe con una sesión de cookie (TransientToken, sin access token en BD)', function () {
        // Logout web del Provider: el usuario viene del guard 'web' y Sanctum
        // devuelve un TransientToken (sin id) en vez de un PersonalAccessToken.
        $user = $this->createUser();
        $user->withAccessToken(new \Laravel\Sanctum\TransientToken);

        expect($user->currentRefreshToken())->toBeNull();

        $user->removeCurrentSession();
    });

    it('falla sin token de autenticación', function () {
        $response = $this->postJson('/v1/users/auth/logout');

        $response->assertStatus(401);
    });
});

describe('current', function () {

    it('retorna los datos del usuario autenticado', function () {
        $user  = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/v1/users/auth/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'authenticated',
                    'model' => ['id', 'name', 'email'],
                ],
            ])
            ->assertJsonPath('data.authenticated', true);
    });

    it('falla sin token de autenticación', function () {
        $response = $this->getJson('/v1/users/auth/current');

        $response->assertStatus(401);
    });
});
