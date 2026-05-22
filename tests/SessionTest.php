<?php

use DantePiazza\LaravelAuth\Tests\TestCase;
use DantePiazza\LaravelAuth\Tests\TestUser;

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
