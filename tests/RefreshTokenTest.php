<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;

uses(TestCase::class);

describe('refresh token', function () {

    it('rota el access token con un refresh token válido', function () {
        $user = $this->createUser();

        $login = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $login->json('data.refresh_token');

        $response = $this->postJson('/v1/users/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token'],
            ]);

        // El nuevo access token debe ser distinto al original
        expect($response->json('data.access_token'))
            ->not->toBe($login->json('data.access_token'));
    });

    it('falla con un refresh token inválido', function () {
        $response = $this->postJson('/v1/users/auth/refresh', [
            'refresh_token' => 'token_invalido',
        ]);

        $response->assertStatus(401);
    });

    it('falla con un refresh token expirado', function () {
        $user = $this->createUser();

        $login = $this->postJson('/v1/users/auth/login', [
            'identity' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Expirar el refresh token manualmente
        $user->refreshTokens()->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/v1/users/auth/refresh', [
            'refresh_token' => $login->json('data.refresh_token'),
        ]);

        $response->assertStatus(401);
    });

    it('falla si no se envía el refresh token', function () {
        $response = $this->postJson('/v1/users/auth/refresh', []);

        $response->assertStatus(422);
    });
});
